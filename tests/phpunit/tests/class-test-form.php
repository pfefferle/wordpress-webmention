<?php
/**
 * Test the Webmention form.
 *
 * @package Webmention
 */

/**
 * Test form class.
 */
class Test_Form extends WP_UnitTestCase {
	/**
	 * Test that a missing form block does not disable the classic fallback.
	 */
	public function test_block_theme_falls_back_when_form_block_is_missing() {
		if ( ! site_supports_blocks() || ! function_exists( 'get_hooked_blocks' ) ) {
			$this->markTestSkipped( 'Block Hooks are not supported.' );
		}

		$stylesheet = get_stylesheet();
		register_theme_directory( DIR_TESTDATA . '/themedir1' );
		switch_theme( 'block-theme' );
		update_option( 'webmention_show_comment_form', 1 );
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( 'webmention/form' );

		try {
			$this->assertTrue( webmention_use_block_hooks() );
			$registry->unregister( 'webmention/form' );
			$this->assertFalse( webmention_use_block_hooks() );

			$post_id = self::factory()->post->create();
			$this->go_to( get_permalink( $post_id ) );
			the_post();

			ob_start();
			webmention_comment_form();
			$this->assertStringContainsString( 'name="source"', ob_get_clean() );
		} finally {
			$registry->register( $block_type );
			switch_theme( $stylesheet );
			delete_option( 'webmention_show_comment_form' );
		}
	}

	/**
	 * Test actual hook insertion and rendering with a block theme and a disabled legacy option.
	 */
	public function test_block_theme_inserts_form_with_legacy_option_disabled() {
		if ( ! site_supports_blocks() || ! function_exists( 'apply_block_hooks_to_content' ) ) {
			$this->markTestSkipped( 'Block Hooks content insertion is not supported.' );
		}

		$stylesheet = get_stylesheet();
		register_theme_directory( DIR_TESTDATA . '/themedir1' );
		switch_theme( 'block-theme' );
		update_option( 'webmention_show_comment_form', 0 );

		try {
			$this->assertTrue( webmention_use_block_hooks() );

			$post_id = self::factory()->post->create();
			$this->go_to( get_permalink( $post_id ) );
			the_post();

			$markup = '<!-- wp:comments --><div class="wp-block-comments"><!-- wp:post-comments-form /--></div><!-- /wp:comments -->';
			$hooked = apply_block_hooks_to_content( $markup );
			$this->assertStringContainsString( '<!-- wp:webmention/form /-->', $hooked );

			$html = do_blocks( $hooked );
			$this->assertSame( 1, substr_count( $html, 'name="source"' ) );
			$this->assertStringContainsString( 'value="' . esc_url( get_permalink( $post_id ) ) . '"', $html );

			ob_start();
			\Webmention\WP_Admin\Settings_Fields::render_comment_settings_field();
			$settings = ob_get_clean();
			$this->assertStringNotContainsString( 'name="webmention_show_comment_form"', $settings );
			$this->assertStringContainsString( 'name="webmention_comment_form_text"', $settings );
		} finally {
			switch_theme( $stylesheet );
			delete_option( 'webmention_show_comment_form' );
		}
	}


	/**
	 * Test that the form targets the given post.
	 */
	public function test_form_targets_post() {
		$post_id = self::factory()->post->create();

		$form = get_webmention_form( array( 'post' => $post_id ) );

		$this->assertStringContainsString( 'class="webmention-form"', $form );
		$this->assertStringContainsString( 'name="source"', $form );
		$this->assertStringContainsString( 'value="' . esc_url( get_permalink( $post_id ) ) . '"', $form );
		$this->assertStringContainsString( '<details class="webmention-form__help">', $form );
	}

	/**
	 * Test that no form is rendered if Webmentions are disabled for the post.
	 */
	public function test_form_empty_if_webmentions_closed() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'webmentions_disabled', '1' );

		$this->assertSame( '', get_webmention_form( array( 'post' => $post_id ) ) );
	}

	/**
	 * Test that multiple forms on one page get unique IDs.
	 */
	public function test_forms_have_unique_ids() {
		$post_id = self::factory()->post->create();

		$first  = get_webmention_form( array( 'post' => $post_id ) );
		$second = get_webmention_form( array( 'post' => $post_id ) );

		preg_match( '/<input id="([^"]+)" class="webmention-source"/', $first, $first_id );
		preg_match( '/<input id="([^"]+)" class="webmention-source"/', $second, $second_id );

		$this->assertNotSame( $first_id[1], $second_id[1] );
		$this->assertStringContainsString( 'aria-describedby="' . str_replace( 'source', 'source-description', $second_id[1] ) . '"', $second );
	}

	/**
	 * Test that custom wrapper attributes replace the default class.
	 */
	public function test_form_wrapper_attributes() {
		$post_id = self::factory()->post->create();

		$form = get_webmention_form(
			array(
				'post'               => $post_id,
				'wrapper_attributes' => 'class="webmention-form wp-block-webmention-form"',
			)
		);

		$this->assertStringContainsString( 'class="webmention-form wp-block-webmention-form"', $form );
	}

	/**
	 * Test that a form with `once` is only rendered once per post.
	 */
	public function test_form_once_per_post() {
		$post_id = self::factory()->post->create();

		$first  = get_webmention_form( array( 'post' => $post_id ) );
		$second = get_webmention_form(
			array(
				'post' => $post_id,
				'once' => true,
			)
		);

		$this->assertStringContainsString( 'webmention-form', $first );
		$this->assertSame( '', $second );
	}

	/**
	 * Test that the classic form is skipped if the post content already rendered the block.
	 */
	public function test_classic_form_skipped_after_block() {
		if ( ! site_supports_blocks() ) {
			$this->markTestSkipped( 'Blocks are not supported.' );
		}

		$post_id = self::factory()->post->create( array( 'post_content' => '<!-- wp:webmention/form /-->' ) );
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$content = apply_filters( 'the_content', get_the_content() );

		ob_start();
		webmention_comment_form();

		$this->assertStringContainsString( 'wp-block-webmention-form', $content );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Test that the classic form is rendered on classic themes.
	 */
	public function test_classic_form_rendered() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		webmention_comment_form();
		$this->assertStringContainsString( 'webmention-form', ob_get_clean() );
	}

	/**
	 * Test that the checkbox still disables the classic form.
	 */
	public function test_classic_form_respects_option() {
		update_option( 'webmention_show_comment_form', 0 );
		add_filter( 'webmention_use_block_hooks', '__return_false' );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		webmention_comment_form();
		$output = ob_get_clean();

		remove_filter( 'webmention_use_block_hooks', '__return_false' );
		delete_option( 'webmention_show_comment_form' );

		$this->assertSame( '', $output );
	}

	/**
	 * Test that the classic form is skipped if the block is added through Block Hooks.
	 */
	public function test_classic_form_skipped_with_block_hooks() {
		add_filter( 'webmention_use_block_hooks', '__return_true' );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		webmention_comment_form();
		$output = ob_get_clean();

		remove_filter( 'webmention_use_block_hooks', '__return_true' );

		$this->assertSame( '', $output );
	}

	/**
	 * Test that the block is hooked into block themes regardless of the legacy option.
	 */
	public function test_hooked_block_types() {
		$hooked = array( 'core/paragraph', 'webmention/form' );

		add_filter( 'webmention_use_block_hooks', '__return_true' );
		$this->assertSame( $hooked, \Webmention\Block::hooked_block_types( $hooked ) );

		update_option( 'webmention_show_comment_form', 0 );
		$this->assertSame( $hooked, \Webmention\Block::hooked_block_types( $hooked ) );

		delete_option( 'webmention_show_comment_form' );
		remove_filter( 'webmention_use_block_hooks', '__return_true' );
		$this->assertSame( array( 'core/paragraph' ), \Webmention\Block::hooked_block_types( $hooked ) );
	}

	/**
	 * Test that Block Hooks are not used without block support, e.g. on ClassicPress.
	 */
	public function test_no_block_hooks_without_block_support() {
		add_filter( 'webmention_site_supports_blocks', '__return_false' );
		$use_block_hooks = webmention_use_block_hooks();
		remove_filter( 'webmention_site_supports_blocks', '__return_false' );

		$this->assertFalse( $use_block_hooks );
	}
}
