<?php

namespace Webmention;

use WP_Comment;

/**
 * Avatar Handler Class
 *
 */
class Avatar {
	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		add_filter( 'pre_get_avatar_data', array( static::class, 'avatar_stored_in_comment' ), 30, 2 );

		// Allow for avatars on Webmention comment types
		if ( 0 !== (int) get_option( 'webmention_avatars', 1 ) ) {
			add_filter( 'get_avatar_comment_types', array( static::class, 'get_avatar_comment_types' ), 99 );
		}
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

		// Simple method to prevent broken images
		$args['extra_attr'] .= sprintf( ' onerror="this.onerror=null;this.src=\'%1$s\';this.srcset=\'%1$s\';"', WEBMENTION_PLUGIN_URL . 'assets/img/mm.jpg' );

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
				$args['url'] = $avatar;
			}

			if ( ! isset( $args['class'] ) || ! is_array( $args['class'] ) ) {
				$args['class'] = array( 'local-avatar' );
			} else {
				$args['class'][] = 'local-avatar';
				$args['class']   = array_unique( $args['class'] );
			}
			$args['found_avatar'] = true;
		}

		return $args;
	}

	/**
	 * Show avatars on Webmentions if set
	 *
	 * @param array $types list of avatar enabled comment types
	 *
	 * @return array show avatars on Webmentions
	 */
	public static function get_avatar_comment_types( $types ) {
		$comment_types = get_webmention_comment_type_names();
		$types         = array_merge( $types, $comment_types );

		return array_unique( $types );
	}
}
