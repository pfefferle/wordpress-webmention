<?php
/**
 * Test Comment class.
 *
 * @package Webmention
 */

use Webmention\Comment;

/**
 * Test Comment class.
 */
class Test_Comment extends WP_UnitTestCase {
	/**
	 * Test comment ID.
	 *
	 * @var int
	 */
	private $comment_id;

	/**
	 * Path of a template that throws when included.
	 *
	 * @var string
	 */
	private $template;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		$post_id          = self::factory()->post->create();
		$this->comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );

		// Stand-in for the comment template, so including it does not end the test run.
		$this->template = get_temp_dir() . 'webmention-test-comment-template.php';
		file_put_contents( $this->template, '<?php throw new Exception( "comment-template" );' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		add_filter(
			'webmention_comment_template',
			function () {
				return $this->template;
			}
		);
		add_filter(
			'wp_redirect',
			function ( $location ) {
				throw new Exception( 'redirect:' . $location );
			}
		);
	}

	/**
	 * Tear down test.
	 */
	public function tear_down() {
		unset( $_SERVER['HTTP_SEC_FETCH_MODE'] );
		wp_delete_file( $this->template );

		parent::tear_down();
	}

	/**
	 * Test that both comment query vars are registered.
	 */
	public function test_query_vars() {
		$this->assertContains( 'replytocom', Comment::query_var( array() ) );
		$this->assertContains( 'c', Comment::query_var( array() ) );
	}

	/**
	 * Test that parsers still get the comment template for replytocom URLs.
	 */
	public function test_replytocom_serves_comment_template() {
		$this->go_to( add_query_arg( 'replytocom', $this->comment_id, home_url( '/' ) ) );

		$this->assertSame( $this->template, Comment::comment_template_include( 'theme.php' ) );
	}

	/**
	 * Test that browsers following the reply link get the theme template.
	 */
	public function test_replytocom_keeps_theme_template_for_browsers() {
		$_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
		$this->go_to( add_query_arg( 'replytocom', $this->comment_id, home_url( '/' ) ) );

		$this->assertSame( 'theme.php', Comment::comment_template_include( 'theme.php' ) );
	}

	/**
	 * Test that other URLs keep their template.
	 */
	public function test_other_urls_keep_theme_template() {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( 'theme.php', Comment::comment_template_include( 'theme.php' ) );
	}

	/**
	 * Test that parsers get the comment template for c URLs.
	 */
	public function test_c_serves_comment_template() {
		$this->go_to( add_query_arg( 'c', $this->comment_id, home_url( '/' ) ) );

		$this->expectExceptionMessage( 'comment-template' );
		Comment::comment_template_redirect();
	}

	/**
	 * Test that browsers are redirected to the comment for c URLs.
	 */
	public function test_c_redirects_browsers_to_comment() {
		$_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
		$this->go_to( add_query_arg( 'c', $this->comment_id, home_url( '/' ) ) );

		$this->expectExceptionMessage( 'redirect:' . get_comment_link( $this->comment_id ) );
		Comment::comment_template_redirect();
	}

	/**
	 * Test that requests without c are left alone.
	 */
	public function test_template_redirect_ignores_other_urls() {
		$this->go_to( home_url( '/' ) );

		$this->assertNull( Comment::comment_template_redirect() );
	}
}
