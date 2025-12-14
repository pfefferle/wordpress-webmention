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
		$upload_dir .= '/webmention/avatars/';
		$upload_dir  = apply_filters( 'webmention_avatar_directory', $upload_dir, $url );
		return $upload_dir . $filepath;
	}

	/**
	 * Determines if there is a file in the store for a specific host and URL
	 *
	 * @param string $host Host.
	 * @param string $author Author.
	 * @return string|boolean URL to image or false if not found
	 */
	public static function find_avatar( $host, $author ) {
		$upload_dir = trailingslashit( self::upload_directory( $host ) );
		$upload_url = trailingslashit( self::upload_directory( $host, true ) );
		$results    = scandir( $upload_dir );
		if ( ! $results ) {
			return $results;
		}
		foreach ( $results as $result ) {
			if ( str_contains( $result, md5( $author ) ) ) {
				return $upload_url . $result;
			}
		}
		return false;
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

		// If this is a gravatar URL, automatically use gravatar to get the right size and file type.
		if ( str_contains( 'gravatar.com', $url ) ) {
			$hash    = wp_parse_url( $url, PHP_URL_PATH );
			$default = get_option( 'avatar_default', 'mystery' );
			$rating  = strtolower( get_option( 'avatar_rating' ) );
			$hash    = str_replace( '/avatar/', '', $hash );
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
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Silencing is intentional as move may fail on some systems.
			@move_uploaded_file( $file, $filepath );
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
	 * Delete Avatar.
	 *
	 * @param int $comment_ID
	 */
	public static function delete_avatar( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return false;
		}
		$url = get_comment_meta( $comment_id, 'avatar', true );
		self::delete_avatar_file( $url );
	}

	/**
	 * Store Avatars locally
	 *
	 * @param int $comment_ID
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
			// disable updating this field as the original URL will be stored going forward and the store will overlay on top of that when the setting is enabled
			// update_comment_meta( $comment->comment_ID, 'avatar', $avatar_url );
		}
	}
}
