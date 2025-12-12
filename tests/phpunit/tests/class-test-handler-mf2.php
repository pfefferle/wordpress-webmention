<?php
/**
 * Test Handler MF2 class.
 *
 * @package Webmention
 */

use DMS\PHPUnitExtensions\ArraySubset\Assert;

/**
 * Test Handler MF2 class.
 */
class Test_Handler_Mf2 extends WP_UnitTestCase {
	/**
	 * Test MF2 parsing.
	 *
	 * @dataProvider template_provider
	 *
	 * @param string $path Path to test file.
	 */
	public function test_mf2( $path ) {
		$response = new \Webmention\Response( 'http://example.com/webmention/source/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( $path ) );

		$handler = new \Webmention\Handler\Mf2();

		$handler->parse( $response, 'http://example.com/webmention/target/placeholder' );

		$subset = json_decode( file_get_contents( substr( $path, 0, -4 ) . 'json' ), true );

		Assert::assertArraySubset( $subset, $handler->get_webmention_item()->to_array() );
	}

	/**
	 * Data provider for test files.
	 *
	 * @return array Array of test file paths.
	 */
	public function template_provider() {
		return array_map(
			function ( $path ) {
				return array( $path );
			},
			glob( WEBMENTION_TESTS_DIR . '/data/mf2/*.html' )
		);
	}
}
