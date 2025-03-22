<?php
/**
 * Upgrade Class
 *
 * @package Webmention
 */
namespace Webmention;

/**
 * Upgrade Class
 *
 * @package Webmention
 */
class Upgrade {
	/**
	 * Initialize the upgrade class.
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'maybe_upgrade' ) );
	}

	/**
	 * Get the current version.
	 *
	 * @return int
	 */
	public static function get_version() {
		return get_option( 'webmention_db_version', 0 );
	}

	/**
	 * Whether the database structure is up to date.
	 *
	 * @return bool True if the database structure is up to date, false otherwise.
	 */
	public static function is_latest_version() {
		return (bool) \version_compare(
			self::get_version(),
			WEBMENTION_VERSION,
			'=='
		);
	}

	/**
	 * Locks the database migration process to prevent simultaneous migrations.
	 *
	 * @return bool|int True if the lock was successful, timestamp of existing lock otherwise.
	 */
	public static function lock() {
		global $wpdb;

		// Try to lock.
		$lock_result = (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", 'webmention_migration_lock', \time() ) ); // phpcs:ignore WordPress.DB

		if ( ! $lock_result ) {
			$lock_result = \get_option( 'webmention_migration_lock' );
		}

		return $lock_result;
	}

	/**
	 * Unlocks the database migration process.
	 */
	public static function unlock() {
		\delete_option( 'webmention_migration_lock' );
	}

	/**
	 * Whether the database migration process is locked.
	 *
	 * @return boolean
	 */
	public static function is_locked() {
		$lock = \get_option( 'webmention_migration_lock' );

		if ( ! $lock ) {
			return false;
		}

		$lock = (int) $lock;

		if ( $lock < \time() - 1800 ) {
			self::unlock();
			return false;
		}

		return true;
	}

	/**
	 * Updates the database structure if necessary.
	 */
	public static function maybe_upgrade() {
		if ( self::is_latest_version() ) {
			return;
		}

		if ( self::is_locked() ) {
			return;
		}

		self::lock();

		$version_from_db = self::get_version();

		if ( version_compare( $version_from_db, '1.0.0', '<' ) ) {
			self::migrate_to_1_0_0();
		}
		if ( version_compare( $version_from_db, '1.0.1', '<' ) ) {
			self::migrate_to_1_0_1();
		}

		/**
		 * Fires when the system has to be migrated.
		 *
		 * @param string $version_from_db The version from which to migrate.
		 * @param string $target_version  The target version to migrate to.
		 */
		\do_action( 'webmention_migrate', $version_from_db, WEBMENTION_VERSION );

		\update_option( 'webmention_db_version', WEBMENTION_VERSION );

		self::unlock();
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
	 * The Migration for Plugin Version 5.0.0 and DB Version 1.0.0
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public static function migrate_to_1_0_0() {
		wp_cache_flush();

		// 1. rename comment meta
		self::update_commentmeta_key( 'semantic_linkbacks_source', 'webmention_source_url' );
		self::update_commentmeta_key( 'semantic_linkbacks_avatar', 'avatar' );
		self::update_commentmeta_key( 'semantic_linkbacks_canonical', 'url' );
		// 2. migrate comment type
		global $wpdb;

		//Migrate Webmentions to comment types.
		$wpdb->query(
			"UPDATE {$wpdb->comments} comment SET comment_type = ( SELECT meta_value FROM {$wpdb->commentmeta} WHERE comment_id = comment.comment_ID AND meta_key = 'semantic_linkbacks_type' LIMIT 1 ) WHERE comment_type = 'webmention'"
		);

		// Add protocol designation for Webmentions.
		$wpdb->query(
			"UPDATE {$wpdb->commentmeta} SET meta_key = 'protocol', meta_value = 'webmention' WHERE meta_key = 'semantic_linkbacks_type' OR meta_key = 'webmention_type'"
		);
	}

	/**
	 * Migrate to version 1.0.1
	 *
	 * @since 5.1.0
	 *
	 * @return void
	 */
	public static function migrate_to_1_0_1() {
		wp_cache_flush();

		$comments = get_comments(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => 'mf2_author',
						'compare' => 'EXISTS',
					),
					array(
						'key'   => 'protocol',
						'value' => 'webmention',
					),
				),
			)
		);

		foreach ( $comments as $comment_id ) {
			$author = get_comment_meta( $comment_id, 'mf2_author', true );
			$source = get_comment_meta( $comment_id, 'webmention_source_url', true );
			if ( is_array( $author ) ) {
				if ( array_key_exists( 'url', $author ) && ( $source !== $author['url'] ) ) {
					$comment                       = get_comment( $comment_id, ARRAY_A );
					$comment['comment_author_url'] = $author['url'];
					wp_update_comment( $comment );
				}
			}
		}
	}
}
