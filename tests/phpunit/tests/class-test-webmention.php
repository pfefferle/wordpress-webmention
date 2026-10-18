<?php
/**
 * Test Webmention class.
 *
 * @package Webmention
 */

/**
 * Test Webmention class.
 */
class Test_Webmention extends WP_UnitTestCase {
	/**
	 * Test that post content keeps its styles on singular, home, and archive views.
	 */
	public function test_content_styles_are_enqueued() {
		$category_id = self::factory()->category->create();
		$post_id     = self::factory()->post->create( array( 'post_category' => array( $category_id ) ) );
		$urls        = array(
			get_permalink( $post_id ),
			home_url( '/?post_type=post' ),
			get_category_link( $category_id ),
		);

		try {
			foreach ( $urls as $url ) {
				wp_dequeue_style( 'webmention' );
				$this->go_to( $url );
				\Webmention\Webmention::get_instance()->enqueue_scripts();
				$this->assertTrue( wp_style_is( 'webmention', 'enqueued' ), $url );
			}
		} finally {
			wp_dequeue_style( 'webmention' );
		}
	}

	/**
	 * Test that constants are registered.
	 */
	public function test_register_constants() {
		\Webmention\Webmention::get_instance()->init();

		$this->assertEquals( WEBMENTION_ALWAYS_SHOW_HEADERS, 0 );
		$this->assertEquals( WEBMENTION_COMMENT_APPROVE, 0 );
		$this->assertEquals( WEBMENTION_COMMENT_TYPE, 'webmention' );
		$this->assertEquals( WEBMENTION_GRAVATAR_CACHE_TIME, WEEK_IN_SECONDS );
	}

	/**
	 * Test that hooks are registered.
	 */
	public function test_register_hooks() {
		\Webmention\Webmention::get_instance()->init();

		// Test if some hooks are registered.
		$this->assertEquals( has_action( 'init', array( \Webmention\Comment::class, 'init' ) ), 10 );
		$this->assertEquals( has_action( 'admin_menu', array( \Webmention\WP_Admin\Admin::class, 'admin_menu' ) ), 10 );
		$this->assertEquals( has_action( 'admin_init', array( \Webmention\WP_Admin\Admin::class, 'admin_init' ) ), 10 );
	}
}
