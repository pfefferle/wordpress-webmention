<?php
class Webmention_Handler_MF2_Test extends WP_UnitTestCase {
	public function test_mf2() {
		require_once( dirname( __FILE__ ) . '/../includes/handlers/class-webmention-handler-base.php' );
		require_once( dirname( __FILE__ ) . '/../includes/handlers/class-webmention-handler-mf2.php' );
		require_once( dirname( __FILE__ ) . '/../includes/entities/class-webmention-item.php' );

		$handler = new Webmention_Handler_MF2();
		
		$mf_array = array();

		$handler->add_properties( $mf_array );
		$test_item = new Webmention_Item();
		$this->webmention_item_test( $test_item, $handler->get_webmention_item() );
	}

	public function webmention_item_test( $item, $test_item ) {
		$this->assertEquals( $test_item->get_name(), $item->get_name() );
		$this->assertEquals( $test_item->get_content(), $item->get_content() );
		$this->assertEquals( $test_item->get_url(), $item->get_url() );
	}
}
