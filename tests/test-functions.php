<?php
class Functions_Test extends WP_UnitTestCase {

	/**
	 * Test that span tags inside anchor tags are removed.
	 */
	public function test_sanitize_html_removes_spans_in_anchors() {
		$input = '<a href="https://example.com"><span>https://</span><span>example.com</span></a>';

		$result = webmention_sanitize_html( $input );

		// Spans should be removed.
		$this->assertStringNotContainsString( '<span>', $result );
		$this->assertStringContainsString( '<a href="https://example.com">', $result );
	}

	/**
	 * Test that span tags outside anchor tags are preserved.
	 */
	public function test_sanitize_html_preserves_spans_outside_anchors() {
		$input    = '<p><span>Hello</span> World</p>';
		$expected = '<p><span>Hello</span> World</p>';

		$result = webmention_sanitize_html( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test Mastodon-style content with spans breaking up URLs.
	 */
	public function test_sanitize_html_mastodon_style_content() {
		$input = '<p><span><a href="https://mastodon.social/@Edent" rel="nofollow ugc">@<span>Edent</span></a></span> <a href="https://www.example.com/path/to/page.html" rel="nofollow ugc"><span>https://www.</span><span>example.com/path/to/</span><span>page.html</span></a> some text.</p>';

		$result = webmention_sanitize_html( $input );

		// Spans inside anchors should be removed.
		$this->assertStringContainsString( '>@Edent</a>', $result );
		// Spans outside anchors should remain.
		$this->assertStringContainsString( '<span><a href="https://mastodon.social/@Edent"', $result );
	}

	/**
	 * Test that anchor tags without URL text are unchanged.
	 */
	public function test_sanitize_html_anchors_with_regular_text_unchanged() {
		$input    = '<a href="https://example.com">Example Link</a>';
		$expected = '<a href="https://example.com">Example Link</a>';

		$result = webmention_sanitize_html( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test nested spans in anchor tags.
	 */
	public function test_sanitize_html_nested_spans_in_anchors() {
		$input  = '<a href="https://example.com"><span><span>Nested</span> Text</span></a>';
		$result = webmention_sanitize_html( $input );

		$this->assertStringContainsString( '>Nested Text</a>', $result );
		$this->assertStringNotContainsString( '<span>', $result );
	}

	/**
	 * Test HTML comments are stripped.
	 */
	public function test_sanitize_html_strips_comments() {
		$input    = '<p>Hello <!-- comment --> World</p>';
		$expected = '<p>Hello  World</p>';

		$result = webmention_sanitize_html( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test that non-string input is returned unchanged.
	 */
	public function test_sanitize_html_non_string_input() {
		$this->assertNull( webmention_sanitize_html( null ) );
		$this->assertEquals( 123, webmention_sanitize_html( 123 ) );
		$this->assertEquals( array( 'test' ), webmention_sanitize_html( array( 'test' ) ) );
	}

	/**
	 * Test that make_clickable doesn't double-link URLs that are already in anchor tags.
	 *
	 * This test verifies whether WordPress's make_clickable() function causes
	 * double-linking issues when anchor text contains a URL.
	 */
	public function test_make_clickable_does_not_double_link() {
		// URL text inside an anchor tag.
		$input = '<a href="https://example.com">https://example.com</a>';

		// Apply make_clickable (which WordPress applies to comment text).
		$result = make_clickable( $input );

		// Check if make_clickable created nested anchor tags (double-linking).
		$nested_anchor_count = substr_count( $result, '<a ' );

		$this->assertEquals( 1, $nested_anchor_count, 'make_clickable should not create nested anchor tags. Result: ' . $result );
	}

	/**
	 * Test make_clickable behavior with URL text in anchor after sanitization.
	 */
	public function test_sanitized_content_with_make_clickable() {
		// Mastodon-style input with spans.
		$input = '<a href="https://example.com"><span>https://</span><span>example.com</span></a>';

		// First sanitize (removes spans).
		$sanitized = webmention_sanitize_html( $input );

		// Then apply make_clickable (as WordPress does for comments).
		$result = make_clickable( $sanitized );

		// Count anchor tags - should only be 1.
		$anchor_count = substr_count( $result, '<a ' );

		$this->assertEquals( 1, $anchor_count, 'Should not have nested anchors after make_clickable. Sanitized: ' . $sanitized . ' | Result: ' . $result );
	}

	/**
	 * Test make_clickable with plain URL (not in anchor).
	 */
	public function test_make_clickable_links_plain_urls() {
		$input = 'Check out https://example.com for more info.';

		$result = make_clickable( $input );

		// Plain URL should be linked.
		$this->assertStringContainsString( '<a href="https://example.com"', $result );
	}
}
