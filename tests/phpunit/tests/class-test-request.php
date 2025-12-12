<?php
/**
 * Test Request class.
 *
 * @package Webmention
 */

/**
 * Test Request class.
 */
class Test_Request extends WP_UnitTestCase {
	/**
	 * Get mock response.
	 *
	 * @param int    $code     Response code.
	 * @param string $response Response message.
	 * @return array Response array.
	 */
	public function response( $code = 200, $response = 'OK' ) {
		$response = array(
			'code'    => $code,
			'message' => $response,
		);
		return $response;
	}

	/**
	 * Get mock HTTP return.
	 *
	 * @param array  $headers  Headers.
	 * @param array  $response Response.
	 * @param string $body     Body.
	 * @return array HTTP return array.
	 */
	public function httpreturn( $headers, $response, $body ) {
		return compact( 'headers', 'response', 'body' );
	}

	/**
	 * Mock return 404 error.
	 *
	 * @return array HTTP return.
	 */
	public function return_404_error() {
		$headers  = '';
		$response = $this->response( 404, 'Not Found' );
		$body     = '';
		return $this->httpreturn( $headers, $response, $body );
	}

	/**
	 * Test error handling.
	 */
	public function test_error_handling() {
		$url = 'http://www.example.com/test/1';

		add_filter( 'pre_http_request', array( $this, 'return_404_error' ) );
		$error = \Webmention\Request::get( 'https://notiz.blog/3042193a120381239a' );
		$this->assertInstanceOf( 'WP_Error', $error );
	}
}
