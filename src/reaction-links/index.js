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

const REACTION_CLASSES = REACTION_TYPES
	.map( ( t ) => t.value )
	.filter( ( v ) => v );

/**
 * Debounce delay in milliseconds for MutationObserver callbacks.
 */
const DEBOUNCE_DELAY = 50;

/**
 * Get the currently selected block
 */
function getSelectedBlock() {
	return select( 'core/block-editor' ).getSelectedBlock();
}

/**
 * Parse reaction class from a class string
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
	const urlInput = document.querySelector( '.block-editor-link-control__search-input input' );
	if ( urlInput && urlInput.value ) {
		return urlInput.value;
	}

	const urlDisplayLink = document.querySelector( '.block-editor-link-control__search-item-info a' );
	if ( urlDisplayLink ) {
		const href = urlDisplayLink.getAttribute( 'href' );
		if ( href ) {
			return href.trim();
		}
	}

	return null;
}

/**
 * Get current reaction from block content for a specific URL
 */
function getCurrentReactionFromBlock( targetUrl ) {
	const block = getSelectedBlock();
	if ( ! block ) {
		return '';
	}

	const { attributes } = block;

	if ( attributes.content ) {
		let content = attributes.content;

		if ( typeof content === 'object' && content.toHTMLString ) {
			content = content.toHTMLString();
		}

		if ( typeof content !== 'string' ) {
			return '';
		}

		const parser = new DOMParser();
		const doc = parser.parseFromString( `<div>${ content }</div>`, 'text/html' );
		const anchors = doc.querySelectorAll( 'a' );

		for ( const anchor of anchors ) {
			const href = anchor.getAttribute( 'href' );

			if ( ! targetUrl || href === targetUrl ) {
				const reaction = parseReactionClass( anchor.getAttribute( 'class' ) );
				if ( reaction ) {
					return reaction;
				}
			}
		}
	}

	return '';
}

/**
 * Apply reaction class to the anchor in block content
 */
function applyReaction( reaction ) {
	const block = getSelectedBlock();
	if ( ! block ) {
		return;
	}

	const { clientId, attributes } = block;
	const targetUrl = getCurrentLinkUrl();

	if ( ! targetUrl || ! attributes.content ) {
		return;
	}

	let content = attributes.content;

	if ( typeof content === 'object' && content.toHTMLString ) {
		content = content.toHTMLString();
	}

	if ( typeof content !== 'string' ) {
		return;
	}

	const parser = new DOMParser();
	const doc = parser.parseFromString( `<div>${ content }</div>`, 'text/html' );
	const container = doc.body.firstChild;
	const anchors = container.querySelectorAll( 'a' );
	let modified = false;

	anchors.forEach( ( anchor ) => {
		const href = anchor.getAttribute( 'href' );

		if ( targetUrl && href !== targetUrl ) {
			return;
		}

		// Remove existing reaction classes
		REACTION_CLASSES.forEach( ( cls ) => {
			anchor.classList.remove( cls );
		} );

		// Add new reaction class
		if ( reaction ) {
			anchor.classList.add( reaction );
		}

		// Clean up empty class attribute
		if ( anchor.classList.length === 0 ) {
			anchor.removeAttribute( 'class' );
		}

		modified = true;
	} );

	if ( modified ) {
		dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, {
			content: container.innerHTML,
		} );
	}
}

/**
 * Create the reaction dropdown
 */
function createReactionDropdown( targetUrl, currentReaction ) {
	const container = document.createElement( 'div' );
	container.className = 'block-editor-link-control__setting webmention-reaction-setting';
	container.dataset.url = targetUrl || '';

	const label = document.createElement( 'label' );
	label.className = 'webmention-reaction-setting__label';
	label.setAttribute( 'for', 'webmention-reaction-select' );
	label.textContent = __( 'Reaction', 'webmention' );
	container.appendChild( label );

	const selectEl = document.createElement( 'select' );
	selectEl.id = 'webmention-reaction-select';
	selectEl.className = 'webmention-reaction-setting__select components-select-control__input';

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
	const settingsDrawer = document.querySelector( '.block-editor-link-control__settings' );

	if ( ! settingsDrawer ) {
		return;
	}

	const targetUrl = getCurrentLinkUrl();
	const currentReaction = getCurrentReactionFromBlock( targetUrl );
	const existingDropdown = settingsDrawer.querySelector( '.webmention-reaction-setting' );

	if ( existingDropdown ) {
		// Check if URL changed - if so, update the dropdown
		if ( existingDropdown.dataset.url !== ( targetUrl || '' ) ) {
			existingDropdown.remove();
		} else {
			// URL is same, just update the selected value if needed
			const selectEl = existingDropdown.querySelector( 'select' );
			if ( selectEl && selectEl.value !== currentReaction ) {
				selectEl.value = currentReaction;
			}
			return;
		}
	}

	const dropdown = createReactionDropdown( targetUrl, currentReaction );
	settingsDrawer.appendChild( dropdown );
}

/**
 * Debounce helper
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

	const observer = new MutationObserver( () => {
		debouncedInject();
	} );

	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );

	// Cleanup on page unload
	window.addEventListener( 'beforeunload', () => {
		observer.disconnect();
	} );
} );
