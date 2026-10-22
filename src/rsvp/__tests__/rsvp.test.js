import { getActiveFormat } from '@wordpress/rich-text';
import {
	FORMAT_NAME,
	RSVP_VALUES,
	getCurrentRsvpValue,
	getRsvpButtonTitle,
} from '../rsvp';

jest.mock( '@wordpress/rich-text', () => ( {
	getActiveFormat: jest.fn(),
} ) );

afterEach( () => {
	jest.clearAllMocks();
} );

describe( 'RSVP_VALUES', () => {
	it( 'defines the four microformats RSVP responses', () => {
		expect( RSVP_VALUES.map( ( v ) => v.value ) ).toEqual( [
			'yes',
			'no',
			'maybe',
			'interested',
		] );
	} );

	it( 'gives every response a label and an icon', () => {
		RSVP_VALUES.forEach( ( rsvp ) => {
			expect( rsvp.label ).toBeTruthy();
			expect( rsvp.icon ).toBeTruthy();
		} );
	} );
} );

describe( 'getCurrentRsvpValue', () => {
	it( 'reads the value from the active format attributes', () => {
		getActiveFormat.mockReturnValue( { attributes: { value: 'yes' } } );
		expect( getCurrentRsvpValue( {} ) ).toBe( 'yes' );
		expect( getActiveFormat ).toHaveBeenCalledWith( {}, FORMAT_NAME );
	} );

	it( 'falls back to unregistered attributes', () => {
		getActiveFormat.mockReturnValue( {
			unregisteredAttributes: { value: 'maybe' },
		} );
		expect( getCurrentRsvpValue( {} ) ).toBe( 'maybe' );
	} );

	it( 'prefers registered attributes over unregistered ones', () => {
		getActiveFormat.mockReturnValue( {
			attributes: { value: 'no' },
			unregisteredAttributes: { value: 'yes' },
		} );
		expect( getCurrentRsvpValue( {} ) ).toBe( 'no' );
	} );

	it( 'returns an empty string when the format is not active', () => {
		getActiveFormat.mockReturnValue( undefined );
		expect( getCurrentRsvpValue( {} ) ).toBe( '' );
	} );

	it( 'returns an empty string when the format has no value', () => {
		getActiveFormat.mockReturnValue( { attributes: {} } );
		expect( getCurrentRsvpValue( {} ) ).toBe( '' );
	} );
} );

describe( 'getRsvpButtonTitle', () => {
	it( 'includes the response label when a value is set', () => {
		expect( getRsvpButtonTitle( 'yes' ) ).toBe( 'RSVP: Yes' );
		expect( getRsvpButtonTitle( 'interested' ) ).toBe( 'RSVP: Interested' );
	} );

	it( 'falls back to a plain title with no value', () => {
		expect( getRsvpButtonTitle( '' ) ).toBe( 'RSVP' );
	} );

	it( 'falls back to a plain title for an unknown value', () => {
		expect( getRsvpButtonTitle( 'bogus' ) ).toBe( 'RSVP' );
	} );
} );
