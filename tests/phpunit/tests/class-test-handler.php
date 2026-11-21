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
	 *
	 * @return \Webmention\Response The response.
	 */
	private function page( $body ) {
		$response = new \Webmention\Response( 'https://www.example.org/blog/a-post/' );
		$response->set_content_type( 'text/html' );
		$response->set_body( sprintf( '<html><head><title>A post</title></head><body>%s</body></html>', $body ) );

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
}
