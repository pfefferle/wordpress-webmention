/**
 * Microformats Reaction Links Extension
 *
 * Extends the WordPress block editor link popover to add microformats2
 * reaction classes (u-in-reply-to, u-like-of, etc.) directly to anchor elements.
 */
import domReady from '@wordpress/dom-ready';
import { select, dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import './editor.scss';

/**
 * Microformats reaction types
 */
const REACTION_TYPES = [
	{
		value: '',
		label: __( 'None', 'webmention' ),
	},
	{
		value: 'u-in-reply-to',
		label: __( 'Reply', 'webmention' ),
	},
	{
		value: 'u-like-of',
		label: __( 'Like', 'webmention' ),
	},
	{
		value: 'u-repost-of',
		label: __( 'Repost', 'webmention' ),
	},
	{
		value: 'u-bookmark-of',
		label: __( 'Bookmark', 'webmention' ),
	},
	{
		value: 'u-tag-of',
		label: __( 'Tag', 'webmention' ),
	},
];

const REACTION_CLASSES = REACTION_TYPES.map( ( t ) => t.value ).filter(
	( v ) => v
);

/**
 * Debounce delay in milliseconds for MutationObserver callbacks.
 */
const DEBOUNCE_DELAY = 50;
let lastRichTextSelection = null;
let lastAnchorSelection = null;

/**
 * Get the currently selected block
 */
function getSelectedBlock() {
	return select( 'core/block-editor' ).getSelectedBlock();
}

/**
 * Parse reaction class from a class string
 *
 * @param {string} classStr Class attribute string.
 * @return {string} Parsed reaction class.
 */
function parseReactionClass( classStr ) {
	if ( ! classStr ) {
		return '';
	}

	const classTokens = classStr.trim().split( /\s+/ );

	for ( const cls of REACTION_CLASSES ) {
		if ( classTokens.includes( cls ) ) {
			return cls;
		}
	}
	return '';
}

/**
 * Get the URL being edited from the link popover
 */
function getCurrentLinkUrl() {
	const urlInput = document.querySelector(
		'.block-editor-link-control__search-input input'
	);
	if ( urlInput && urlInput.value ) {
		return urlInput.value;
	}

	const urlDisplayLink = document.querySelector(
		'.block-editor-link-control__search-item-info a'
	);
	if ( urlDisplayLink ) {
		const href = urlDisplayLink.getAttribute( 'href' );
		if ( href ) {
			return href.trim();
		}
	}

	return null;
}

/**
 * Normalize rich text values to an HTML string.
 *
 * @param {*} value Raw attribute value.
 * @return {?string} HTML string when supported, otherwise null.
 */
function getHtmlString( value ) {
	let content = value;

	if ( typeof content === 'object' && content?.toHTMLString ) {
		content = content.toHTMLString();
	}

	return typeof content === 'string' ? content : null;
}

/**
 * Get the selected rich text attribute and its HTML content.
 */
function getEditableRichTextContext() {
	const block = getSelectedBlock();
	if ( ! block ) {
		return null;
	}

	const { clientId, attributes } = block;
	const blockEditorStore = select( 'core/block-editor' );
	const hasSelectionApi =
		typeof blockEditorStore.getSelectionStart === 'function';
	const selectionStart = hasSelectionApi
		? blockEditorStore.getSelectionStart()
		: null;
	let attributeKey = selectionStart?.attributeKey;

	if ( selectionStart?.clientId && selectionStart.clientId !== clientId ) {
		return null;
	}

	if ( attributeKey ) {
		lastRichTextSelection = {
			clientId,
			attributeKey,
		};
	}

	if ( ! attributeKey ) {
		if ( lastRichTextSelection?.clientId === clientId ) {
			attributeKey = lastRichTextSelection.attributeKey;
		}
	}

	if ( ! attributeKey ) {
		// If the selection API is available but there is no rich-text attribute,
		// this block shape is not supported by this extension.
		if ( hasSelectionApi ) {
			return null;
		}

		if ( ! Object.prototype.hasOwnProperty.call( attributes, 'content' ) ) {
			return null;
		}

		attributeKey = 'content';
	}

	const content = getHtmlString( attributes[ attributeKey ] );
	if ( ! content ) {
		return null;
	}

	return {
		clientId,
		attributeKey,
		content,
	};
}

/**
 * Parse anchors from an HTML string.
 *
 * @param {string} content HTML content from a rich-text attribute.
 * @return {?Object} Parsed container and anchor list.
 */
function parseContentAnchors( content ) {
	const parser = new window.DOMParser();
	const doc = parser.parseFromString(
		`<div>${ content }</div>`,
		'text/html'
	);
	const container = doc.body.firstChild;

	if ( ! container ) {
		return null;
	}

	return {
		container,
		anchors: Array.from( container.querySelectorAll( 'a' ) ),
	};
}

/**
 * Get the currently selected anchor from the editor and its index.
 *
 * @param {string} clientId Current block client ID.
 * @return {?Object} Selected anchor position and href.
 */
function getSelectedAnchorInEditor( clientId ) {
	const selectedBlockEl = document.querySelector(
		`[data-block="${ clientId }"]`
	);
	const view =
		selectedBlockEl?.ownerDocument?.defaultView || document.defaultView;
	const selection = view?.getSelection ? view.getSelection() : null;

	if ( ! selection || selection.rangeCount === 0 ) {
		return null;
	}

	let node = selection.anchorNode;

	if ( ! node || ( selectedBlockEl && ! selectedBlockEl.contains( node ) ) ) {
		return null;
	}

	if ( node.nodeType === 3 ) {
		node = node.parentElement;
	}

	if ( ! node || node.nodeType !== 1 || typeof node.closest !== 'function' ) {
		return null;
	}

	const anchor = node.closest( 'a' );
	if ( ! anchor ) {
		return null;
	}

	const editableRoot = anchor.closest( '[contenteditable="true"]' );
	if ( ! editableRoot ) {
		return null;
	}

	const anchorIndex = Array.from(
		editableRoot.querySelectorAll( 'a' )
	).indexOf( anchor );
	if ( anchorIndex < 0 ) {
		return null;
	}

	return {
		index: anchorIndex,
		href: anchor.getAttribute( 'href' )?.trim() || '',
	};
}

/**
 * Resolve the single target anchor index in parsed content.
 *
 * @param {Array}   anchors        Parsed anchor elements from block attributes.
 * @param {?Object} selectedAnchor Selected anchor position from editor DOM.
 * @param {?string} targetUrl      URL currently shown in the link popover.
 * @return {number} Anchor index, or -1 when not resolvable.
 */
function getTargetAnchorIndex( anchors, selectedAnchor, targetUrl ) {
	if ( selectedAnchor && selectedAnchor.index < anchors.length ) {
		const indexedAnchorHref = (
			anchors[ selectedAnchor.index ].getAttribute( 'href' ) || ''
		).trim();
		if (
			! selectedAnchor.href ||
			indexedAnchorHref === selectedAnchor.href
		) {
			return selectedAnchor.index;
		}
	}

	if ( selectedAnchor?.href ) {
		const selectedHrefIndex = anchors.findIndex(
			( anchor ) =>
				( anchor.getAttribute( 'href' ) || '' ).trim() ===
				selectedAnchor.href
		);
		if ( selectedHrefIndex >= 0 ) {
			return selectedHrefIndex;
		}
	}

	if ( targetUrl ) {
		return anchors.findIndex(
			( anchor ) =>
				( anchor.getAttribute( 'href' ) || '' ).trim() ===
				targetUrl.trim()
		);
	}

	return -1;
}

/**
 * Build a full reaction editing context for the selected link.
 */
function getReactionContext() {
	const richTextContext = getEditableRichTextContext();
	if ( ! richTextContext ) {
		return null;
	}

	const parsed = parseContentAnchors( richTextContext.content );
	if ( ! parsed ) {
		return null;
	}

	const targetUrl = getCurrentLinkUrl();
	let selectedAnchor = getSelectedAnchorInEditor( richTextContext.clientId );

	if ( selectedAnchor ) {
		lastAnchorSelection = {
			clientId: richTextContext.clientId,
			attributeKey: richTextContext.attributeKey,
			...selectedAnchor,
		};
	} else if (
		lastAnchorSelection?.clientId === richTextContext.clientId &&
		lastAnchorSelection?.attributeKey === richTextContext.attributeKey
	) {
		selectedAnchor = lastAnchorSelection;
	}

	const targetAnchorIndex = getTargetAnchorIndex(
		parsed.anchors,
		selectedAnchor,
		targetUrl
	);

	if ( targetAnchorIndex < 0 ) {
		return null;
	}

	return {
		...richTextContext,
		...parsed,
		targetAnchorIndex,
	};
}

/**
 * Get current reaction from the selected anchor in block content.
 *
 * @param {Object} reactionContext Reaction context for the selected link.
 * @return {string} Active reaction class.
 */
function getCurrentReactionFromBlock( reactionContext ) {
	const targetAnchor =
		reactionContext.anchors[ reactionContext.targetAnchorIndex ];
	if ( ! targetAnchor ) {
		return '';
	}

	return parseReactionClass( targetAnchor.getAttribute( 'class' ) );
}

/**
 * Apply reaction class to the anchor in block content
 *
 * @param {string} reaction Reaction class to apply.
 */
function applyReaction( reaction ) {
	const reactionContext = getReactionContext();
	if ( ! reactionContext ) {
		return;
	}

	const targetAnchor =
		reactionContext.anchors[ reactionContext.targetAnchorIndex ];
	if ( ! targetAnchor ) {
		return;
	}

	const previousClassAttribute = targetAnchor.getAttribute( 'class' ) || '';

	// Remove existing reaction classes
	REACTION_CLASSES.forEach( ( cls ) => {
		targetAnchor.classList.remove( cls );
	} );

	// Add new reaction class
	if ( reaction ) {
		targetAnchor.classList.add( reaction );
	}

	// Clean up empty class attribute
	if ( targetAnchor.classList.length === 0 ) {
		targetAnchor.removeAttribute( 'class' );
	}

	const updatedClassAttribute = targetAnchor.getAttribute( 'class' ) || '';
	const modified = previousClassAttribute !== updatedClassAttribute;

	if ( modified ) {
		dispatch( 'core/block-editor' ).updateBlockAttributes(
			reactionContext.clientId,
			{
				[ reactionContext.attributeKey ]:
					reactionContext.container.innerHTML,
			}
		);
	}
}

/**
 * Create the reaction dropdown
 *
 * @param {string} targetKey       Unique key for selected anchor context.
 * @param {string} currentReaction Current reaction class.
 * @return {HTMLElement} Dropdown container element.
 */
function createReactionDropdown( targetKey, currentReaction ) {
	const container = document.createElement( 'div' );
	container.className =
		'block-editor-link-control__setting webmention-reaction-setting';
	container.dataset.target = targetKey;

	const label = document.createElement( 'label' );
	label.className = 'webmention-reaction-setting__label';
	label.setAttribute( 'for', 'webmention-reaction-select' );
	label.textContent = __( 'Reaction', 'webmention' );
	container.appendChild( label );

	const selectEl = document.createElement( 'select' );
	selectEl.id = 'webmention-reaction-select';
	selectEl.className =
		'webmention-reaction-setting__select components-select-control__input';

	REACTION_TYPES.forEach( ( type ) => {
		const option = document.createElement( 'option' );
		option.value = type.value;
		option.textContent = type.label;
		option.selected = type.value === currentReaction;
		selectEl.appendChild( option );
	} );

	selectEl.addEventListener( 'change', ( e ) => {
		applyReaction( e.target.value );
	} );

	container.appendChild( selectEl );
	return container;
}

/**
 * Inject the reaction dropdown into the link popover
 */
function injectReactionDropdown() {
	const settingsDrawer = document.querySelector(
		'.block-editor-link-control__settings'
	);

	if ( ! settingsDrawer ) {
		return;
	}

	const reactionContext = getReactionContext();
	const existingDropdown = settingsDrawer.querySelector(
		'.webmention-reaction-setting'
	);
	if ( ! reactionContext ) {
		if ( existingDropdown ) {
			existingDropdown.remove();
		}
		return;
	}

	const targetKey = `${ reactionContext.clientId }:${ reactionContext.attributeKey }:${ reactionContext.targetAnchorIndex }`;
	const currentReaction = getCurrentReactionFromBlock( reactionContext );

	if ( existingDropdown ) {
		// Recreate the dropdown if another link or attribute is selected.
		if ( existingDropdown.dataset.target !== targetKey ) {
			existingDropdown.remove();
		} else {
			// Selection is the same, just update the selected value if needed.
			const selectEl = existingDropdown.querySelector( 'select' );
			if ( selectEl && selectEl.value !== currentReaction ) {
				selectEl.value = currentReaction;
			}
			return;
		}
	}

	const dropdown = createReactionDropdown( targetKey, currentReaction );
	settingsDrawer.appendChild( dropdown );
}

/**
 * Debounce helper
 *
 * @param {Function} func Function to debounce.
 * @param {number}   wait Debounce delay in milliseconds.
 * @return {Function} Debounced function.
 */
function debounce( func, wait ) {
	let timeout;
	return function ( ...args ) {
		clearTimeout( timeout );
		timeout = setTimeout( () => func.apply( this, args ), wait );
	};
}

/**
 * Initialize
 */
domReady( () => {
	const debouncedInject = debounce( injectReactionDropdown, DEBOUNCE_DELAY );
	const debouncedSelectionCache = debounce(
		getReactionContext,
		DEBOUNCE_DELAY
	);

	const observer = new window.MutationObserver( () => {
		debouncedInject();
	} );

	document.addEventListener( 'selectionchange', debouncedSelectionCache );

	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );

	// Cleanup on page unload
	window.addEventListener( 'beforeunload', () => {
		observer.disconnect();
		document.removeEventListener(
			'selectionchange',
			debouncedSelectionCache
		);
	} );
} );
