<?php

namespace Webmention;

class DB {
	/**
	 * Which internal datastructure version we are running on.
	 *
	 * @var int
	 */
	private static $target_version = '1.0.0';

	private static function get_target_version() {
		return self::$target_version;
	}

	private static function get_version() {
		return floatval( get_option( 'webmention_db_version', 0 ) );
	}

	/**
	 * Whether the database structure is up to date.
	 *
	 * @return bool
	 */
	private static function is_latest_version() {
		$current_version = self::get_version();

		return (bool) version_compare( $current_version, self::$target_version, '==' );
	}

	/**
	 * Updates the database structure if necessary.
	 */
	public static function update_database() {
		if ( self::is_latest_version() ) {
			return;
		}

		$version_from_db = self::get_version();
		if ( version_compare( $version_from_db, '1.0.0', '<' ) ) {
			
			// Before renaming comment meta add the webmention protocol key to where it may not be present.
			self::add_protocol_key();

			global $wpdb;

			// 1. rename comment meta
			self::update_commentmeta_key( 'semantic_linkbacks_avatar', 'avatar' );
			self::update_commentmeta_key( 'semantic_linkbacks_author_url', 'webmention_author_url' );
			self::update_commentmeta_key( 'semantic_linkbacks_canonical', 'webmention_canonical' );
			self::update_commentmeta_key( 'semantic_linkbacks_source', 'webmention_source' );
			// 2. migrate comment type

		}

		update_option( 'webmention_db_version', self::$db_version );
	}

	/**
	 * Rename meta keys.
	 *
	 * @param string $old The old commentmeta key
	 * @param string $new The new commentmeta key
	 */
	public static function update_commentmeta_key( $old, $new ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"UPDATE {$wpdb->commentmeta} SET meta_key = %s WHERE meta_key = %s",
			$new,
			$old
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $query );
	}

	/**
	 * Rename option keys.
	 *
	 * @param string $old The old option key
	 * @param string $new The new option key
	 */
	public static function update_options_key( $old, $new ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"UPDATE {$wpdb->options} SET options_name = %s WHERE options_name = %s",
			$new,
			$old
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $query );
	}

	/**
	 * Migrate webmentions to comment types
	 *
	 */
	public static function update_comment_type() {
		global $wpdb;
		foreach( array( 'mention', 'reply', 'repost', 'like', 'favorite', 'tag', 'bookmark', 'invited', 'listen', 'watch', 'read', 'follow' ) as $type ) {
			$comment_id_list = get_comments( 
						array(
							'type' => 'webmention',
							'meta_key' => 'semantic_linkbacks_type',
							'meta_value' => $type,
							'fields' => 'ids'
						)
			$query = $wpdb->prepare(
					"UPDATE {$wpdb->comments}
					SET comment_type = '{$type}'
					WHERE comment_type = 'webmention'
					AND comment_ID IN ({$comment_id_list})" 
			);
			$wpdb->query( $query );
		}
	}

	/**
	 * Add protocol designation for webmentions.
	 * Should be done before migrating comment types.
	 *
	 */
	public static function add_protocol_key() {
		$ids = get_comments(
			array(
				'type' => 'webmention',
				'fields' => 'ids'
			);
		foreach( $ids as $id ) {
			update_comment_meta( $id, 'protocol', 'webmention' );
		}

		/* The above covers adding webmention as a protocol to comment type webmention before we change these over to their new types.
		 * But what about comment types which were used for reply? We start with the meta keys we added in Version 3.2.0.
		 */

		$ids = get_comments(
			array(
				'type' => 'comment',
				'fields' => 'ids',
				'meta_query' => array(
					array(
						'key' => 'webmention_source_url',
						'compare' => 'EXISTS'
					)
				)
			);
		foreach( $ids as $id ) {
			update_comment_meta( $id, 'protocol', 'webmention' );
		}

		/* What were the indicators pre-3.2? These would be limited to Semantic Linkbacks indicators.
		 * Problem is that Semantic Linkbacks processed pingbacks and trackbacks in this way.
		 * We are going to assume that anything rendered as a comment was a webmention even though there
		 * is a small chance this is not true. Instances of pingbacks being sent with reply to microformats
		 * seem rare.
		 * Pingbacks and trackbacks of other types will remain type pingback or type trackback.
		 */

		$ids = get_comments(
			array(
				'type' => 'comment',
				'fields' => 'ids',
				'meta_query' => array(
					array(
						'key' => 'semantic_linkbacks_type',
						'value' => 'reply',
						'compare' => '='
					)
				)
			);
		foreach( $ids as $id ) {
			update_comment_meta( $id, 'protocol', 'webmention' );
		}

	}


}
