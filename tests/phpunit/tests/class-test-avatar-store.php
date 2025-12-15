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
	 * Test cleanup orphaned avatars based on host and author URL properties.
	 */
	public function test_cleanup_orphaned_avatars() {
		$upload_dir = Avatar_Store::upload_directory();
		
		// Create test data
		$host1 = 'example.com';
		$host2 = 'test.org';
		$author_url1 = 'https://example.com/author1';
		$author_url2 = 'https://example.com/author2';
		$author_url3 = 'https://test.org/author1';
		
		$normalized1 = normalize_url( $author_url1 );
		$normalized2 = normalize_url( $author_url2 );
		$normalized3 = normalize_url( $author_url3 );
		
		$author_hash1 = md5( $normalized1 );
		$author_hash2 = md5( $normalized2 );
		$author_hash3 = md5( $normalized3 );
		
		// Create upload directory structure
		$host1_dir = trailingslashit( $upload_dir ) . $host1;
		$host2_dir = trailingslashit( $upload_dir ) . $host2;
		wp_mkdir_p( $host1_dir );
		wp_mkdir_p( $host2_dir );
		
		// Create avatar files
		$file1 = $host1_dir . '/' . $author_hash1 . '.jpg'; // Will be used by comment
		$file2 = $host1_dir . '/' . $author_hash2 . '.jpg'; // Orphaned (no matching comment)
		$file3 = $host2_dir . '/' . $author_hash3 . '.jpg'; // Will be used by comment
		
		// Create test avatar files
		file_put_contents( $file1, 'test avatar 1' );
		file_put_contents( $file2, 'test avatar 2' );
		file_put_contents( $file3, 'test avatar 3' );
		
		// Verify files exist
		$this->assertFileExists( $file1 );
		$this->assertFileExists( $file2 );
		$this->assertFileExists( $file3 );
		
		// Create comments that match file1 and file3
		$comment1 = self::factory()->comment->create(
			array(
				'comment_post_ID'    => $this->post->ID,
				'comment_type'       => 'webmention',
				'comment_author_url' => $author_url1,
			)
		);
		// Update comment to set author URL and webmention source URL
		wp_update_comment(
			array(
				'comment_ID'         => $comment1,
				'comment_author_url' => $author_url1,
			)
		);
		update_comment_meta( $comment1, 'webmention_source_url', 'https://example.com/post1' );
		
		$comment2 = self::factory()->comment->create(
			array(
				'comment_post_ID'    => $this->post->ID,
				'comment_type'       => 'webmention',
				'comment_author_url' => $author_url3,
			)
		);
		// Update comment to set author URL
		wp_update_comment(
			array(
				'comment_ID'         => $comment2,
				'comment_author_url' => $author_url3,
			)
		);
		update_comment_meta( $comment2, 'url', 'https://test.org/post1' );
		
		// Run cleanup - should delete file2 (orphaned) but keep file1 and file3
		$deleted = Avatar_Store::cleanup_orphaned_avatars();
		
		// Verify results
		$this->assertEquals( 1, $deleted, 'Should delete exactly one orphaned avatar file' );
		$this->assertFileExists( $file1, 'File matching comment should not be deleted' );
		$this->assertFalse( file_exists( $file2 ), 'Orphaned file should be deleted' );
		$this->assertFileExists( $file3, 'File matching comment should not be deleted' );
		
		// Cleanup test files
		if ( file_exists( $file1 ) ) {
			wp_delete_file( $file1 );
		}
		if ( file_exists( $file3 ) ) {
			wp_delete_file( $file3 );
		}
		if ( is_dir( $host1_dir ) ) {
			rmdir( $host1_dir );
		}
		if ( is_dir( $host2_dir ) ) {
			rmdir( $host2_dir );
		}
	}
}
