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
}
