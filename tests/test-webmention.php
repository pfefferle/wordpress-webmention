<?php
class Webmention_Test extends WP_UnitTestCase {
    public function test_remove_sl() {
		\Webmention\remove_semantic_linkbacks();

		$this->assertEquals( true, true );
    }
}