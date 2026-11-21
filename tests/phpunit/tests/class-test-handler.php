<?php
/**
 * Test Handler class.
 *
 * @package Webmention
 */

/**
 * Test Handler class.
 */
class Test_Handler extends WP_UnitTestCase {
	/**
	 * Build the response of a page.
	 *
	 * @param string $body Markup for the body of the page.
	 * @param string $head Optional. Markup for the head of the page.
	 *
	 * @return \Webmention\Response The response.
	 */
	private function page( $body, $head = '' ) {
		$response = new \Webmention\Response( 'https://www.example.org/blog/a-post/' );
		$response->set_content_type( 'text/html' );
		$response->set_body( sprintf( '<html><head><title>A post</title>%s</head><body>%s</body></html>', $head, $body ) );

		return $response;
	}

	/**
	 * A handler that is added to the front runs first.
	 */
	public function test_unshift() {
		$first = new class() extends \Webmention\Handler\Base {
			/**
			 * Name the item.
			 *
			 * @param \Webmention\Response $response   Response Object.
			 * @param string               $target_url The target URL.
			 *
			 * @return true
			 */
			public function parse( \Webmention\Response $response, $target_url ) {
				$this->webmention_item->add_name( 'First' );

				return true;
			}
		};

		$handler = new \Webmention\Handler();
		$handler->unshift( $first );

		$item = $handler->parse_aggregated( $this->page( '' ), 'http://example.com/webmention/target/placeholder' );

		$this->assertSame( 'First', $item->get_name() );
	}

	/**
	 * A microformats entry without an author leaves the author to the handlers that follow.
	 */
	public function test_entry_without_author_leaves_the_author_to_later_handlers() {
		$handler = new \Webmention\Handler();
		$item    = $handler->parse_aggregated(
			$this->page(
				'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","author":{"@type":"Person","name":"Jane","url":"https://jane.example/"}}</script>'
				. '<div class="h-entry"><a class="u-mention-of" href="http://example.com/webmention/target/placeholder">a link</a></div>'
			),
			'http://example.com/webmention/target/placeholder'
		);

		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
		$this->assertSame( 'https://jane.example/', $item->get_author( 'url' ) );
	}

	/**
	 * Build a microformats entry that links to the target.
	 *
	 * @param string $author Author markup.
	 *
	 * @return string The markup.
	 */
	private function entry( $author ) {
		return '<div class="h-entry">' . $author . '<time class="dt-published" datetime="2026-01-01T10:00:00+00:00"></time>'
			. '<div class="e-content">Some text with <a class="u-in-reply-to" href="http://example.com/webmention/target/placeholder">a link</a>.</div></div>';
	}

	/**
	 * The handlers that follow are skipped as soon as the item has all a comment needs.
	 */
	public function test_complete_item_stops_the_handlers() {
		$requests = array();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$requests ) {
				$requests[] = $url;

				return new WP_Error( 'unexpected_request' );
			},
			10,
			3
		);

		$handler = new \Webmention\Handler();
		$item    = $handler->parse_aggregated(
			$this->page(
				$this->entry( '<a class="p-author h-card" href="https://jane.example/">Jane</a>' ),
				'<link rel="https://api.w.org/" href="https://www.example.org/wp-json/" /><link rel="alternate" type="application/json" href="https://www.example.org/wp-json/wp/v2/posts/1" />'
				. '<meta property="og:site_name" content="The Example">'
			),
			'http://example.com/webmention/target/placeholder'
		);

		$this->assertTrue( $item->is_complete() );
		$this->assertSame( array(), $requests );
		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
		$this->assertSame( 'https://jane.example/', $item->get_author( 'url' ) );
		$this->assertSame( 'comment', $item->get_response_type() );
	}

	/**
	 * An item whose author has no URL is not complete, so the handlers that follow still run.
	 */
	public function test_item_without_author_url_is_not_complete() {
		$handler = new \Webmention\Handler();
		$item    = $handler->parse_aggregated(
			$this->page( $this->entry( '<span class="p-author h-card">Jane</span>' ) ),
			'http://example.com/webmention/target/placeholder'
		);

		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
		$this->assertSame( 'https://www.example.org/blog/a-post/', $item->get_author( 'url' ) );
		$this->assertSame( 'A post', $item->get_name() );
	}

	/**
	 * An item without an author is not complete.
	 */
	public function test_item_without_author_is_not_complete() {
		$item = new \Webmention\Entity\Item();
		$item->set_published( '2026-01-01T10:00:00+00:00' );
		$item->set_url( 'https://www.example.org/blog/a-post/' );
		$item->set_response_type( 'mention' );
		$item->set_summary( 'A summary.' );

		$this->assertFalse( $item->is_complete() );

		$item->set_author(
			array(
				'type' => 'card',
				'name' => 'Jane',
				'url'  => 'https://jane.example/',
			)
		);

		$this->assertTrue( $item->is_complete() );
	}
}
