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

			global $wpdb;

			// 1. rename comment meta
			self::update_commentmeta_key( 'semantic_linkbacks_avatar', 'avatar' );
			self::update_commentmeta_key( 'semantic_linkbacks_author_url', 'webmention_author_url' );
			self::update_commentmeta_key( 'semantic_linkbacks_canonical', 'webmention_canonical' );
			self::update_commentmeta_key( 'semantic_linkbacks_source', 'webmention_source' );
			// 2. migrate comment type
			self::update_comment_type();
			self::add_protocol_key();
		}

		update_option( 'webmention_db_version', self::$target_version );
	}

	/**
	 * Rename meta keys.
	 *
	 * @param string $old The old commentmeta key
	 * @param string $new The new commentmeta key
	 */
	public static function update_commentmeta_key( $old, $new ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->commentmeta,
			array( 'meta_key' => $new ),
			array( 'meta_key' => $old ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Rename option keys.
	 *
	 * @param string $old The old option key
	 * @param string $new The new option key
	 */
	public static function update_options_key( $old, $new ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->options,
			array( 'options_name' => $new ),
			array( 'options_name' => $old ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Migrate webmentions to comment types.
	 */
	public static function update_comment_type() {
		global $wpdb;

		$wpdb->query(
			"UPDATE {$wpdb->comments} comment SET comment_type = ( SELECT meta_value FROM {$wpdb->commentmeta} WHERE comment_id = comment.comment_ID AND meta_key = 'semantic_linkbacks_type' LIMIT 1 ) WHERE comment_type = 'webmention'"
		);
	}

	/**
	 * Add protocol designation for webmentions.
	 */
	public static function add_protocol_key() {
		global $wpdb;

		$wpdb->query(
			"UPDATE {$wpdb->commentmeta} SET meta_key = 'protocol', meta_value = 'webmention' WHERE meta_key = 'semantic_linkbacks_type' OR meta_key = 'webmention_type'"
		);
	}
}
