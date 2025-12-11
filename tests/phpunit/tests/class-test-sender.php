<?php
/**
 * Test Sender class.
 *
 * @package Webmention
 */

use Webmention\Sender;

/**
 * Test Sender class.
 */
class Test_Sender extends WP_UnitTestCase {
	/**
	 * Test post.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		// Create a test post.
		$this->post = self::factory()->post->create_and_get(
			array(
				'post_content' => 'Test post with a link to <a href="https://example.com">Example</a>',
			)
		);
	}

	/**
	 * Test send webmentions.
	 */
	public function test_send_webmentions() {
		// Mock webmention endpoint discovery.
		add_filter(
			'webmention_server_url',
			function ( $url, $target ) {
				return 'https://example.com/webmention';
			},
			10,
			2
		);

		// Mock HTTP request.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => 'Webmention received',
				);
			},
			10,
			3
		);

		$result = Sender::send_webmentions( $this->post->ID );

		// Check if webmention was sent.
		$this->assertIsArray( $result );
		$this->assertContains( 'https://example.com', $result );

		// Check if URLs were saved to post meta.
		$mentioned_urls = get_post_meta( $this->post->ID, '_webmentioned', true );
		$this->assertIsArray( $mentioned_urls );
		$this->assertContains( 'https://example.com', $mentioned_urls );
	}

	/**
	 * Test update ping.
	 */
	public function test_update_ping() {
		$pinged = array(
			'https://example1.com',
			'https://example2.com',
		);

		$result = Sender::update_ping( $this->post->ID, $pinged );

		// Check if pings were updated.
		$this->assertIsString( $result );
		$this->assertEquals( implode( "\n", $pinged ), $result );

		// Check database directly.
		$updated_post = get_post( $this->post->ID );
		$this->assertEquals( $result, $updated_post->pinged );
	}

	/**
	 * Test update ping with invalid post.
	 */
	public function test_update_ping_invalid_post() {
		$result = Sender::update_ping( 999999, array( 'https://example.com' ) );
		$this->assertFalse( $result );
	}

	/**
	 * Test update ping with invalid pinged.
	 */
	public function test_update_ping_invalid_pinged() {
		$result = Sender::update_ping( $this->post->ID, 'not an array' );
		$this->assertFalse( $result );
	}

	/**
	 * Test send webmentions with error response.
	 */
	public function test_send_webmentions_with_error_response() {
		// Mock webmention endpoint discovery.
		add_filter(
			'webmention_server_url',
			function ( $url, $target ) {
				return 'https://example.com/webmention';
			},
			10,
			2
		);

		// Mock HTTP request with 500 error.
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				return array(
					'response' => array(
						'code'    => 500,
						'message' => 'Internal Server Error',
					),
					'body'     => 'Server Error',
					'headers'  => array(),
					'cookies'  => array(),
				);
			},
			10,
			3
		);

		$result = Sender::send_webmentions( $this->post->ID );

		// Check if retry was scheduled.
		$this->assertTrue( metadata_exists( 'post', $this->post->ID, '_mentionme' ) );
		$this->assertEquals( '1', get_post_meta( $this->post->ID, '_mentionme_tries', true ) );
	}

	/**
	 * Tear down test.
	 */
	public function tear_down() {
		parent::tear_down();
		remove_all_filters( 'webmention_server_url' );
		remove_all_filters( 'pre_http_request' );
	}
}
