<?php
class Request_Test extends WP_UnitTestCase {
	public function response( $code = 200, $response = "OK" ) {
		$response = array(
			'code' => $code,
			'message' => $response,
		);
		return $response;
	}

	public function httpreturn( $headers, $response, $body ) {
		return compact( 'headers', 'response', 'body' );
	}

	public function return_404_error() {
		$headers = '';
		$response = $this->response( 404, 'Not Found' );
		$body = '';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_error_handling() {
		$url = 'http://www.example.com/test/1';

		add_filter( 'pre_http_request', array( $this, 'return_404_error' ) );
		$error = \Webmention\Request::get( 'https://notiz.blog/3042193a120381239a' );
		$this->assertInstanceOf( 'WP_Error', $error );
	}
}