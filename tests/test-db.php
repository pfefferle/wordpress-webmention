<?php
class DB_Test extends WP_UnitTestCase {
	public function test_update_database() {
		require_once( dirname( __FILE__ ) . '/../includes/class-db.php' );

		$comment_id = wp_insert_comment(
			array(
				'comment_author' => 'Test',
				'comment_author_email' => 'test@example.org',
				'comment_content' => 'test comment',
				'comment_type' => 'webmention',
				'comment_meta' => array(
					'semantic_linkbacks_source' => 'https://example.org/source',
					'semantic_linkbacks_avatar' => 'https://example.org/avatar',
					'semantic_linkbacks_canonical' => 'https://example.org/canonical',
					'semantic_linkbacks_author_url' => 'https://example.org/author_url',
					'semantic_linkbacks_type' => 'reply',
					'mf2_author' => array(
						'name' => 'Test',
						'url' => 'https://example.org/author_url',
						'photo' => 'https://example.org/avatar',
					),
				),
			)
		);

		$this->assertEquals( \Webmention\DB::get_version(), 0 );

		\Webmention\DB::update_database();

		$this->assertEquals( \Webmention\DB::get_version(), '1.0.1' );

		wp_cache_flush();

		$metas = get_comment_meta( $comment_id );

		$this->assertEquals( $metas['avatar'][0], 'https://example.org/avatar' );
		$this->assertEquals( $metas['protocol'][0], 'webmention' );
		$this->assertEquals( $metas['webmention_source_url'][0], 'https://example.org/source' );
		$this->assertEquals( $metas['url'][0], 'https://example.org/canonical' );

		$comment = get_comment( $comment_id );

		$this->assertEquals( $comment->comment_type, 'reply' );
		$this->assertEquals( $comment->comment_author_url, 'https://example.org/author_url' );
	}
}
