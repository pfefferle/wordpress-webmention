<?php
class Webmention_Test extends WP_UnitTestCase {
	public function test_register_constants() {
		\Webmention\Webmention::get_instance()->init();

		$this->assertEquals( WEBMENTION_ALWAYS_SHOW_HEADERS, 0 );
		$this->assertEquals( WEBMENTION_COMMENT_APPROVE, 0 );
		$this->assertEquals( WEBMENTION_COMMENT_TYPE, 'webmention' );
		$this->assertEquals( WEBMENTION_GRAVATAR_CACHE_TIME, WEEK_IN_SECONDS );
	}

	public function test_register_hooks() {
		\Webmention\Webmention::get_instance()->init();

		// test if some hooks are registered
		$this->assertEquals( has_action( 'init', array( \Webmention\Comment::class, 'init' ) ), 10 );
		$this->assertEquals( has_action( 'admin_menu', array( \Webmention\Admin::class, 'admin_menu' ) ), 10 );
		$this->assertEquals( has_action( 'admin_init', array( \Webmention\Admin::class, 'admin_init' ) ), 10 );
	}

}
