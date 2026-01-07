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
}
