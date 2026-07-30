import { WEBMENTION_FIELDS, getFieldValue, setFieldValue } from '../fields';

describe( 'WEBMENTION_FIELDS', () => {
	it( 'defines the incoming and outgoing toggles', () => {
		expect( WEBMENTION_FIELDS.map( ( field ) => field.metaKey ) ).toEqual( [
			'webmentions_disabled',
			'webmentions_disabled_pings',
		] );
	} );

	it( 'gives every field a label and help text', () => {
		WEBMENTION_FIELDS.forEach( ( field ) => {
			expect( field.label ).toBeTruthy();
			expect( field.help ).toBeTruthy();
		} );
	} );
} );

describe( 'getFieldValue', () => {
	it( 'returns false when the meta or key is unset', () => {
		expect( getFieldValue( {}, 'webmentions_disabled' ) ).toBe( false );
		expect( getFieldValue( undefined, 'webmentions_disabled' ) ).toBe(
			false
		);
	} );

	it( 'coerces truthy meta values to true', () => {
		expect(
			getFieldValue(
				{ webmentions_disabled: true },
				'webmentions_disabled'
			)
		).toBe( true );
		expect(
			getFieldValue( { webmentions_disabled: 1 }, 'webmentions_disabled' )
		).toBe( true );
	} );

	it( 'returns false for falsy meta values', () => {
		expect(
			getFieldValue(
				{ webmentions_disabled: false },
				'webmentions_disabled'
			)
		).toBe( false );
	} );
} );

describe( 'setFieldValue', () => {
	it( 'sets the value without mutating the original meta', () => {
		const meta = { existing: 'x' };
		const next = setFieldValue( meta, 'webmentions_disabled', true );

		expect( next ).toEqual( {
			existing: 'x',
			webmentions_disabled: true,
		} );
		expect( meta ).toEqual( { existing: 'x' } );
	} );

	it( 'preserves the other fields when toggling one', () => {
		const meta = {
			webmentions_disabled: true,
			webmentions_disabled_pings: false,
		};
		const next = setFieldValue( meta, 'webmentions_disabled_pings', true );

		expect( next ).toEqual( {
			webmentions_disabled: true,
			webmentions_disabled_pings: true,
		} );
	} );
} );
