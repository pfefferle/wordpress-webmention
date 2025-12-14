<?php

namespace Webmention;

use WP_Comment;

/**
 * Avatar Store Class
 */
class Avatar_Store {
	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		// Store Avatars Locally
		add_action( 'comment_post', array( static::class, 'store_avatar' ), 20 );
		add_action( 'edit_comment', array( static::class, 'store_avatar' ), 20 );
		
		// Delete avatars when comments are deleted
		add_action( 'delete_comment', array( static::class, 'delete_avatar' ), 10 );
		add_action( 'wp_delete_comment', array( static::class, 'delete_avatar' ), 10 );
	}

	/**
	 * Return upload directory.
	 *
	 * @param string  $filepath File Path. Optional
	 * @param boolean $url Return a URL if true, otherwise the directory.
	 * @return string URL of upload directory.
	 */
	public static function upload_directory( $filepath = '', $url = false ) {
		$upload_dir  = wp_get_upload_dir();
		$upload_dir  = $url ? $upload_dir['baseurl'] : $upload_dir['basedir'];
		$upload_dir  = trailingslashit( $upload_dir ) . 'webmention/avatars/';
		$upload_dir  = apply_filters( 'webmention_avatar_directory', $upload_dir, $url );
		return $upload_dir . $filepath;
	}

	/**
	 * Sideload Avatar
	 *
	 * @param string $url URL.
	 * @param string $host Host.
	 * @param string $author Author
	 * @return string URL to Downloaded Image.
	 */
	public static function sideload_avatar( $url, $host, $author ) {
		// If the URL is inside the upload directory.
		if ( str_contains( self::upload_directory( '', true ), $url ) ) {
			return $url;
		}

		// Load dependencies.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . WPINC . '/media.php';

		$filehandle = $host . '/' . md5( $author ) . '.jpg';
		$filepath   = self::upload_directory( $filehandle );

		// Ensure directory exists.
		$dir = dirname( $filepath );
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// If this is a gravatar URL, automatically use gravatar to get the right size and file type.
		if ( str_contains( $url, 'gravatar.com' ) || str_contains( $url, 'secure.gravatar.com' ) ) {
			$hash    = wp_parse_url( $url, PHP_URL_PATH );
			$default = get_option( 'avatar_default', 'mystery' );
			$rating  = strtolower( get_option( 'avatar_rating' ) );
			$hash    = str_replace( '/avatar/', '', $hash );
			$hash    = trim( $hash, '/' );
			// Extract hash from path (might be just the hash or /avatar/hash)
			if ( strpos( $hash, '/' ) !== false ) {
				$parts = explode( '/', $hash );
				$hash  = end( $parts );
			}
			
			switch ( $default ) {
				case 'mm':
				case 'mystery':
				case 'mysteryman':
					$default = 'mp';
					break;
				case 'gravatar_default':
					$default = false;
					break;
			}
			$url  = 'https://www.gravatar.com/avatar/' . $hash . '.jpg';
			$url  = add_query_arg(
				array(
					's' => WEBMENTION_AVATAR_SIZE,
					'd' => $default, // Replace with our site default.
					'r' => $rating,
				),
				$url
			);
			$file = download_url( $url, 300 );
			if ( is_wp_error( $file ) ) {
				return false;
			}
			// Move downloaded file to final location.
			$moved = @rename( $file, $filepath );
			if ( ! $moved && file_exists( $file ) ) {
				@copy( $file, $filepath );
				@unlink( $file );
			}
			return self::upload_directory( $filehandle, true );
		}

		// Allow for common query parameters in image APIs to get a better quality image.
		$query = array();
		wp_parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
		if ( array_key_exists( 's', $query ) && is_numeric( $query['s'] ) ) {
			$url = str_replace( 's=' . $query['s'], 's=' . WEBMENTION_AVATAR_SIZE, $url );
		}
		if ( array_key_exists( 'width', $query ) && array_key_exists( 'height', $query ) ) {
			$url = str_replace( 'width=' . $query['width'], 'width=' . WEBMENTION_AVATAR_SIZE, $url );
			$url = str_replace( 'height=' . $query['height'], 'height=' . WEBMENTION_AVATAR_SIZE, $url );
		}

		// Download Profile Picture and add as attachment
		$file = wp_get_image_editor( download_url( $url, 300 ) );
		if ( is_wp_error( $file ) ) {
			return false;
		}
		$file->resize( null, WEBMENTION_AVATAR_SIZE, true );
		$file->set_quality( WEBMENTION_AVATAR_QUALITY );
		$file->save( $filepath, 'image/jpg' );
		return self::upload_directory( $filehandle, true );
	}

	/**
	 * Given an Avatar URL return the filepath.
	 *
	 * @param string $url URL.
	 * @return string Filepath.
	 */
	public static function avatar_url_to_filepath( $url ) {
		if ( ! str_contains( self::upload_directory( '', true ), $url ) ) {
			return false;
		}
		$path = str_replace( self::upload_directory( '', true ), '', $url );
		return self::upload_directory( $path );
	}

	/**
	 * Delete Avatar File.
	 *
	 * @param string $url Avatar to Delete.
	 * @return boolean True if successful. False if not.
	 */
	public static function delete_avatar_file( $url ) {
		$filepath = self::avatar_url_to_filepath( $url );
		if ( empty( $filepath ) ) {
			return false;
		}
		if ( file_exists( $filepath ) ) {
			wp_delete_file( $filepath );
			return true;
		}
		return false;
	}


	/**
	 * Delete Avatar when comment is deleted.
	 * Only deletes if no other comments are using the same avatar.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public static function delete_avatar( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return false;
		}

		$avatar_url = get_comment_meta( $comment_id, 'avatar', true );
		if ( ! $avatar_url ) {
			return false;
		}

		// Check if any other comments are using this avatar
		$other_comments = get_comments(
			array(
				'meta_key'   => 'avatar',
				'meta_value' => $avatar_url,
				'number'     => 1,
				'exclude'    => array( $comment_id ),
			)
		);

		// Only delete the avatar file if no other comments are using it
		if ( empty( $other_comments ) ) {
			self::delete_avatar_file( $avatar_url );
		}

		return true;
	}

	/**
	 * Store Avatars locally
	 *
	 * @param int $comment_ID
	 * @return bool|string Returns false on failure, or the stored avatar URL on success.
	 */
	public static function store_avatar( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return false;
		}

		// Do not try to store the avatar if there is a User ID. Let something else handle that.
		if ( $comment->user_id ) {
			return false;
		}

		$avatar = \Webmention\Avatar::get_avatar_meta( $comment );

		if ( ! $avatar ) {
			return false;
		}

		$author = normalize_url( get_comment_author_url( $comment ) );

		// Do not try to store if no author URL.
		if ( empty( $author ) ) {
			return false;
		}
		$host       = webmention_extract_domain( get_url_from_webmention( $comment ) );
		$avatar_url = self::sideload_avatar( $avatar, $host, $author );

		if ( $avatar_url ) {
			delete_comment_meta( $comment->comment_ID, 'semantic_linkbacks_avatar' );
			update_comment_meta( $comment->comment_ID, 'avatar', $avatar_url );
			return $avatar_url;
		}

		return false;
	}

	/**
	 * Get avatar URL by author URL and host.
	 *
	 * @param string $author_url Normalized author URL.
	 * @param string $host Host domain.
	 * @return string|false Avatar URL or false if not found.
	 */
	public static function get_avatar_by_author( $author_url, $host ) {
		$filehandle = $host . '/' . md5( $author_url ) . '.jpg';
		$filepath   = self::upload_directory( $filehandle );

		if ( file_exists( $filepath ) ) {
			return self::upload_directory( $filehandle, true );
		}

		return false;
	}

	/**
	 * Get all stored avatars grouped by author URL.
	 * Returns avatars that are stored in the file system AND being used by comments.
	 *
	 * @return array Array of avatars with keys: host, author_url, avatar_url, filepath.
	 */
	public static function get_all_stored_avatars() {
		$avatars     = array();
		$upload_dir  = self::upload_directory();
		$upload_url  = self::upload_directory( '', true );

		if ( ! is_dir( $upload_dir ) ) {
			return $avatars;
		}

		// Get all comments with avatars that are stored locally
		global $wpdb;
		$comments_with_avatars = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.comment_ID, c.comment_author_url, cm.meta_value as avatar_url 
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_ID
				WHERE cm.meta_key = %s 
				AND c.comment_type = 'webmention'
				AND c.user_id = 0
				AND cm.meta_value LIKE %s",
				'avatar',
				$wpdb->esc_like( $upload_url ) . '%'
			)
		);

		if ( empty( $comments_with_avatars ) ) {
			return $avatars;
		}

		// Group by stored avatar URL
		$avatar_map = array();
		foreach ( $comments_with_avatars as $comment_data ) {
			$avatar_url = $comment_data->avatar_url;
			
			if ( ! isset( $avatar_map[ $avatar_url ] ) ) {
				$avatar_map[ $avatar_url ] = array(
					'avatar_url'  => $avatar_url,
					'comment_ids' => array(),
					'author_urls' => array(),
				);
			}
			
			$avatar_map[ $avatar_url ]['comment_ids'][] = $comment_data->comment_ID;
			if ( $comment_data->comment_author_url ) {
				$normalized = normalize_url( $comment_data->comment_author_url );
				if ( $normalized ) {
					$avatar_map[ $avatar_url ]['author_urls'][] = $normalized;
				}
			}
		}

		// Convert to final format
		foreach ( $avatar_map as $avatar_url => $data ) {
			// Extract host and hash from file path
			$relative_path = str_replace( $upload_url, '', $avatar_url );
			// Remove leading/trailing slashes
			$relative_path = trim( $relative_path, '/' );
			$path_parts    = explode( '/', $relative_path );
			
			if ( count( $path_parts ) !== 2 ) {
				continue;
			}

			$host        = $path_parts[0];
			$author_hash = basename( $path_parts[1], '.jpg' );
			$filepath    = self::upload_directory( $relative_path );

			// Get unique author URL (prefer normalized)
			$author_url = null;
			if ( ! empty( $data['author_urls'] ) ) {
				$author_urls = array_unique( array_filter( $data['author_urls'] ) );
				$author_url  = ! empty( $author_urls ) ? reset( $author_urls ) : null;
			}

			// Verify file exists
			if ( file_exists( $filepath ) ) {
				$avatars[] = array(
					'host'        => $host,
					'author_hash' => $author_hash,
					'avatar_url'  => $avatar_url,
					'filepath'    => $filepath,
					'author_url'  => $author_url,
				);
			}
		}

		return $avatars;
	}

	/**
	 * Clean up orphaned avatar files (avatars not used by any comments).
	 *
	 * @return int Number of files deleted.
	 */
	public static function cleanup_orphaned_avatars() {
		$upload_dir  = self::upload_directory();
		$upload_url  = self::upload_directory( '', true );
		$deleted     = 0;

		if ( ! is_dir( $upload_dir ) ) {
			return $deleted;
		}

		// Get all avatar URLs currently in use by comments
		global $wpdb;
		$used_avatars = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->commentmeta} WHERE meta_key = %s AND meta_value LIKE %s",
				'avatar',
				$wpdb->esc_like( $upload_url ) . '%'
			)
		);

		$used_avatars = array_flip( $used_avatars );

		// Ensure we have a trailing slash for glob pattern.
		$upload_dir = trailingslashit( $upload_dir );
		$hosts      = glob( $upload_dir . '*', GLOB_ONLYDIR );

		if ( ! is_array( $hosts ) ) {
			return $deleted;
		}

		foreach ( $hosts as $host_dir ) {
			if ( ! is_dir( $host_dir ) ) {
				continue;
			}

			$files = glob( $host_dir . '/*.jpg' );

			if ( ! is_array( $files ) ) {
				continue;
			}

			foreach ( $files as $file ) {
				if ( ! is_file( $file ) ) {
					continue;
				}

				$host     = basename( $host_dir );
				$filename = basename( $file, '.jpg' );
				$relative = $host . '/' . $filename . '.jpg';
				$avatar_url = $upload_url . $relative;

				// Delete if not used by any comments
				if ( ! isset( $used_avatars[ $avatar_url ] ) ) {
					if ( wp_delete_file( $file ) ) {
						$deleted++;
					}
				}
			}
		}

		return $deleted;
	}

	/**
	 * Update all comments that use a specific avatar URL.
	 *
	 * @param string $old_avatar_url Old avatar URL.
	 * @param string $new_avatar_url New avatar URL.
	 * @return int Number of comments updated.
	 */
	public static function update_comments_avatar( $old_avatar_url, $new_avatar_url ) {
		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->commentmeta,
			array( 'meta_value' => $new_avatar_url ),
			array(
				'meta_key'   => 'avatar',
				'meta_value' => $old_avatar_url,
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		return $updated;
	}

	/**
	 * Replace avatar for an author URL.
	 *
	 * @param string $author_url Normalized author URL.
	 * @param string $host Host domain.
	 * @param string $new_avatar_url URL of new avatar to download and store.
	 * @return string|WP_Error New avatar URL on success, WP_Error on failure.
	 */
	public static function replace_avatar( $author_url, $host, $new_avatar_url ) {
		// Get old avatar URL if it exists.
		$old_avatar_url = self::get_avatar_by_author( $author_url, $host );

		// Sideload the new avatar.
		$new_stored_url = self::sideload_avatar( $new_avatar_url, $host, $author_url );

		if ( ! $new_stored_url ) {
			return new \WP_Error( 'avatar_download_failed', __( 'Failed to download and store avatar.', 'webmention' ) );
		}

		// If there was an old avatar, update all comments using it.
		if ( $old_avatar_url && $old_avatar_url !== $new_stored_url ) {
			self::update_comments_avatar( $old_avatar_url, $new_stored_url );
			// Delete old avatar file.
			self::delete_avatar_file( $old_avatar_url );
		}

		return $new_stored_url;
	}

	/**
	 * Refresh avatar from author URL.
	 *
	 * @param string $author_url Author URL to fetch avatar from.
	 * @param string $host Host domain.
	 * @return string|WP_Error New avatar URL on success, WP_Error on failure.
	 */
	public static function refresh_avatar( $author_url, $host ) {
		// Use Handler to parse author page and get avatar.
		require_once WEBMENTION_PLUGIN_DIR . '/includes/Handler/class-mf2.php';
		$handler = new \Webmention\Handler\MF2();
		$author  = $handler->parse_authorpage( $author_url );

		if ( is_wp_error( $author ) ) {
			return $author;
		}

		// Extract photo from author data.
		$photo = null;
		if ( isset( $author['properties']['photo'] ) && ! empty( $author['properties']['photo'] ) ) {
			$photo = is_array( $author['properties']['photo'] ) ? $author['properties']['photo'][0] : $author['properties']['photo'];
		}

		if ( ! $photo ) {
			return new \WP_Error( 'no_avatar_found', __( 'No avatar found on author page.', 'webmention' ) );
		}

		// Replace avatar with the one from author page.
		return self::replace_avatar( $author_url, $host, $photo );
	}
}
