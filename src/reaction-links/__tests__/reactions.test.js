import {
	REACTION_TYPES,
	parseReactionClass,
	buildClassValue,
	getCssClassesRow,
	getCssClassesInput,
	getCurrentReaction,
	applyReaction,
	createReactionDropdown,
	injectReactionDropdown,
} from '../reactions';

/**
 * Build a fake link-control settings drawer that mirrors the structure the
 * WordPress link popover renders, so the DOM helpers can be tested against it.
 *
 * @param {?Object} css        CSS-classes setting state, or null to omit the row.
 * @param {boolean} css.active Whether the setting is active (input rendered).
 * @param {string}  css.value  Initial value of the CSS-classes input.
 * @return {HTMLElement} The settings drawer, already attached to the document.
 */
function buildDrawer( css = { active: true, value: '' } ) {
	const drawer = document.createElement( 'div' );
	drawer.className = 'block-editor-link-control__settings';

	// Two native toggle settings (plain checkboxes, no fieldset).
	[ 'Open in new tab', 'Mark as nofollow' ].forEach( () => {
		const row = document.createElement( 'div' );
		row.className =
			'block-editor-link-control__setting components-checkbox-control';
		const checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		row.appendChild( checkbox );
		drawer.appendChild( row );
	} );

	// The "Additional CSS class(es)" setting: a fieldset with an activation
	// checkbox, and (when active) the text input.
	if ( css ) {
		const row = document.createElement( 'div' );
		row.className = 'block-editor-link-control__setting';
		const fieldset = document.createElement( 'fieldset' );
		row.appendChild( fieldset );

		const activator = document.createElement( 'input' );
		activator.type = 'checkbox';
		activator.checked = !! css.active;
		// Mimic core: activating the setting reveals the input synchronously.
		activator.addEventListener( 'click', () => {
			if (
				! fieldset.querySelector(
					'input.components-input-control__input'
				)
			) {
				const revealed = document.createElement( 'input' );
				revealed.type = 'text';
				revealed.className = 'components-input-control__input';
				revealed.value = '';
				fieldset.appendChild( revealed );
			}
		} );
		fieldset.appendChild( activator );

		if ( css.active ) {
			const input = document.createElement( 'input' );
			input.type = 'text';
			input.className = 'components-input-control__input';
			input.value = css.value || '';
			fieldset.appendChild( input );
		}

		drawer.appendChild( row );
	}

	document.body.appendChild( drawer );
	return drawer;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'parseReactionClass', () => {
	it( 'returns an empty string for empty input', () => {
		expect( parseReactionClass( '' ) ).toBe( '' );
		expect( parseReactionClass( undefined ) ).toBe( '' );
	} );

	it( 'finds a reaction token among other classes', () => {
		expect( parseReactionClass( 'my-custom u-like-of another' ) ).toBe(
			'u-like-of'
		);
	} );

	it( 'ignores unrelated classes', () => {
		expect( parseReactionClass( 'u-like something-else' ) ).toBe( '' );
	} );

	it( 'handles irregular whitespace', () => {
		expect( parseReactionClass( '  u-repost-of   x ' ) ).toBe(
			'u-repost-of'
		);
	} );
} );

describe( 'buildClassValue', () => {
	it( 'adds a reaction when there are no classes', () => {
		expect( buildClassValue( '', 'u-in-reply-to' ) ).toBe(
			'u-in-reply-to'
		);
	} );

	it( 'preserves non-reaction classes when adding a reaction', () => {
		expect( buildClassValue( 'my-custom', 'u-like-of' ) ).toBe(
			'my-custom u-like-of'
		);
	} );

	it( 'swaps an existing reaction while keeping other classes', () => {
		expect(
			buildClassValue( 'my-custom u-in-reply-to', 'u-like-of' )
		).toBe( 'my-custom u-like-of' );
	} );

	it( 'removes the reaction when passed an empty value', () => {
		expect( buildClassValue( 'my-custom u-in-reply-to', '' ) ).toBe(
			'my-custom'
		);
	} );

	it( 'returns an empty string when removing the only class', () => {
		expect( buildClassValue( 'u-like-of', '' ) ).toBe( '' );
	} );

	it( 'strips every known reaction token before adding the new one', () => {
		expect(
			buildClassValue( 'u-like-of u-repost-of keep', 'u-tag-of' )
		).toBe( 'keep u-tag-of' );
	} );
} );

describe( 'getCssClassesRow', () => {
	it( 'finds the fieldset-based setting row', () => {
		const drawer = buildDrawer();
		const row = getCssClassesRow( drawer );
		expect( row ).not.toBeNull();
		expect( row.querySelector( 'fieldset' ) ).not.toBeNull();
	} );

	it( 'returns null when there is no CSS-classes setting', () => {
		const drawer = buildDrawer( null );
		expect( getCssClassesRow( drawer ) ).toBeNull();
	} );

	it( 'ignores the injected reaction row', () => {
		const drawer = buildDrawer( null );
		const reactionRow = document.createElement( 'div' );
		reactionRow.className =
			'block-editor-link-control__setting webmention-reaction-setting';
		reactionRow.appendChild( document.createElement( 'fieldset' ) );
		drawer.appendChild( reactionRow );
		expect( getCssClassesRow( drawer ) ).toBeNull();
	} );
} );

describe( 'getCurrentReaction', () => {
	it( 'reads the reaction from the active CSS-classes input', () => {
		const drawer = buildDrawer( { active: true, value: 'x u-like-of' } );
		expect( getCurrentReaction( drawer ) ).toBe( 'u-like-of' );
	} );

	it( 'returns an empty string when the value has no reaction', () => {
		const drawer = buildDrawer( { active: true, value: 'my-custom' } );
		expect( getCurrentReaction( drawer ) ).toBe( '' );
	} );

	it( 'returns an empty string when the setting is inactive', () => {
		const drawer = buildDrawer( { active: false } );
		expect( getCurrentReaction( drawer ) ).toBe( '' );
	} );
} );

describe( 'applyReaction', () => {
	it( 'writes the reaction into an active CSS-classes input, preserving classes', async () => {
		const drawer = buildDrawer( { active: true, value: 'my-custom' } );
		await applyReaction( 'u-like-of' );
		expect( getCssClassesInput( getCssClassesRow( drawer ) ).value ).toBe(
			'my-custom u-like-of'
		);
	} );

	it( 'swaps an existing reaction', async () => {
		const drawer = buildDrawer( { active: true, value: 'u-in-reply-to' } );
		await applyReaction( 'u-repost-of' );
		expect( getCssClassesInput( getCssClassesRow( drawer ) ).value ).toBe(
			'u-repost-of'
		);
	} );

	it( 'removes the reaction when set to None', async () => {
		const drawer = buildDrawer( {
			active: true,
			value: 'my-custom u-like-of',
		} );
		await applyReaction( '' );
		expect( getCssClassesInput( getCssClassesRow( drawer ) ).value ).toBe(
			'my-custom'
		);
	} );

	it( 'activates the setting on demand before writing', async () => {
		const drawer = buildDrawer( { active: false } );
		expect( getCssClassesInput( getCssClassesRow( drawer ) ) ).toBeNull();
		await applyReaction( 'u-tag-of' );
		expect( getCssClassesInput( getCssClassesRow( drawer ) ).value ).toBe(
			'u-tag-of'
		);
	} );

	it( 'does not activate the setting when choosing None on an inactive field', async () => {
		const drawer = buildDrawer( { active: false } );
		await applyReaction( '' );
		expect( getCssClassesInput( getCssClassesRow( drawer ) ) ).toBeNull();
	} );

	it( 'does nothing when no link popover is open', async () => {
		await expect( applyReaction( 'u-like-of' ) ).resolves.toBeUndefined();
	} );
} );

describe( 'createReactionDropdown', () => {
	it( 'renders every reaction type as an option', () => {
		const dropdown = createReactionDropdown( '' );
		const options = dropdown.querySelectorAll( 'option' );
		expect( options ).toHaveLength( REACTION_TYPES.length );
		expect( [ ...options ].map( ( o ) => o.value ) ).toEqual(
			REACTION_TYPES.map( ( t ) => t.value )
		);
	} );

	it( 'preselects the current reaction', () => {
		const dropdown = createReactionDropdown( 'u-bookmark-of' );
		expect( dropdown.querySelector( 'select' ).value ).toBe(
			'u-bookmark-of'
		);
	} );

	it( 'applies the reaction when the selection changes', async () => {
		const drawer = buildDrawer( { active: true, value: '' } );
		injectReactionDropdown( drawer );
		const select = drawer.querySelector(
			'.webmention-reaction-setting select'
		);
		select.value = 'u-like-of';
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		// Let the async applyReaction settle.
		await Promise.resolve();
		expect( getCssClassesInput( getCssClassesRow( drawer ) ).value ).toBe(
			'u-like-of'
		);
	} );
} );

describe( 'injectReactionDropdown', () => {
	it( 'injects the dropdown when a CSS-classes setting is present', () => {
		const drawer = buildDrawer( { active: true, value: 'u-like-of' } );
		injectReactionDropdown( drawer );
		const select = drawer.querySelector(
			'.webmention-reaction-setting select'
		);
		expect( select ).not.toBeNull();
		expect( select.value ).toBe( 'u-like-of' );
	} );

	it( 'does not inject when there is no CSS-classes setting', () => {
		const drawer = buildDrawer( null );
		injectReactionDropdown( drawer );
		expect(
			drawer.querySelector( '.webmention-reaction-setting' )
		).toBeNull();
	} );

	it( 'removes a stale dropdown when the CSS-classes setting disappears', () => {
		const withCss = buildDrawer( { active: true, value: '' } );
		injectReactionDropdown( withCss );
		expect(
			withCss.querySelector( '.webmention-reaction-setting' )
		).not.toBeNull();

		// Drop the CSS-classes row and re-run.
		getCssClassesRow( withCss ).remove();
		injectReactionDropdown( withCss );
		expect(
			withCss.querySelector( '.webmention-reaction-setting' )
		).toBeNull();
	} );

	it( 'syncs an existing dropdown to the current reaction', () => {
		const drawer = buildDrawer( { active: true, value: '' } );
		injectReactionDropdown( drawer );
		const input = getCssClassesInput( getCssClassesRow( drawer ) );
		input.value = 'u-repost-of';

		injectReactionDropdown( drawer );
		expect(
			drawer.querySelector( '.webmention-reaction-setting select' ).value
		).toBe( 'u-repost-of' );
	} );

	it( 'does not override the dropdown while the author has it focused', () => {
		const drawer = buildDrawer( { active: true, value: '' } );
		injectReactionDropdown( drawer );
		const select = drawer.querySelector(
			'.webmention-reaction-setting select'
		);
		select.value = 'u-like-of';
		select.focus();

		const input = getCssClassesInput( getCssClassesRow( drawer ) );
		input.value = 'u-tag-of';
		injectReactionDropdown( drawer );

		expect( select.value ).toBe( 'u-like-of' );
	} );
} );
