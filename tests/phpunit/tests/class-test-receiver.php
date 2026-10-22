<?php
/**
 * Test Receiver class.
 *
 * @package Webmention
 */

use Webmention\Receiver;
use Webmention\Response;

/**
 * Test Receiver class.
 */
class Test_Receiver extends WP_UnitTestCase {
	/**
	 * Source URL of the incoming Webmention.
	 *
	 * @var string
	 */
	const SOURCE = 'https://example.org/deleted-post/';

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

		$this->post = self::factory()->post->create_and_get();
	}

	/**
	 * Build a mocked HTTP response.
	 *
	 * @param int    $code    HTTP status code.
	 * @param string $message HTTP status message.
	 *
	 * @return array The mocked `wp_remote_get()` return value.
	 */
	private function http_response( $code, $message ) {
		return array(
			'headers'  => array(),
			'response' => array(
				'code'    => $code,
				'message' => $message,
			),
			'body'     => '',
		);
	}

	/**
	 * Make every outgoing HTTP request return the given status code.
	 *
	 * @param int    $code    HTTP status code.
	 * @param string $message HTTP status message.
	 */
	private function mock_http_status( $code, $message ) {
		add_filter(
			'pre_http_request',
			function () use ( $code, $message ) {
				return $this->http_response( $code, $message );
			}
		);
	}

	/**
	 * Create a Webmention comment for the test post.
	 *
	 * @return int The comment ID.
	 */
	private function create_webmention_comment() {
		return self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post->ID,
				'comment_type'    => WEBMENTION_COMMENT_TYPE,
				'comment_meta'    => array(
					'protocol'              => 'webmention',
					'webmention_source_url' => self::SOURCE,
				),
			)
		);
	}

	/**
	 * Send a Webmention for the test post.
	 *
	 * @return WP_REST_Response|WP_Error The result of the request.
	 */
	private function receive_webmention() {
		$request = new WP_REST_Request( 'POST', '/webmention/1.0/endpoint' );
		$request->set_param( 'source', self::SOURCE );
		$request->set_param( 'target', get_permalink( $this->post ) );

		return Receiver::post( $request );
	}

	/**
	 * HTTP status codes that signal a removed source and the error codes they map to.
	 *
	 * @return array[]
	 */
	public function data_gone_status_codes() {
		return array(
			'not found'             => array( 404, 'Not Found', 'resource_not_found' ),
			'gone'                  => array( 410, 'Gone', 'resource_deleted' ),
			'unavailable for legal' => array( 451, 'Unavailable For Legal Reasons', 'resource_removed' ),
		);
	}

	/**
	 * A removed source has to produce an error code `Receiver::delete()` acts on.
	 *
	 * @dataProvider data_gone_status_codes
	 *
	 * @param int    $status   HTTP status code.
	 * @param string $message  HTTP status message.
	 * @param string $expected Expected `WP_Error` code.
	 */
	public function test_get_error_maps_status_to_error_code( $status, $message, $expected ) {
		$response = new Response( self::SOURCE, $this->http_response( $status, $message ) );
		$error    = $response->get_error();

		$this->assertInstanceOf( 'WP_Error', $error );
		$this->assertSame( $expected, $error->get_error_code() );
		$this->assertSame( $status, $error->get_error_data()['status'] );
	}

	/**
	 * Other HTTP errors stay generic.
	 */
	public function test_get_error_keeps_generic_code_for_other_errors() {
		$response = new Response( self::SOURCE, $this->http_response( 500, 'Internal Server Error' ) );

		$this->assertSame( 'http_error', $response->get_error()->get_error_code() );
	}

	/**
	 * A Webmention for a source that is gone removes the stored comment.
	 *
	 * @dataProvider data_gone_status_codes
	 *
	 * @param int    $status  HTTP status code.
	 * @param string $message HTTP status message.
	 */
	public function test_gone_source_deletes_comment( $status, $message ) {
		$comment_id = $this->create_webmention_comment();

		$this->mock_http_status( $status, $message );

		$this->receive_webmention();

		$comment = get_comment( $comment_id );

		$this->assertTrue(
			null === $comment || 'trash' === $comment->comment_approved,
			'The comment should have been deleted or trashed.'
		);
	}

	/**
	 * A source that is only temporarily broken keeps the stored comment.
	 */
	public function test_server_error_keeps_comment() {
		$comment_id = $this->create_webmention_comment();

		$this->mock_http_status( 500, 'Internal Server Error' );

		$this->receive_webmention();

		$comment = get_comment( $comment_id );

		$this->assertNotNull( $comment );
		$this->assertNotSame( 'trash', $comment->comment_approved );
	}
}
