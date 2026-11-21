<?php
/**
 * Test Handler Meta class.
 *
 * @package Webmention
 */

/**
 * Test Handler Meta class.
 */
class Test_Handler_Meta extends WP_UnitTestCase {
	/**
	 * Test OGP parsing.
	 */
	public function test_ogp() {
		$response = new \Webmention\Response( 'http://example.com/webmention/target/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( WEBMENTION_TESTS_DIR . '/data/open-graph-test.html' ) );

		$handler = new \Webmention\Handler\Meta();

		$handler->parse( $response, 'http://example.com/webmention/target/placeholder' );

		$this->assertEquals( 'Hier &amp; Jetzt – Open Web Nr. 5', $handler->get_webmention_item()->get_name() );
		$this->assertEquals( "Was bedeutet die Annäherung von WordPress an Matrix? (Datei herunterladen) Marcel und ich sprechen nochmal über Automattic, WordPress und Matrix. Marcel ist übrigens nicht ganz so pessimistisch wie ich und kann dem Ganzen sogar etwas Positives abgewinnen. \u{201A}Hier &amp; Jetzt\u{2018} kann man per RSS-Feed abonnieren und findet man natürlich auch bei Apple Podcast und in [...]", $handler->get_webmention_item()->get_content() );
		$this->assertEquals( 'https://notiz.blog/2020/06/17/hier-und-jetzt-open-web-nr-5/', $handler->get_webmention_item()->get_url() );
	}

	/**
	 * The name coming from og:title must be sanitized like content and summary.
	 */
	public function test_name_is_sanitized() {
		$response = new \Webmention\Response( 'http://example.com/webmention/target/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( '<html><head><meta property="og:title" content="&lt;img src=x onerror=&quot;alert(1)&quot;&gt;Hello" /></head><body></body></html>' );

		$handler = new \Webmention\Handler\Meta();
		$handler->parse( $response, 'http://example.com/webmention/target/placeholder' );

		$name = $handler->get_webmention_item()->get_name();
		$this->assertStringNotContainsString( 'onerror', $name );
		$this->assertStringContainsString( 'Hello', $name );
	}

	/**
	 * Parse a page with all handlers.
	 *
	 * @param string $head Markup for the head of the page.
	 * @param string $body Optional. Markup for the body of the page.
	 *
	 * @return \Webmention\Entity\Item The parsed item.
	 */
	private function parse_page( $head, $body = '' ) {
		$response = new \Webmention\Response( 'https://www.example.org/blog/a-post/' );
		$response->set_content_type( 'text/html' );
		$response->set_body( sprintf( '<html><head><title>A post</title>%s</head><body>%s</body></html>', $head, $body ) );

		$handler = new \Webmention\Handler();

		return $handler->parse_aggregated( $response, 'http://example.com/webmention/target/placeholder' );
	}

	/**
	 * Icon markup and the icon that has to be picked.
	 *
	 * @return array[]
	 */
	public function data_icons() {
		return array(
			'no icon'                  => array( '', '' ),
			'plain icon'               => array( '<link rel="icon" href="https://www.example.org/icon.png">', 'https://www.example.org/icon.png' ),
			'shortcut icon'            => array( '<link rel="shortcut icon" href="https://www.example.org/favicon.ico">', 'https://www.example.org/favicon.ico' ),
			'relative icon'            => array( '<link rel="icon" href="/icon.png">', 'https://www.example.org/icon.png' ),
			'largest icon'             => array(
				'<link rel="icon" sizes="32x32" href="/32.png"><link rel="icon" sizes="192x192" href="/192.png"><link rel="icon" href="/plain.png">',
				'https://www.example.org/192.png',
			),
			'apple touch icon first'   => array(
				'<link rel="icon" sizes="512x512" href="/512.png"><link rel="apple-touch-icon" href="/apple.png">',
				'https://www.example.org/apple.png',
			),
			'largest apple touch icon' => array(
				'<link rel="apple-touch-icon" sizes="120x120" href="/120.png"><link rel="apple-touch-icon" sizes="180x180" href="/180.png">',
				'https://www.example.org/180.png',
			),
			'mask icon is skipped'     => array( '<link rel="mask-icon" href="/mask.svg">', '' ),
			'unsafe scheme is skipped' => array( '<link rel="icon" href="javascript:alert(1)">', '' ),
		);
	}

	/**
	 * A page without an author is its own author: named after the site, linked to the page, with the icon as photo.
	 *
	 * @dataProvider data_icons
	 *
	 * @param string $head     Icon markup.
	 * @param string $expected The expected icon URL.
	 */
	public function test_site_is_the_author_of_a_page_without_author( $head, $expected ) {
		$item = $this->parse_page( $head );

		$this->assertSame( 'example.org', $item->get_author( 'name' ) );
		$this->assertSame( 'https://www.example.org/blog/a-post/', $item->get_author( 'url' ) );
		$this->assertSame( $expected, $item->get_author( 'photo' ) );
	}

	/**
	 * A microformats entry without an author is still a page without an author.
	 */
	public function test_site_is_the_author_of_an_entry_without_author() {
		$item = $this->parse_page(
			'<link rel="icon" href="/icon.png">',
			'<div class="h-entry"><a class="u-mention-of" href="http://example.com/webmention/target/placeholder">a link</a></div>'
		);

		$this->assertSame( 'example.org', $item->get_author( 'name' ) );
		$this->assertSame( 'https://www.example.org/blog/a-post/', $item->get_author( 'url' ) );
		$this->assertSame( 'https://www.example.org/icon.png', $item->get_author( 'photo' ) );
	}

	/**
	 * The fallback also applies when the other handlers already found everything but the author.
	 */
	public function test_fallback_applies_to_an_otherwise_complete_item() {
		$item = $this->parse_page(
			'<link rel="icon" href="/icon.png">',
			'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"A post","image":"https://www.example.org/image.png","isPartOf":{"name":"The Example"}}</script>'
			. '<div class="h-entry"><time class="dt-published" datetime="2026-01-01T10:00:00+00:00"></time><time class="dt-updated" datetime="2026-01-02T10:00:00+00:00"></time>'
			. '<div class="e-content">Some text with <a class="u-mention-of" href="http://example.com/webmention/target/placeholder">a link</a>.</div></div>'
		);

		$this->assertSame( 'The Example', $item->get_author( 'name' ) );
		$this->assertSame( 'https://www.example.org/blog/a-post/', $item->get_author( 'url' ) );
		$this->assertSame( 'https://www.example.org/icon.png', $item->get_author( 'photo' ) );
	}

	/**
	 * The site name is preferred over the domain.
	 */
	public function test_site_name_is_the_author_name() {
		$item = $this->parse_page( '<meta property="og:site_name" content="The Example">' );

		$this->assertSame( 'The Example', $item->get_author( 'name' ) );
	}

	/**
	 * A named author is linked to the page, but does not get the icon of the site.
	 */
	public function test_named_author_gets_url_but_not_the_icon() {
		$item = $this->parse_page( '<meta name="author" content="Jane"><link rel="icon" href="/icon.png">' );

		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
		$this->assertSame( 'https://www.example.org/blog/a-post/', $item->get_author( 'url' ) );
		$this->assertSame( '', $item->get_author( 'photo' ) );
	}

	/**
	 * The fallback must not take the place of an author that a later handler finds.
	 */
	public function test_fallback_keeps_author_of_later_handler() {
		$item = $this->parse_page(
			'<link rel="icon" href="/icon.png">',
			'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"A post","author":{"@type":"Person","name":"Jane","url":"https://jane.example/"}}</script>'
		);

		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
		$this->assertSame( 'https://jane.example/', $item->get_author( 'url' ) );
		$this->assertSame( '', $item->get_author( 'photo' ) );
	}

	/**
	 * A description is the summary of a page, not its content, so a page with a short description stays a mention.
	 */
	public function test_description_is_the_summary() {
		$item = $this->parse_page( '<meta name="description" content="A short description.">' );

		$this->assertSame( 'A short description.', $item->get_summary() );
		$this->assertSame( 'A short description.', $item->get_content() );
		$this->assertSame( 'mention', $item->get_response_type() );
	}

	/**
	 * The Dublin Core description is read.
	 */
	public function test_dublin_core_description() {
		$item = $this->parse_page( '<meta name="DC.Description" content="A short description.">' );

		$this->assertSame( 'A short description.', $item->get_summary() );
	}
}
