/**
 * Microformats Reaction Links logic
 *
 * Adds a friendly picker for microformats2 reaction classes (u-in-reply-to,
 * u-like-of, etc.) and a "Don't send a Webmention" toggle (the `nomention`
 * class) to the WordPress block editor link popover.
 *
 * Both controls drive WordPress core's own "Additional CSS class(es)" link
 * setting instead of editing the anchor directly. That keeps reactions on the
 * native link-editing pipeline: changing one stages the change, highlights the
 * popover's "Apply" button, and is committed to (or discarded from) the anchor
 * exactly like every other link setting.
 */
import { __ } from '@wordpress/i18n';

/**
 * Microformats reaction types
 */
export const REACTION_TYPES = [
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

export const REACTION_CLASSES = REACTION_TYPES.map( ( t ) => t.value ).filter(
	( v ) => v
);

/**
 * CSS class that excludes a link from sending Webmentions.
 */
export const NOMENTION_CLASS = 'nomention';

/**
 * Debounce delay in milliseconds for MutationObserver callbacks.
 */
export const DEBOUNCE_DELAY = 50;

/**
 * Maximum time in milliseconds to wait for the native CSS-classes input to
 * render after the setting is activated.
 */
export const INPUT_RENDER_TIMEOUT = 500;

/**
 * Parse reaction class from a class string
 *
 * @param {string} classStr Class attribute string.
 * @return {string} Parsed reaction class.
 */
export function parseReactionClass( classStr ) {
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
 * Check whether a class string contains the nomention class.
 *
 * @param {string} classStr Class attribute string.
 * @return {boolean} Whether the link is excluded from sending Webmentions.
 */
export function hasNomentionClass( classStr ) {
	return ( classStr || '' ).trim().split( /\s+/ ).includes( NOMENTION_CLASS );
}

/**
 * Get the settings drawer of the currently open link popover.
 *
 * @return {?HTMLElement} Settings drawer, or null when no link is being edited.
 */
export function getLinkSettingsDrawer() {
	return document.querySelector( '.block-editor-link-control__settings' );
}

/**
 * Locate core's "Additional CSS class(es)" setting row within the drawer.
 *
 * It is the only link setting rendered as a fieldset (the toggle settings such
 * as "Open in new tab" are plain checkboxes), which lets us find it without
 * relying on translated label text.
 *
 * @param {HTMLElement} settings Link settings drawer element.
 * @return {?HTMLElement} CSS-classes setting row, or null when not present.
 */
export function getCssClassesRow( settings ) {
	const rows = settings.querySelectorAll(
		'.block-editor-link-control__setting'
	);

	for ( const row of rows ) {
		if ( row.classList.contains( 'webmention-reaction-setting' ) ) {
			continue;
		}
		if ( row.querySelector( 'fieldset' ) ) {
			return row;
		}
	}
	return null;
}

/**
 * Get the text input for the CSS-classes setting, when it is rendered.
 *
 * The input only exists once the setting has been activated.
 *
 * @param {HTMLElement} cssRow CSS-classes setting row.
 * @return {?HTMLInputElement} Input element, or null when the setting is inactive.
 */
export function getCssClassesInput( cssRow ) {
	return cssRow.querySelector( 'input.components-input-control__input' );
}

/**
 * Set a React-controlled input's value so the component registers the change.
 *
 * @param {HTMLInputElement} input Target input.
 * @param {string}           value New value.
 */
export function setControlledInputValue( input, value ) {
	const descriptor = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	);
	descriptor.set.call( input, value );
	input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

/**
 * Wait for an element to appear, polling on animation frames.
 *
 * @param {() => ?HTMLElement} getter  Returns the element or a falsy value.
 * @param {number}             timeout Maximum wait in milliseconds.
 * @return {Promise<?HTMLElement>} Resolves with the element, or null on timeout.
 */
export function waitForElement( getter, timeout ) {
	return new Promise( ( resolve ) => {
		const start = Date.now();
		const poll = () => {
			const element = getter();
			if ( element ) {
				resolve( element );
				return;
			}
			if ( Date.now() - start >= timeout ) {
				resolve( null );
				return;
			}
			window.requestAnimationFrame( poll );
		};
		poll();
	} );
}

/**
 * Read the reaction currently reflected in the CSS-classes setting.
 *
 * @param {HTMLElement} settings Link settings drawer element.
 * @return {string} Active reaction class, or an empty string for "None".
 */
export function getCurrentReaction( settings ) {
	const cssRow = getCssClassesRow( settings );
	if ( ! cssRow ) {
		return '';
	}

	const input = getCssClassesInput( cssRow );
	return input ? parseReactionClass( input.value ) : '';
}

/**
 * Read whether the CSS-classes setting currently contains the nomention class.
 *
 * @param {HTMLElement} settings Link settings drawer element.
 * @return {boolean} Whether the link is excluded from sending Webmentions.
 */
export function getCurrentNomention( settings ) {
	const cssRow = getCssClassesRow( settings );
	if ( ! cssRow ) {
		return false;
	}

	const input = getCssClassesInput( cssRow );
	return input ? hasNomentionClass( input.value ) : false;
}

/**
 * Build the CSS class string for a link after switching its reaction.
 *
 * Any non-reaction classes the author added are preserved; the reaction token
 * is swapped in or removed.
 *
 * @param {string} currentClasses Existing class attribute value.
 * @param {string} reaction       New reaction class, or empty string to remove it.
 * @return {string} Updated class attribute value.
 */
export function buildClassValue( currentClasses, reaction ) {
	const tokens = ( currentClasses || '' )
		.trim()
		.split( /\s+/ )
		.filter( ( token ) => token && ! REACTION_CLASSES.includes( token ) );

	if ( reaction ) {
		tokens.push( reaction );
	}

	return tokens.join( ' ' );
}

/**
 * Build the CSS class string for a link after toggling the nomention class.
 *
 * @param {string}  currentClasses Existing class attribute value.
 * @param {boolean} enabled        Whether the nomention class should be present.
 * @return {string} Updated class attribute value.
 */
export function buildNomentionClassValue( currentClasses, enabled ) {
	const tokens = ( currentClasses || '' )
		.trim()
		.split( /\s+/ )
		.filter( ( token ) => token && token !== NOMENTION_CLASS );

	if ( enabled ) {
		tokens.push( NOMENTION_CLASS );
	}

	return tokens.join( ' ' );
}

/**
 * Update core's "Additional CSS class(es)" setting.
 *
 * Activates the setting on demand, then updates its input. Core takes over from
 * there: the change is staged, the popover's "Apply" button lights up, and the
 * classes are written to the anchor only when the author applies the link.
 *
 * @param {(currentClasses: string) => string} buildValue Returns the new class value.
 */
export async function updateCssClasses( buildValue ) {
	const settings = getLinkSettingsDrawer();
	if ( ! settings ) {
		return;
	}

	const cssRow = getCssClassesRow( settings );
	if ( ! cssRow ) {
		return;
	}

	let input = getCssClassesInput( cssRow );
	const currentClasses = input ? input.value : '';
	const nextValue = buildValue( currentClasses );

	// Nothing to write and the setting is inactive: leave it untouched so we do
	// not needlessly enable the "Apply" button.
	if ( ! nextValue && ! input ) {
		return;
	}

	// The input only exists once the setting is active; activate it on demand.
	if ( ! input ) {
		const activator = cssRow.querySelector( 'input[type="checkbox"]' );
		if ( activator ) {
			activator.click();
			input = await waitForElement(
				() => getCssClassesInput( cssRow ),
				INPUT_RENDER_TIMEOUT
			);
		}
	}

	if ( ! input ) {
		return;
	}

	setControlledInputValue( input, nextValue );
}

/**
 * Apply a reaction by writing it into core's "Additional CSS class(es)" setting.
 *
 * @param {string} reaction Reaction class to apply, or empty string for "None".
 */
export function applyReaction( reaction ) {
	return updateCssClasses( ( currentClasses ) =>
		buildClassValue( currentClasses, reaction )
	);
}

/**
 * Add or remove the nomention class via core's "Additional CSS class(es)" setting.
 *
 * @param {boolean} enabled Whether the link should be excluded from Webmentions.
 */
export function applyNomention( enabled ) {
	return updateCssClasses( ( currentClasses ) =>
		buildNomentionClassValue( currentClasses, enabled )
	);
}

/**
 * Create the reaction dropdown
 *
 * @param {string} currentReaction Current reaction class.
 * @return {HTMLElement} Dropdown container element.
 */
export function createReactionDropdown( currentReaction ) {
	const container = document.createElement( 'div' );
	container.className =
		'block-editor-link-control__setting webmention-reaction-setting';

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

	selectEl.addEventListener( 'change', ( event ) => {
		applyReaction( event.target.value );
	} );

	container.appendChild( selectEl );
	return container;
}

/**
 * Create the "Don't send a Webmention" toggle
 *
 * @param {boolean} checked Whether the link currently has the nomention class.
 * @return {HTMLElement} Toggle container element.
 */
export function createNomentionToggle( checked ) {
	const container = document.createElement( 'div' );
	container.className =
		'block-editor-link-control__setting webmention-nomention-setting';

	// Mirror the markup of core's CheckboxControl so the toggle picks up the
	// same styles as the native link settings above it.
	const control = document.createElement( 'div' );
	control.className = 'components-base-control components-checkbox-control';
	const field = document.createElement( 'div' );
	field.className = 'components-base-control__field';
	const inputContainer = document.createElement( 'span' );
	inputContainer.className = 'components-checkbox-control__input-container';

	const checkbox = document.createElement( 'input' );
	checkbox.type = 'checkbox';
	checkbox.id = 'webmention-nomention-checkbox';
	checkbox.className = 'components-checkbox-control__input';
	checkbox.checked = checked;
	checkbox.addEventListener( 'change', ( event ) => {
		applyNomention( event.target.checked );
	} );
	inputContainer.appendChild( checkbox );

	const label = document.createElement( 'label' );
	label.className = 'components-checkbox-control__label';
	label.setAttribute( 'for', 'webmention-nomention-checkbox' );
	label.textContent = __( "Don't send a Webmention", 'webmention' );

	field.appendChild( inputContainer );
	field.appendChild( label );
	control.appendChild( field );
	container.appendChild( control );

	return container;
}

/**
 * Inject or refresh the reaction dropdown inside the link settings drawer.
 */
export function injectReactionDropdown() {
	const settings = getLinkSettingsDrawer();
	const existingDropdown = settings
		? settings.querySelector( '.webmention-reaction-setting' )
		: null;

	// Only offer reactions when core exposes its CSS-classes setting, which is
	// the mechanism the picker builds on.
	if ( ! settings || ! getCssClassesRow( settings ) ) {
		if ( existingDropdown ) {
			existingDropdown.remove();
		}
		return;
	}

	const currentReaction = getCurrentReaction( settings );

	if ( existingDropdown ) {
		const selectEl = existingDropdown.querySelector( 'select' );
		// Keep the picker in sync with the link's classes, but do not fight the
		// author while they have the dropdown focused.
		if (
			selectEl &&
			selectEl.value !== currentReaction &&
			settings.ownerDocument.activeElement !== selectEl
		) {
			selectEl.value = currentReaction;
		}
		return;
	}

	settings.appendChild( createReactionDropdown( currentReaction ) );
}

/**
 * Inject or refresh the nomention toggle inside the link settings drawer.
 */
export function injectNomentionToggle() {
	const settings = getLinkSettingsDrawer();
	const existingToggle = settings
		? settings.querySelector( '.webmention-nomention-setting' )
		: null;

	// Like the reaction picker, the toggle builds on core's CSS-classes setting.
	if ( ! settings || ! getCssClassesRow( settings ) ) {
		if ( existingToggle ) {
			existingToggle.remove();
		}
		return;
	}

	const currentNomention = getCurrentNomention( settings );

	if ( existingToggle ) {
		const checkbox = existingToggle.querySelector( 'input' );
		if (
			checkbox &&
			checkbox.checked !== currentNomention &&
			settings.ownerDocument.activeElement !== checkbox
		) {
			checkbox.checked = currentNomention;
		}
		return;
	}

	settings.appendChild( createNomentionToggle( currentNomention ) );
}

/**
 * Debounce helper
 *
 * @param {(...args: unknown[]) => void} func Function to debounce.
 * @param {number}                       wait Debounce delay in milliseconds.
 * @return {(...args: unknown[]) => void} Debounced function.
 */
export function debounce( func, wait ) {
	let timeout;
	return function ( ...args ) {
		clearTimeout( timeout );
		timeout = setTimeout( () => func.apply( this, args ), wait );
	};
}

/**
 * Watch the editor for the link popover and keep the injected controls in sync.
 */
export function init() {
	const debouncedInject = debounce( () => {
		injectReactionDropdown();
		injectNomentionToggle();
	}, DEBOUNCE_DELAY );

	// The link popover mounts and updates as the author edits links; re-run the
	// injection on DOM changes so the controls appear and stay in sync.
	const observer = new window.MutationObserver( debouncedInject );

	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );

	// Cleanup on page unload
	window.addEventListener( 'beforeunload', () => {
		observer.disconnect();
	} );
}
