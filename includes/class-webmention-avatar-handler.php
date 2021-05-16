<?php

/**
 * Avatar Handler Class
 *
 */
class Webmention_Avatar_Handler {
	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		$cls = get_called_class();

		add_filter( 'pre_get_avatar_data', array( $cls, 'avatar_stored_in_comment' ), 30, 2 );

		// Store Avatars Locally
		add_action( 'comment_post', array( $cls, 'store_avatar' ), 20 );
		add_action( 'edit_comment', array( $cls, 'store_avatar' ), 20 );
	}

	/**
	 * Return upload directory.
	 *
	 * @param string $filepath File Path. Optional
	 * @return string URL of upload directory.
	 */
	public static function upload_directory( $filepath = '' ) {
		$upload_dir = wp_get_upload_dir();
		$upload_dir = $upload_dir['basedir'] . '/webmention/avatars/';
		$upload_dir = apply_filters( 'webmention_avatar_directory', $upload_dir );
		return $upload_dir . $filepath;
	}

	/**
	 * Return upload directory url.
	 *
	 * @param string $filepath File Path. Optional.
	 * @return string URL of upload directory.
	 */
	public static function upload_directory_url( $filepath = '' ) {
		$upload_dir = wp_get_upload_dir();
		$upload_dir = $upload_dir['baseurl'] . '/webmention/avatars/';
		$upload_dir = apply_filters( 'webmention_avatar_directory', $upload_dir );
		return $upload_dir . $filepath;
	}

	/**
	 * Sideload Avatar
	 *
	 * @param string $url URL.
	 * @param string $host Host.
	 * @param string $author Author
	 * @return string URL to Downloaded Image.
	 *
	 */
	public static function sideload_avatar( $url, $host, $author ) {
		// If the URL is inside the upload directory.
		if ( str_contains( self::upload_directory_url(), $url ) ) {
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
			@move_uploaded_file( $file, $filepath );
			return self::upload_directory_url( $filehandle );
		}

		// Allow for other s= queries.
		$query = array();
		wp_parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
		if ( array_key_exists( 's', $query ) ) {
			$url = str_replace( 's=' . $query['s'], 's=' . WEBMENTION_AVATAR_SIZE, $url );
		}

		// Download Profile Picture and add as attachment
		$file = wp_get_image_editor( download_url( $url, 300 ) );
		if ( is_wp_error( $file ) ) {
			return false;
		}
		$file->resize( null, WEBMENTION_AVATAR_SIZE, true );
		$file->set_quality( WEBMENTION_AVATAR_QUALITY );
		$file->save( $filepath, 'image/jpg' );

		return self::upload_directory_url( $filehandle );
	}


	/**
	 * Given an Avatar URL return the filepath.
	 *
	 * @param string $url URL.
	 * @return string Filepath.
	 */
	public static function avatar_url_to_filepath( $url ) {
		$upload_dir = wp_upload_dir( null, false );
		if ( false !== strpos( $url, $upload_dir['baseurl'] ) ) {
			return false;
		}
		$path = str_replace( $upload_dir['baseurl'], '', $url );
		return ( $upload_dir['basedir'] . ltrim( $path, '/' ) );
	}

	/**
	 * Delete Avatar File.
	 *
	 * @param string $url Avatar to Delete.
	 * @return boolean True if successful. False if not.
	 *
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

		$avatar = webmention_get_avatar_url( $comment );

		if ( ! $avatar ) {
			return false;
		}

		$author = get_comment_author_url( $comment );
		$host   = webmention_get_user_domain( $comment );
		$avatar = self::sideload_avatar( $avatar, $host, $author );

		delete_comment_meta( $comment->comment_ID, 'semantic_linkbacks_avatar' );
		update_comment_meta( $comment->comment_ID, 'avatar', $avatar );
	}

	public static function anonymous_avatar( $avatar_defaults ) {
		$avatar_defaults['mystery'] = __( 'Mystery Person (hosted locally)', 'webmention' );

		return $avatar_defaults;
	}

	/**
	 * Function to retrieve Avatar if stored in meta
	 *
	 *
	 * @param int|WP_Comment $comment
	 *
	 * @return string $url
	 */
	public static function get_avatar_meta( $comment ) {
		if ( is_numeric( $comment ) ) {
			$comment = get_comment( $comment );
		}

		if ( ! $comment ) {
			return false;
		}

		$avatar = get_comment_meta( $comment->comment_ID, 'avatar', true );

		// Backward Compatibility for Semantic Linkbacks
		if ( ! $avatar ) {
			$avatar = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_avatar', true );
		}

		return $avatar;
	}

	/**
	 * If there is an avatar stored in comment meta use it
	 *
	 * @param array             $args Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $comment Comment object
	 *
	 * @return array $args
	 */
	public static function avatar_stored_in_comment( $args, $comment ) {
		// If this is not a comment then do not continue
		if ( ! $comment instanceof WP_Comment ) {
			return $args;
		}

		// If another filter has already provided a url
		if ( isset( $args['url'] ) && wp_http_validate_url( $args['url'] ) ) {
			return $args;
		}

		// If this type does not show avatars or if there is a user ID set then return
		if ( ! is_avatar_comment_type( get_comment_type( $comment ) ) || $comment->user_id ) {
			return $args;
		}

		// check if comment has an avatar
		$avatar = self::get_avatar_meta( $comment->comment_ID );

		if ( $avatar ) {
			if ( is_numeric( $avatar ) ) {
				$avatar = wp_get_attachment_url( $avatar, 'full' );
			}

			if ( wp_http_validate_url( $avatar ) ) {
				$path = self::avatar_url_to_filepath( $avatar );
				// Check to see if filepath exists.
				if ( $path && ! file_exists( $path ) ) {
					return $args;
				}
				$args['url'] = $avatar;
			}

			if ( ! isset( $args['class'] ) || ! is_array( $args['class'] ) ) {
				$args['class'] = array( 'local-avatar' );
			} else {
				$args['class'][] = 'local-avatar';
				$args['class']   = array_unique( $args['class'] );
			}
		}

		return $args;
	}
}
