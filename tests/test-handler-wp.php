<?php
class WP_Handler_Meta_Test extends WP_UnitTestCase {
	public function test_discovery() {
		require_once( dirname( __FILE__ ) . '/../includes/Handler/class-base.php' );
		require_once( dirname( __FILE__ ) . '/../includes/Handler/class-wp.php' );
		require_once( dirname( __FILE__ ) . '/../includes/Entity/class-item.php' );
		require_once( dirname( __FILE__ ) . '/../includes/class-response.php' );

		$response = new \Webmention\Response( 'http://example.com/webmention/target/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( dirname( __FILE__ ) . '/data/wp-api-discovery-test.html' ) );

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