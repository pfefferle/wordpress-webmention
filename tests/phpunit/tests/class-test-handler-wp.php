<?php
/**
 * Test Handler WP class.
 *
 * @package Webmention
 */

/**
 * Test Handler WP class.
 */
class Test_Handler_Wp extends WP_UnitTestCase {
	/**
	 * Test WP API discovery.
	 */
	public function test_discovery() {
		$response = new \Webmention\Response( 'http://example.com/webmention/target/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( WEBMENTION_TESTS_DIR . '/data/wp-api-discovery-test.html' ) );

		$site_api_links = $response->get_links_by( array( 'rel' => 'https://api.w.org/' ) );

		$this->assertEquals( 'https://notiz.blog/wp-api/', $site_api_links[0]['uri'] );

		$post_api_links = $response->get_links_by(
			array(
				'rel'  => 'alternate',
				'type' => 'application/json',
			)
		);

		$this->assertEquals( 'https://notiz.blog/wp-api/wp/v2/posts/5307', $post_api_links[0]['uri'] );
	}

	/**
	 * The post is read with a single request to the API, and its dates are taken as GMT.
	 */
	public function test_parse() {
		$requests = array();

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$requests ) {
				$requests[] = $url;

				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'body'     => wp_json_encode(
						array(
							'link'         => 'https://notiz.blog/2013/07/08/die-renaissance-des-pingbacks/',
							'title'        => array( 'rendered' => 'A post' ),
							'date'         => '2013-07-08T12:00:00',
							'date_gmt'     => '2013-07-08T10:00:00',
							'modified'     => '2013-07-09T12:00:00',
							'modified_gmt' => '2013-07-09T10:00:00',
							'_embedded'    => array(
								'author' => array(
									array(
										'name' => 'Jane',
										'link' => 'https://notiz.blog/author/jane/',
									),
								),
							),
						)
					),
				);
			},
			10,
			3
		);

		$response = new \Webmention\Response( 'https://notiz.blog/2013/07/08/die-renaissance-des-pingbacks/' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( WEBMENTION_TESTS_DIR . '/data/wp-api-discovery-test.html' ) );

		$handler = new \Webmention\Handler\WP();

		$this->assertTrue( $handler->parse( $response, 'http://example.com/webmention/target/placeholder' ) );

		$item = $handler->get_webmention_item();

		$this->assertSame( array( 'https://notiz.blog/wp-api/wp/v2/posts/5307?_embed=1' ), $requests );
		$this->assertSame( 'A post', $item->get_name() );
		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
		$this->assertSame( '2013-07-08T10:00:00+00:00', $item->get_published()->format( DATE_ATOM ) );
		$this->assertSame( '2013-07-09T10:00:00+00:00', $item->get_updated()->format( DATE_ATOM ) );
	}

	/**
	 * An API response with unexpected types does not break the parsing.
	 */
	public function test_unexpected_types() {
		$response = new \Webmention\Response( 'https://notiz.blog/wp-api/wp/v2/posts/5307' );
		$response->set_body( '{"title":"A post","excerpt":"An excerpt","date_gmt":["2013"],"modified_gmt":"not a date","_embedded":{"author":[{"name":"Jane","avatar_urls":"none"}]}}' );

		$handler = new \Webmention\Handler\WP();
		$item    = $handler->get_webmention_item();

		foreach ( array_merge( $handler->parse_post_json( $response ), $handler->parse_author_json( $response ) ) as $key => $value ) {
			if ( '_' !== $key[0] ) {
				$item->add( $key, $value );
			}
		}

		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
		$this->assertEmpty( $item->get_name() );
		$this->assertInstanceOf( 'DateTimeImmutable', $item->get_published() );
	}
}
