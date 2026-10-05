<?php
/**
 * Test Response class.
 *
 * @package Webmention
 */

/**
 * Test HTML response encoding.
 */
class Test_Response extends WP_UnitTestCase {

	/**
	 * Preserve text and attributes without emitting encoding warnings.
	 *
	 * @dataProvider html_encoding_provider
	 *
	 * @param string $body     HTML response body.
	 * @param string $expected Expected paragraph text.
	 */
	public function test_get_dom_document_encoding( $body, $expected ) {
		if ( ! function_exists( 'mb_encode_numericentity' ) ) {
			$this->markTestSkipped( 'The mbstring extension is required.' );
		}

		$detect_order         = mb_detect_order();
		$substitute_character = mb_substitute_character();
		$errors               = array();

		mb_detect_order( array( 'ASCII', 'UTF-8' ) );
		mb_substitute_character( 0x3F );
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Assert that parsing emits no encoding diagnostics.
			static function ( $severity, $message ) use ( &$errors ) {
				$errors[] = $message;
				return true;
			},
			E_WARNING | E_NOTICE | E_DEPRECATED
		);

		try {
			$response = new \Webmention\Response(
				'https://example.org/source',
				array(
					'headers'  => array( 'content-type' => 'text/html; charset=UTF-8' ),
					'body'     => $body,
					'response' => array( 'code' => 200 ),
				)
			);
			$document = $response->get_dom_document();

			$this->assertInstanceOf( DOMDocument::class, $document );
			$this->assertSame( $expected, $document->getElementsByTagName( 'p' )->item( 0 )->textContent );
			$this->assertSame(
				'https://example.org/café/中文/😀?a=1&b=2',
				$document->getElementsByTagName( 'a' )->item( 0 )->getAttribute( 'href' )
			);
			$this->assertSame( array(), $errors, 'Parsing must not emit PHP warnings, notices, or deprecations.' );
		} finally {
			restore_error_handler();
			mb_detect_order( $detect_order );
			mb_substitute_character( $substitute_character );
		}
	}

	/**
	 * HTML bodies with valid and malformed character encodings.
	 *
	 * @return array Test cases.
	 */
	public static function html_encoding_provider() {
		$link = '<a href="https://example.org/café/中文/😀?a=1&amp;b=2">Link</a>';

		return array(
			'unicode'           => array(
				'<p>Café 中文 😀</p>' . $link,
				'Café 中文 😀',
			),
			'existing entities' => array(
				'<p>&amp; &eacute; &#20013; &#x1F600;</p>' . $link,
				'& é 中 😀',
			),
			'invalid byte'      => array(
				"<p>Invalid \xFF byte</p>" . $link,
				'Invalid ? byte',
			),
			'truncated UTF-8'   => array(
				"<p>Truncated \xF0\x9F</p>" . $link,
				'Truncated ?',
			),
		);
	}
}
