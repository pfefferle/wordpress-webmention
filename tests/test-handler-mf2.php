<?php

use DMS\PHPUnitExtensions\ArraySubset\Assert;

class Webmention_Handler_MF2_Test extends WP_UnitTestCase {
	/**
	 * @dataProvider template_provider
	 */
	public function test_mf2( $path ) {
		require_once( dirname( __FILE__ ) . '/../includes/Handler/class-base.php' );
		require_once( dirname( __FILE__ ) . '/../includes/Handler/class-mf2.php' );
		require_once( dirname( __FILE__ ) . '/../includes/Entity/class-item.php' );
		require_once( dirname( __FILE__ ) . '/../includes/class-response.php' );

		$response = new \Webmention\Response( 'http://example.com/webmention/source/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( $path ) );

		$handler = new \Webmention\Handler\Mf2();

		$handler->parse( $response, 'http://example.com/webmention/target/placeholder' );

		$subset = json_decode( file_get_contents( substr( $path, 0, -4 ) . 'json' ), true );

		Assert::assertArraySubset( $subset, $handler->get_webmention_item()->to_array() );
	}

	public function template_provider() {
		return array_map(
			function( $path ) {
				return array( $path );
			},
			glob( dirname( __FILE__ ) . '/data/mf2/*.html' )
		);
	}
}
