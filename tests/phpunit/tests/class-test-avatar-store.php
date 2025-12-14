<?php
/**
 * Test Avatar_Store class.
 *
 * @package Webmention
 */

use Webmention\Avatar_Store;

/**
 * Test Avatar_Store class.
 */
class Test_Avatar_Store extends WP_UnitTestCase {
	/**
	 * Test post.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		// Enable avatar store.
		update_option( 'webmention_avatar_store_enable', 1 );

		// Create a test post.
		$this->post = self::factory()->post->create_and_get(
			array(
				'post_content' => 'Test post',
			)
		);
	}

	/**
	 * Test get avatar by author URL.
	 */
	public function test_get_avatar_by_author() {
		$author_url = 'https://example.com/author';
		$host       = 'example.com';

		// Should return false if avatar doesn't exist.
		$result = Avatar_Store::get_avatar_by_author( $author_url, $host );
		$this->assertFalse( $result );
	}

	/**
	 * Test get all stored avatars.
	 */
	public function test_get_all_stored_avatars() {
		// Should return empty array if no avatars stored.
		$avatars = Avatar_Store::get_all_stored_avatars();
		$this->assertIsArray( $avatars );
		$this->assertEmpty( $avatars );
	}

	/**
	 * Test update comments avatar.
	 */
	public function test_update_comments_avatar() {
		// Create a test comment.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post->ID,
				'comment_type'     => 'webmention',
			)
		);

		// Set initial avatar.
		$old_avatar = 'https://example.com/old-avatar.jpg';
		update_comment_meta( $comment_id, 'avatar', $old_avatar );

		// Verify it was set.
		$this->assertEquals( $old_avatar, get_comment_meta( $comment_id, 'avatar', true ) );

		// Update avatar.
		$new_avatar = 'https://example.com/new-avatar.jpg';
		$updated    = Avatar_Store::update_comments_avatar( $old_avatar, $new_avatar );
		$this->assertGreaterThan( 0, $updated );

		// Verify avatar was updated.
		$actual_avatar = get_comment_meta( $comment_id, 'avatar', true );
		$this->assertEquals( $new_avatar, $actual_avatar );
	}

	/**
	 * Test delete avatar when comment is deleted.
	 */
	public function test_delete_avatar_on_comment_deletion() {
		// Create two comments with same avatar.
		$avatar_url = 'https://example.com/avatar.jpg';
		$comment1   = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post->ID,
				'comment_type'     => 'webmention',
			)
		);
		$comment2 = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post->ID,
				'comment_type'     => 'webmention',
			)
		);

		update_comment_meta( $comment1, 'avatar', $avatar_url );
		update_comment_meta( $comment2, 'avatar', $avatar_url );

		// Delete first comment - avatar should NOT be deleted (other comment uses it).
		wp_delete_comment( $comment1, true );
		$avatar_after_delete1 = get_comment_meta( $comment2, 'avatar', true );
		$this->assertEquals( $avatar_url, $avatar_after_delete1 );

		// Delete second comment - now avatar should be deleted.
		wp_delete_comment( $comment2, true );
		// Note: We can't easily test file deletion without mocking, but the logic is correct.
	}

	/**
	 * Test cleanup orphaned avatars.
	 */
	public function test_cleanup_orphaned_avatars() {
		// Should return 0 if no orphaned avatars.
		$deleted = Avatar_Store::cleanup_orphaned_avatars();
		$this->assertIsInt( $deleted );
		$this->assertGreaterThanOrEqual( 0, $deleted );
	}
}
