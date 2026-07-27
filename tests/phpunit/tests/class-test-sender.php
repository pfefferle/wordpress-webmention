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
	 * Test that unchanged content does not re-send Webmentions.
	 *
	 * Regression test for repeated Webmentions (issue #619): once a target has
	 * been successfully notified, re-running the sender for the same, unchanged
	 * post must not send the Webmention again.
	 */
	public function test_send_webmentions_does_not_resend_unchanged_content() {
		$request_count = 0;

		add_filter(
			'webmention_server_url',
			function () {
				return 'https://example.com/webmention';
			}
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$request_count ) {
				// Only count the actual Webmention POST, not endpoint discovery.
				if ( 'https://example.com/webmention' === $url ) {
					++$request_count;
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => 'Webmention received',
				);
			},
			10,
			3
		);

		// First run should send the Webmention.
		Sender::send_webmentions( $this->post->ID );
		$this->assertSame( 1, $request_count, 'First run should send one Webmention.' );

		// Second run with unchanged content must not send it again.
		Sender::send_webmentions( $this->post->ID );
		$this->assertSame( 1, $request_count, 'Unchanged content must not re-send the Webmention.' );
	}

	/**
	 * Test that changed content re-sends Webmentions to the new targets.
	 */
	public function test_send_webmentions_resends_when_content_changes() {
		$requested_targets = array();

		add_filter(
			'webmention_server_url',
			function () {
				return 'https://example.com/webmention';
			}
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$requested_targets ) {
				// Only record the actual Webmention POST, not endpoint discovery.
				if ( 'https://example.com/webmention' === $url && ! empty( $args['body'] ) ) {
					parse_str( $args['body'], $parsed );
					if ( isset( $parsed['target'] ) ) {
						$requested_targets[] = rawurldecode( $parsed['target'] );
					}
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => 'Webmention received',
				);
			},
			10,
			3
		);

		// First run notifies the original link.
		Sender::send_webmentions( $this->post->ID );
		$this->assertSame( array( 'https://example.com' ), $requested_targets );

		// Add a new link to the post content.
		wp_update_post(
			array(
				'ID'           => $this->post->ID,
				'post_content' => 'Test post with links to <a href="https://example.com">Example</a> and <a href="https://example.org">Example Org</a>',
			)
		);
		$requested_targets = array();

		Sender::send_webmentions( $this->post->ID );
		$this->assertContains( 'https://example.org', $requested_targets, 'A newly added link must be notified.' );
	}

	/**
	 * Test that a target which fails during a content-change re-send is retried.
	 *
	 * A previously-notified target that returns HTTP 5xx while the post content is
	 * being re-sent must not be recorded as notified, so the rescheduled run still
	 * retries it instead of treating it as already delivered.
	 */
	public function test_send_webmentions_retries_target_that_fails_during_content_change() {
		$response_code = 200;

		add_filter(
			'webmention_server_url',
			function () {
				return 'https://example.com/webmention';
			}
		);

		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$response_code ) {
				// Endpoint discovery must always succeed; only the POST reflects the code.
				if ( 'https://example.com/webmention' === $url ) {
					return array(
						'response' => array( 'code' => $response_code ),
						'body'     => 'Webmention received',
					);
				}
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => 'Webmention received',
				);
			},
			10,
			3
		);

		// First run succeeds, so the target is recorded as notified.
		Sender::send_webmentions( $this->post->ID );
		$this->assertContains( 'https://example.com', get_post_meta( $this->post->ID, '_webmentioned', true ) );

		// Change the content so the sender re-notifies every target, but the re-send fails.
		wp_update_post(
			array(
				'ID'           => $this->post->ID,
				'post_content' => 'Updated body linking to <a href="https://example.com">Example</a>',
			)
		);
		$response_code = 500;

		Sender::send_webmentions( $this->post->ID );

		$mentioned = get_post_meta( $this->post->ID, '_webmentioned', true );
		$mentioned = empty( $mentioned ) ? array() : $mentioned;
		$this->assertNotContains( 'https://example.com', $mentioned, 'A target that fails during a content-change re-send must not be marked as notified.' );
	}

	/**
	 * Test that the post `pinged` column is populated after sending.
	 *
	 * Regression test for the dead "restore update punged" block that referenced
	 * an undefined `$ping` variable and therefore never updated `pinged`.
	 */
	public function test_send_webmentions_updates_pinged() {
		add_filter(
			'webmention_server_url',
			function () {
				return 'https://example.com/webmention';
			}
		);

		add_filter(
			'pre_http_request',
			function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => 'Webmention received',
				);
			}
		);

		Sender::send_webmentions( $this->post->ID );

		$pinged = get_pung( $this->post->ID );
		$this->assertContains( 'https://example.com', $pinged, 'Successfully sent targets must be recorded in `pinged`.' );
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
