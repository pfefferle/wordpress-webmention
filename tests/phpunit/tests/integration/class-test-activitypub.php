<?php
/**
 * Test ActivityPub integration.
 *
 * @package Webmention
 */

use Webmention\Comment_Walker;
use Webmention\Integration\Activitypub;

/**
 * Test ActivityPub integration.
 *
 * @coversDefaultClass \Webmention\Integration\Activitypub
 */
class Test_Activitypub extends WP_UnitTestCase {
	/**
	 * Hook the Webmention facepile like Comment_Walker::init() does.
	 */
	public function set_up() {
		parent::set_up();

		add_action( 'comment_form_before', array( Comment_Walker::class, 'show_separated_reactions' ) );
		add_action( 'comment_form_comments_closed', array( Comment_Walker::class, 'show_separated_reactions' ) );
	}

	/**
	 * The facepile stays when the reactions block was not rendered.
	 *
	 * @covers ::maybe_remove_facepile
	 */
	public function test_keeps_facepile_without_reactions_block() {
		Activitypub::maybe_remove_facepile();

		$this->assertSame( 10, has_action( 'comment_form_before', array( Comment_Walker::class, 'show_separated_reactions' ) ) );
		$this->assertSame( 10, has_action( 'comment_form_comments_closed', array( Comment_Walker::class, 'show_separated_reactions' ) ) );
	}

	/**
	 * The facepile is removed once the reactions block was rendered.
	 *
	 * @covers ::maybe_remove_facepile
	 */
	public function test_removes_facepile_with_reactions_block() {
		apply_filters( 'render_block_activitypub/reactions', '' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Core's dynamic block hook name.

		Activitypub::maybe_remove_facepile();

		$this->assertFalse( has_action( 'comment_form_before', array( Comment_Walker::class, 'show_separated_reactions' ) ) );
		$this->assertFalse( has_action( 'comment_form_comments_closed', array( Comment_Walker::class, 'show_separated_reactions' ) ) );
	}
}
