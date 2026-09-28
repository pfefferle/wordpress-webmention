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
	 * Test that the block is only hooked into block themes with the form enabled.
	 */
	public function test_hooked_block_types() {
		$hooked = array( 'core/paragraph', 'webmention/form' );

		add_filter( 'webmention_use_block_hooks', '__return_true' );
		$this->assertSame( $hooked, \Webmention\Block::hooked_block_types( $hooked ) );

		update_option( 'webmention_show_comment_form', 0 );
		$this->assertSame( array( 'core/paragraph' ), \Webmention\Block::hooked_block_types( $hooked ) );

		update_option( 'webmention_show_comment_form', 1 );
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
