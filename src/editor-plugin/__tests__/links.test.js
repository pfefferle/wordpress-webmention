import { hasOnlyPlainLinks } from '../links';

describe( 'hasOnlyPlainLinks', () => {
	it( 'returns true when the content has only plain links', () => {
		expect(
			hasOnlyPlainLinks(
				'<p><a href="https://example.org" class="custom">a</a> <a href="https://example.com">b</a></p>'
			)
		).toBe( true );
	} );

	it( 'returns false when the content has no links', () => {
		expect( hasOnlyPlainLinks( '<p>No links here.</p>' ) ).toBe( false );
		expect( hasOnlyPlainLinks( '' ) ).toBe( false );
	} );

	it( 'returns false when a link has a reaction class', () => {
		expect(
			hasOnlyPlainLinks(
				'<a href="https://example.org">a</a><a href="https://example.com" class="u-like-of">b</a>'
			)
		).toBe( false );
	} );

	it( 'returns false when a link has the nomention class', () => {
		expect(
			hasOnlyPlainLinks(
				'<a href="https://example.org">a</a><a href="https://example.com" class="foo nomention">b</a>'
			)
		).toBe( false );
	} );

	it( 'returns false when a link has rel-nomention', () => {
		expect(
			hasOnlyPlainLinks(
				'<a href="https://example.org">a</a><a href="https://example.com" rel="nofollow NoMention">b</a>'
			)
		).toBe( false );
	} );

	it( 'does not match partial class names', () => {
		expect(
			hasOnlyPlainLinks(
				'<a href="https://example.org" class="nomentions u-like">a</a>'
			)
		).toBe( true );
	} );
} );
