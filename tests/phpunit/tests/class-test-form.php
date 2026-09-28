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
	 * Test that the classic form is skipped if the post contains the block.
	 */
	public function test_classic_form_skipped_with_block() {
		$post_id = self::factory()->post->create( array( 'post_content' => '<!-- wp:webmention/form /-->' ) );
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		webmention_comment_form();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Test that the classic form is rendered without the block.
	 */
	public function test_classic_form_rendered() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		webmention_comment_form();
		$this->assertStringContainsString( 'webmention-form', ob_get_clean() );
	}
}
