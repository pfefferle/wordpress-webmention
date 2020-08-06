<?php
class Webmention_Handler_MF2_Test extends WP_UnitTestCase {
	/**
	 * @dataProvider template_provider
	 */
	public function test_mf2( $path ) {
		require_once( dirname( __FILE__ ) . '/../includes/handlers/class-webmention-handler-base.php' );
		require_once( dirname( __FILE__ ) . '/../includes/handlers/class-webmention-handler-mf2.php' );
		require_once( dirname( __FILE__ ) . '/../includes/entities/class-webmention-item.php' );

		$request = new Webmention_Request();
		$request->set_content_type( 'text/html' );
		$request->set_url( 'http://example.com/webmention/target/placeholder' );
		$request->set_body( file_get_contents( $path ) );

		$handler = new Webmention_Handler_Mf2();

		$handler->parse( $request );

		$subset = json_decode( file_get_contents( substr( $path, 0, -4 ) . 'json' ), true );

		$this->assertArraySubset( $subset, $handler->get_webmention_item()->to_array() );
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
