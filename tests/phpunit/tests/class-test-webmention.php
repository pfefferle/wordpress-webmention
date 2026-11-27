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
	 * Clean up the enqueued stylesheet.
	 */
	public function tear_down() {
		wp_dequeue_style( 'webmention' );

		parent::tear_down();
	}

	/**
	 * Test that the stylesheet is registered, but not enqueued on a plain page view.
	 */
	public function test_styles_are_not_enqueued_without_markup() {
		$post_id = self::factory()->post->create();

		// Other tests may have rendered markup that enqueued it.
		wp_dequeue_style( 'webmention' );

		// Older WordPress versions enqueue the styles of all registered blocks on classic themes.
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		$this->go_to( get_permalink( $post_id ) );
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'webmention', 'registered' ) );
		$this->assertFalse( wp_style_is( 'webmention', 'enqueued' ) );
	}

	/**
	 * Test that the form enqueues the stylesheet.
	 */
	public function test_form_enqueues_styles() {
		$post_id = self::factory()->post->create();

		$this->assertNotEmpty( get_webmention_form( array( 'post' => $post_id ) ) );
		$this->assertTrue( wp_style_is( 'webmention', 'enqueued' ) );
	}

	/**
	 * Test that the reaction list enqueues the stylesheet.
	 */
	public function test_reaction_list_enqueues_styles() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'like',
			)
		);

		$output = wp_list_comments(
			array(
				'avatar_only' => true,
				'echo'        => false,
				'walker'      => new \Webmention\Comment_Walker(),
			),
			array( get_comment( $comment_id ) )
		);

		$this->assertStringContainsString( 'avatar-only', $output );
		$this->assertTrue( wp_style_is( 'webmention', 'enqueued' ) );
	}

	/**
	 * Test that post content with an RSVP enqueues the stylesheet.
	 */
	public function test_rsvp_content_enqueues_styles() {
		apply_filters( 'the_content', '<p>No RSVP here.</p>' );
		$this->assertFalse( wp_style_is( 'webmention', 'enqueued' ) );

		apply_filters( 'the_content', '<p><data class="p-rsvp" value="yes">Yes</data></p>' );
		$this->assertTrue( wp_style_is( 'webmention', 'enqueued' ) );
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

	/**
	 * The test suite must not run as a local environment, otherwise the
	 * development tools in `local/` (which disable SSRF protection) are loaded.
	 */
	public function test_local_tools_are_not_loaded() {
		$this->assertSame( 'production', \wp_get_environment_type() );
		$this->assertFalse( \function_exists( 'Webmention\Development\allow_unsafe_http_request_urls' ) );
	}
}
