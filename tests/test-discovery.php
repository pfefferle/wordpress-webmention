<?php
class Discovery_Test extends WP_UnitTestCase {

	public function headers( $link = "") {
		$headers = array(
			'server' => 'nginx/1.9.15',
			'date' => 'Mon, 16 May 2016 01:21:08 GMT',
			'content-type' => 'text/html; charset=UTF-8',
			'link' => $link,
		);
		return $headers;
	}

	public function response( $code = 200, $response = "OK" ) {
		$response = array(
			'code' => $code,
			'response' => $response,
		);
		return $response;
	}

	public function httpreturn( $headers, $response, $body ) {
		return compact( 'headers', 'response', 'body' );
	}

	public function discover_relative_httplink() {
		$link = '</test/1/webmention>; rel=webmention';
		$headers = $this->headers( $link );
		$response = $this->response( 200, 'OK' );
		$body = '<html></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_relative_httplink() {
		$url = 'http://www.example.com/test/1';

		add_filter( 'pre_http_request', array( $this, 'discover_relative_httplink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/1/webmention', $endpoint );
	}

	public function discover_absolute_httplink() {
		$link = '<http://www.example.com/test/2/webmention>; rel=webmention';
		$headers = $this->headers( $link );
		$response = $this->response( 200, 'OK' );
		$body = '<html></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_absolute_httplink() {
		$url = 'http://www.example.com/test/2';

		add_filter( 'pre_http_request', array( $this, 'discover_absolute_httplink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/2/webmention', $endpoint );
	}

	public function discover_quoted_httplink() {
		$link = '<http://www.example.com/test/8/webmention>; rel="webmention"';
		$headers = $this->headers( $link );
		$response = $this->response( 200, 'OK' );
		$body = '<html></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_quoted_httplink() {
		$url = 'http://www.example.com/test/2';

		add_filter( 'pre_http_request', array( $this, 'discover_quoted_httplink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/8/webmention', $endpoint );
	}

	public function discover_multiple_httplink() {
		$link = '<http://www.example.com/test/10/webmention>; rel="webmention somethingelse"';
		$headers = $this->headers( $link );
		$response = $this->response( 200, 'OK' );
		$body = '<html></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_multiple_httplink() {
		$url = 'http://www.example.com/test/10';

		add_filter( 'pre_http_request', array( $this, 'discover_multiple_httplink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/10/webmention', $endpoint );
	}

	public function discover_casesensitive_httplink() {
		$link = '<http://www.example.com/test/7/webmention>; rel=webmention';
		$headers = $this->headers( $link );
		$headers['Link'] = $headers['link'];
		unset( $headers['link'] );
		$response = $this->response( 200, 'OK' );
		$body = '<html></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_casesensitive_httplink() {
		$url = 'http://www.example.com/test/7';

		add_filter( 'pre_http_request', array( $this, 'discover_casesensitive_httplink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertFalse( $endpoint );
	}

	public function discover_relative_htmllink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html>\r\n<html lang="en"><head><link rel="webmention" href="/test/3/webmention"></head><body>This is a test.</body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_relative_htmllink() {
		$url = 'http://www.example.com/test/3';

		add_filter( 'pre_http_request', array( $this, 'discover_relative_htmllink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/3/webmention', $endpoint );
	}

	public function discover_absolute_htmllink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head><link rel="webmention" href="http://www.example.com/test/4/webmention"></head><body>This is a test</body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_absolute_htmllink() {
		$url = 'http://www.example.com/test/4';

		add_filter( 'pre_http_request', array( $this, 'discover_absolute_htmllink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/4/webmention', $endpoint );
	}

	public function discover_multiple_htmllink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head><link rel="webmention somethingelse" href="http://www.example.com/test/9/webmention"></head><body>This is a test</body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_multiple_htmllink() {
		$url = 'http://www.example.com/test/9';

		add_filter( 'pre_http_request', array( $this, 'discover_multiple_htmllink' ) );
		$endpoint = webmention_discover_endpoint( $url );

		$this->assertSame( 'http://www.example.com/test/9/webmention', $endpoint );
	}

	public function discover_notwebmention_htmllink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head><link rel="not-webmention" href="http://www.example.com/test/12/webmention"></head><body>This is a testThere is also a <a href="/test/121/webmention" rel="webmention"></body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_notwebmention_htmllink() {
		$url = 'http://www.example.com/test/12';

		add_filter( 'pre_http_request', array( $this, 'discover_notwebmention_htmllink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/121/webmention', $endpoint );
	}

	public function discover_empty_htmllink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head><link rel="webmention" href=""></head><body>This is a test</body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_empty_htmllink() {
		$url = 'http://www.example.com/test/15';

		add_filter( 'pre_http_request', array( $this, 'discover_empty_htmllink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/15', $endpoint );
	}

	public function discover_relative_hreflink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head></head><body><a rel="webmention" href="/test/5/webmention">Webmention</a></body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_relative_hreflink() {
		$url = 'http://www.example.com/test/5';

		add_filter( 'pre_http_request', array( $this, 'discover_relative_hreflink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/5/webmention', $endpoint );
	}

	public function discover_absolute_hreflink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head></head><body><a rel="webmention" href="http://www.example.com/test/6/webmention">Webmention</a></body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_absolute_hreflink() {
		$url = 'http://www.example.com/test/6';

		add_filter( 'pre_http_request', array( $this, 'discover_absolute_hreflink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/6/webmention', $endpoint );
	}

	public function discover_comment_hreflink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head></head><body><!-- <a rel="webmention" href="http://www.example.com/test/13/webmention">Webmention</a> --><a rel="webmention" href="http://www.example.com/test/131/webmention">Webmention</a></body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_comment_hreflink() {
		$url = 'http://www.example.com/test/13';

		add_filter( 'pre_http_request', array( $this, 'discover_comment_hreflink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/131/webmention', $endpoint );
	}

	public function discover_escaped_hreflink() {
		$headers = $this->headers( '' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head></head><body><code>&lt;a rel="webmention" href="http://www.example.com/test/14/webmention">Webmention&gt;&lt;/a&gt;<a rel="webmention" href="http://www.example.com/test/141/webmention">Webmention</a></body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_escaped_hreflink() {
		$url = 'http://www.example.com/test/14';

		add_filter( 'pre_http_request', array( $this, 'discover_escaped_hreflink' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/141/webmention', $endpoint );
	}

	public function discover_multiple_link() {
		$headers = $this->headers( '<http://www.example.com/test/11/webmention>; rel=webmention' );
		$response = $this->response( 200, 'OK' );
		$body = '<!DOCTYPE html><html lang="en"><head><link rel="webmention" href="http://www.example.com/test/4/webmention"></head><body><a rel="webmention" href="http://www.example.com/test/6/webmention">Webmention</a></body></html>';
		return $this->httpreturn( $headers, $response, $body );
	}

	public function test_discover_multiple_link() {
		$url = 'http://www.example.com/test/11';

		add_filter( 'pre_http_request', array( $this, 'discover_multiple_link' ) );
		$endpoint = webmention_discover_endpoint( $url );
		$this->assertSame( 'http://www.example.com/test/11/webmention', $endpoint );
	}

	public function test_webmention_extract_urls() {
		$urls = webmention_extract_urls( '<main><div><a href="https://example.org">Test</a></div><a href="https://example.com">Test</a></main>' );

		$this->assertEquals(
			$urls,
			array(
				'https://example.org',
				'https://example.com'
			)
		);
	}
}
