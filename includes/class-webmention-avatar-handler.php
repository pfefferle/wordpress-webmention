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
		add_filter( 'get_avatar_data', array( $cls, 'anonymous_avatar_data' ), 30, 2 );

		// All the default gravatars come from Gravatar instead of being generated locally so add a local default
		add_filter( 'avatar_defaults', array( $cls, 'anonymous_avatar' ) );

		// Store Avatars Locally
		add_action( 'comment_post', array( $cls, 'store_avatar' ), 20 );
		add_action( 'edit_comment', array( $cls, 'store_avatar' ), 20 );
	}

	/**
	 * Sideload Avatar
	 *
	 * @param string $url URL.
	 * @param string $host Host.
	 * @param string $user_name User Name.
	 * @return string URL to Downloaded Image.
	 *
	 */
	public static function sideload_avatar( $url, $host, $user_name ) {
		$upload_dir = wp_upload_dir( null, false );

		if ( wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return false;
		}

		// Load dependencies.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . WPINC . '/media.php';

		$filehandle = '/webmention/avatars/' . $host . '/' . $user_name . '.jpg';
		$filepath   = $upload_dir['basedir'] . $filehandle;

		// Download Profile Picture and add as attachment
		$file = wp_get_image_editor( download_url( $url, 300 ) );

		if ( is_wp_error( $file ) ) {
			$file = wp_get_image_editor( download_url( plugin_dir_url( dirname( __FILE__ ) ) . 'img/mm.jpg', 300 ) );
		}

		$file->resize( null, WEBMENTION_AVATAR_SIZE, true );
		$file->set_quality( WEBMENTION_AVATAR_QUALITY );
		$file->save( $filepath, 'image/jpg' );

		return ( $upload_dir['baseurl'] . '/' . ltrim( $filehandle, '/' ) );
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

		$user_name = sanitize_title( get_comment_author( $comment ) );
		$host      = webmention_get_user_domain( $comment );
		$avatar    = self::sideload_avatar( $avatar, $host, $user_name );

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
	 * Function to retrieve default avatar URL
	 *
	 *
	 * @param string $type Default Avatar URL
	 *
	 * @return string|boolean $url
	 */
	public static function get_default_avatar( $type = null ) {
		if ( ! $type ) {
			$type = get_option( 'avatar_default', 'mystery' );
		}

		switch ( $type ) {
			case 'mm':
			case 'mystery':
			case 'mysteryman':
				return plugin_dir_url( dirname( __FILE__ ) ) . 'img/mm.jpg';
		}

		return apply_filters( 'webmention_default_avatar', $type );
	}

	/**
	 * Function to check if there is a gravatar
	 *
	 *
	 * @param WP_Comment $comment
	 *
	 * @return boolean
	 */
	public static function check_gravatar( $comment, $args = null ) {
		if ( ! empty( $comment->comment_author_email ) ) {
			$hash = md5( strtolower( trim( $comment->comment_author_email ) ) );
		} elseif ( is_array( $args ) && array_key_exists( 'url', $args ) ) {
			if ( ! strpos( $args['url'], 'gravatar.com' ) ) {
				return false;
			}
			$hash = wp_parse_url( $args['url'], PHP_URL_PATH );
			$hash = str_replace( '/avatar/', '', $hash );
		} else {
			return false;
		}

		$found = get_transient( 'webmention_gravatar_' . $hash );

		if ( false !== $found ) {
			return $found;
		} else {
			$url      = 'https://www.gravatar.com/avatar/' . $hash . '?d=404';
			$response = wp_remote_head( $url );
			$found    = ( is_wp_error( $response ) || 404 === wp_remote_retrieve_response_code( $response ) ) ? 0 : 1;
			set_transient( 'webmention_gravatar_' . $hash, $found, WEBMENTION_GRAVATAR_CACHE_TIME );
		}

		return $found;
	}

	/**
	 * Replaces the default avatar with a locally stored default
	 *
	 * @param array      $args    Arguments passed to get_avatar_data(), after processing.
	 * @param WP_Comment $comment A comment object
	 *
	 * @return array $args
	 */
	public static function anonymous_avatar_data( $args, $comment ) {
		if ( ! $comment instanceof WP_Comment ) {
			return $args;
		}

		$local = apply_filters( 'webmention_local_avatars', array( 'mm', 'mystery', 'mysteryman' ) );

		if ( ! in_array( $args['default'], $local, true ) ) {
			return $args;
		}

		// Always override if default forced
		if ( $args['force_default'] ) {
			$args['url'] = self::get_default_avatar( $args['default'] );
			return $args;
		}

		if ( ! strpos( $args['url'], 'gravatar.com' ) ) {
			return $args;
		}

		if ( self::check_gravatar( $comment, $args ) ) {
			return $args;
		}

		$args['url'] = self::get_default_avatar();

		return $args;
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
