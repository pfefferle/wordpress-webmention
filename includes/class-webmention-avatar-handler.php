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
	public static function check_gravatar( $comment ) {
		$hash  = md5( strtolower( trim( $comment->comment_author_email ) ) );
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

		if ( ! empty( $comment->comment_author_email ) && self::check_gravatar( $comment ) ) {
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
		if ( ! is_avatar_comment_type( $comment->comment_type ) || $comment->user_id ) {
			return $args;
		}

		// check if comment has an avatar
		$avatar = self::get_avatar_meta( $comment->comment_ID );

		if ( $avatar ) {
			if ( is_numeric( $avatar ) ) {
				$avatar = wp_get_attachment_url( $avatar, 'full' );
			}

			if ( wp_http_validate_url( $avatar ) ) {
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
