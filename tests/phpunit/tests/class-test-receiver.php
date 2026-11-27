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
	 * @param string $body    Optional. The HTML document.
	 *
	 * @return array The mocked `wp_remote_get()` return value.
	 */
	private function http_response( $code, $message, $body = '' ) {
		return array(
			'headers'  => array( 'content-type' => 'text/html; charset=utf-8' ),
			'response' => array(
				'code'    => $code,
				'message' => $message,
			),
			'body'     => $body,
		);
	}

	/**
	 * Make every outgoing HTTP request return the given status code.
	 *
	 * @param int    $code    HTTP status code.
	 * @param string $message HTTP status message.
	 * @param string $body    Optional. The HTML document.
	 */
	private function mock_http_status( $code, $message, $body = '' ) {
		add_filter(
			'pre_http_request',
			function () use ( $code, $message, $body ) {
				return $this->http_response( $code, $message, $body );
			}
		);
	}

	/**
	 * Create a Webmention comment for the test post.
	 *
	 * @param array $args Optional. Comment data to add or override.
	 *
	 * @return int The comment ID.
	 */
	private function create_webmention_comment( $args = array() ) {
		return self::factory()->comment->create(
			array_replace_recursive(
				array(
					'comment_post_ID' => $this->post->ID,
					'comment_type'    => WEBMENTION_COMMENT_TYPE,
					'comment_meta'    => array(
						'protocol'              => 'webmention',
						'webmention_source_url' => self::SOURCE,
					),
				),
				$args
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

	/**
	 * Build a source page that links to the test post.
	 *
	 * @param string $author Optional. Author markup to add to the page.
	 *
	 * @return string The HTML document.
	 */
	private function source_page( $author = '' ) {
		return sprintf(
			'<html><head><title>A page</title></head><body><div class="h-entry">%s<a class="u-mention-of" href="%s">a link</a></div></body></html>',
			$author,
			get_permalink( $this->post )
		);
	}

	/**
	 * The comment data the receiver builds before the source is parsed.
	 *
	 * @return array The comment data.
	 */
	private function commentdata() {
		return array(
			'source'             => self::SOURCE,
			'target'             => get_permalink( $this->post ),
			'comment_author_url' => self::SOURCE,
		);
	}

	/**
	 * A source without author data uses the source URL as author URL.
	 */
	public function test_source_without_author_uses_source_url() {
		$this->mock_http_status( 200, 'OK', $this->source_page() );

		$commentdata = Receiver::default_commentdata( $this->commentdata() );

		$this->assertSame( self::SOURCE, $commentdata['comment_author_url'] );
	}

	/**
	 * An author URL on the source page replaces the source URL.
	 */
	public function test_source_author_url_replaces_source_url() {
		$this->mock_http_status(
			200,
			'OK',
			$this->source_page( '<a class="p-author h-card" href="https://example.org/author/">Jane</a>' )
		);

		$commentdata = Receiver::default_commentdata( $this->commentdata() );

		$this->assertSame( 'https://example.org/author/', $commentdata['comment_author_url'] );
	}

	/**
	 * Refreshing a Webmention whose source has no author data does not empty the author URL.
	 */
	public function test_refresh_keeps_author_url() {
		$comment_id = $this->create_webmention_comment(
			array(
				'comment_author_url' => self::SOURCE,
				'comment_meta'       => array( 'webmention_target_url' => get_permalink( $this->post ) ),
			)
		);

		$this->mock_http_status( 200, 'OK', $this->source_page() );

		webmention_refresh( $comment_id );

		$this->assertSame( self::SOURCE, get_comment( $comment_id )->comment_author_url );
	}
}
