<?php

/**
 * Notifications Class
 *
 */
class Webmention_Notifications {
	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		$cls = get_called_class();

		// Replace hooks with custom hook
		remove_action( 'comment_post', 'wp_new_comment_notify_moderator' );
		remove_action( 'comment_post', 'wp_new_comment_notify_postauthor' );
		add_action( 'comment_post', array( $cls, 'wp_new_comment_notify_moderator' ) );
		add_action( 'comment_post', array( $cls, 'wp_new_comment_notify_postauthor' ) );

		if ( WP_DEBUG ) {
			// For testing outgoing comment email
			add_filter( 'bulk_actions-edit-comments', array( $cls, 'register_bulk_send' ) );
			add_filter( 'handle_bulk_actions-edit-comments', array( $cls, 'bulk_send_notifications' ), 10, 3 );
		}
	}

	/**
	 * Register Bulk send outgoing email
	 */
	public static function register_bulk_send( $bulk_actions ) {
		$bulk_actions['resend_notification_email'] = __( 'Resend Comment Email', 'webmention' );
		$bulk_actions['resend_moderation_email']   = __( 'Resend Moderation Email', 'webmention' );
		return $bulk_actions;
	}

	/**
	 * Handle Bulk Send Outgoing EMail
	 */
	public static function bulk_send_notifications( $redirect_to, $doaction, $comment_ids ) {
		if ( ! in_array( $doaction, array( 'resend_notification_email', 'resend_moderation_email' ), true ) ) {
			return $redirect_to;
		}
		foreach ( $comment_ids as $comment_id ) {
			switch ( $doaction ) {
				case 'resend_notification_email':
					self::wp_new_comment_notify_postauthor( $comment_id );
					break;
				case 'resend_moderation_email':
					self::wp_notify_moderator( $comment_id );
					break;
			}
		}
		return $redirect_to;
	}

	/*
	 * Returns a text based summary of a comment/webmention for the purpose of display
	 */

	public static function get_comment_details( $comment ) {

		/* translators: %s: Comment Type */
		$message = sprintf( __( 'Comment Type: %s', 'webmention' ), get_webmention_comment_string( $comment ) ) . "\r\n";
		/* translators: %s: Post Permalink */
		$message .= sprintf( __( 'Permalink: %s', 'webmention' ), get_permalink( $comment->comment_post_ID ) ) . "\r\n\r\n";
		/* translators: %s: Comment Date */
		$message .= sprintf( __( 'Date: %s', 'webmention' ), get_comment_date( DATE_W3C, $comment ) ) . "\r\n\r\n";

		switch ( $comment->comment_type ) {
			default: // Webmentions
				/* translators: %s: comment author's name */
				$message .= sprintf( __( 'Author: %s ', 'webmention' ), get_comment_author( $comment ) ) . "\r\n";
				if ( ! empty( $comment->comment_author_email ) ) {
					/* translators: %s: comment author email */
					$notify_message .= sprintf( __( 'Email: %s', 'webmention' ), $comment->comment_author_email ) . "\r\n";
				}
				/* translators: %s: trackback/pingback/comment author URL */
				$message .= sprintf( __( 'URL: %s', 'webmention' ), $comment->comment_author_url ) . "\r\n";
				/* translators: %s: comment text */
				$message .= sprintf( __( 'Comment: %s', 'webmention' ), "\r\n" . get_comment_text( $comment ) ) . "\r\n\r\n";
				break;
		}
		return apply_filters( 'webmention_comment_details', $message, $comment );

	}


	public static function wp_new_comment_notify_postauthor( $comment ) {
		$comment = get_comment( $comment );

		$maybe_notify = get_option( 'comments_notify' );

		/**
		 * Filters whether to send the post author new comment notification emails,
		 * overriding the site setting.
		 *
		 * @since 4.4.0
		 *
		 * @param bool $maybe_notify Whether to notify the post author about the new comment.
		 * @param int  $comment_ID   The ID of the comment for the notification.
		 */
		$maybe_notify = apply_filters( 'notify_post_author', $maybe_notify, $comment->comment_ID );

		/*
		 * wp_notify_postauthor() checks if notifying the author of their own comment.
		 * By default, it won't, but filters can override this.
		 */
		if ( ! $maybe_notify ) {
			return false;
		}

		// Only send notifications for approved comments.
		if ( ! isset( $comment->comment_approved ) || '1' !== $comment->comment_approved ) {
			return false;
		}

		if ( is_webmention_comment_type( $comment ) ) {
			return self::wp_notify_postauthor( $comment );
		}
		return wp_notify_postauthor( $comment );
	}

	public static function wp_new_comment_notify_moderator( $comment ) {
		$comment = get_comment( $comment );

		// Only send notifications for pending comments.
		$maybe_notify = ( '0' === $comment->comment_approved );

		/** This filter is documented in wp-includes/comment.php */
		$maybe_notify = apply_filters( 'notify_moderator', $maybe_notify, $comment->comment_ID );

		if ( ! $maybe_notify ) {
			return false;
		}

		if ( is_webmention_comment_type( $comment ) ) {
			return self::wp_notify_moderator( $comment );
		}

		return wp_notify_moderator( $comment );
	}

	public static function wp_notify_postauthor( $comment ) {
		$comment = get_comment( $comment );
		if ( empty( $comment ) || empty( $comment->comment_post_ID ) ) {
				return false;
		}

		$post   = get_post( $comment->comment_post_ID );
		$author = get_userdata( $post->post_author );

		// Who to notify? By default, just the post author, but others can be added.
		$emails = array();
		if ( $author ) {
			$emails[] = $author->user_email;
		}

		/**
		 * Filters the list of email addresses to receive a comment notification.
		 *
		 * By default, only post authors are notified of comments. This filter allows
		 * others to be added.
		 *
		 * @since 3.7.0
		 *
		 * @param array $emails     An array of email addresses to receive a comment notification.
		 * @param int   $comment_id The comment ID.
		 */
		$emails = apply_filters( 'comment_notification_recipients', $emails, $comment->comment_ID );
		$emails = array_filter( $emails );

		// If there are no addresses to send the comment to, bail.
		if ( ! count( $emails ) ) {
			return false;
		}

		// Facilitate unsetting below without knowing the keys.
		$emails = array_flip( $emails );

		/**
		 * Filters whether to notify comment authors of their comments on their own posts.
		 *
		 * By default, comment authors aren't notified of their comments on their own
		 * posts. This filter allows you to override that.
		 *
		 * @since 3.8.0
		 *
		 * @param bool $notify     Whether to notify the post author of their own comment.
		 *                         Default false.
		 * @param int  $comment_id The comment ID.
		 */
		$notify_author = apply_filters( 'comment_notification_notify_author', false, $comment->comment_ID );

		// The comment was left by the author
		if ( $author && ! $notify_author && $comment->user_id === $post->post_author ) {
			unset( $emails[ $author->user_email ] );
		}

		// The author moderated a comment on their own post
		if ( $author && ! $notify_author && get_current_user_id() === $post->post_author ) {
			unset( $emails[ $author->user_email ] );
		}

		// The post author is no longer a member of the blog
		if ( $author && ! $notify_author && ! user_can( $post->post_author, 'read_post', $post->ID ) ) {
			unset( $emails[ $author->user_email ] );
		}

		// If there's no email to send the comment to, bail, otherwise flip array back around for use below
		if ( ! count( $emails ) ) {
			return false;
		} else {
			$emails = array_flip( $emails );
		}

		$switched_locale = switch_to_locale( get_locale() );

		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname        = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		/* translators: %s: post title */
		$notify_message = sprintf( __( 'New response to your post "%s"', 'webmention' ), $post->post_title ) . "\r\n";

		$notify_message .= self::get_comment_details( $comment );

		if ( user_can( $post->post_author, 'edit_comment', $comment->comment_ID ) ) {
			if ( EMPTY_TRASH_DAYS ) {
				/* translators: Comment moderation. %s: Comment action URL */
				$notify_message .= sprintf( __( 'Trash it: %s', 'default' ), admin_url( "comment.php?action=trash&c={$comment->comment_ID}#wpbody-content" ) ) . "\r\n";
			} else {
				/* translators: Comment moderation. %s: Comment action URL */
				$notify_message .= sprintf( __( 'Delete it: %s', 'default' ), admin_url( "comment.php?action=delete&c={$comment->comment_ID}#wpbody-content" ) ) . "\r\n";
			}
			/* translators: Comment moderation. %s: Comment action URL */
			$notify_message .= sprintf( __( 'Spam it: %s', 'default' ), admin_url( "comment.php?action=spam&c={$comment->comment_ID}#wpbody-content" ) ) . "\r\n";
		}

		$wp_email = 'wordpress@' . preg_replace( '#^www\.#', '', strtolower( $_SERVER['SERVER_NAME'] ) );

		if ( '' === $comment->comment_author ) {
			$from = "From: \"$blogname\" <$wp_email>";
			if ( '' !== $comment->comment_author_email ) {
				$reply_to = "Reply-To: $comment->comment_author_email";
			}
		} else {
			$from = "From: \"$comment->comment_author\" <$wp_email>";
			if ( '' !== $comment->comment_author_email ) {
				$reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
			}
		}

		$message_headers = "$from\n" . 'Content-Type: text/plain; charset="' . get_option( 'blog_charset' ) . "\"\n";

		if ( isset( $reply_to ) ) {
			$message_headers .= $reply_to . "\n";
		}

		/**
		 * Filters the comment notification email text.
		 *
		 * @since 1.5.2
		 *
		 * @param string $notify_message The comment notification email text.
		 * @param int    $comment_id     Comment ID.
		 */
		$notify_message = apply_filters( 'comment_notification_text', $notify_message, $comment->comment_ID );

		/**
		 * Filters the comment notification email subject.
		 *
		 * @since 1.5.2
		 *
		 * @param string $subject    The comment notification email subject.
		 * @param int    $comment_id Comment ID.
		 */
		$subject = apply_filters( 'comment_notification_subject', $subject, $comment->comment_ID );

		/**
		 * Filters the comment notification email headers.
		 *
		 * @since 1.5.2
		 *
		 * @param string $message_headers Headers for the comment notification email.
		 * @param int    $comment_id      Comment ID.
		 */
		$message_headers = apply_filters( 'comment_notification_headers', $message_headers, $comment->comment_ID );

		foreach ( $emails as $email ) {
			@wp_mail( $email, wp_specialchars_decode( $subject ), $notify_message, $message_headers );
		}

		if ( $switched_locale ) {
			restore_previous_locale();
		}
		return true;
	}


	public static function wp_notify_moderator( $comment_id ) {
		global $wpdb;

		$maybe_notify = get_option( 'moderation_notify' );

		/**
		 * Filters whether to send the site moderator email notifications, overriding the site setting.
		 *
		 * @since 4.4.0
		 *
		 * @param bool $maybe_notify Whether to notify blog moderator.
		 * @param int  $comment_ID   The id of the comment for the notification.
		 */
		$maybe_notify = apply_filters( 'notify_moderator', $maybe_notify, $comment_id );

		if ( ! $maybe_notify ) {
			return true;
		}

		$comment = get_comment( $comment_id );
		$post    = get_post( $comment->comment_post_ID );
		$user    = get_userdata( $post->post_author );
		// Send to the administration and to the post author if the author can modify the comment.
		$emails = array( get_option( 'admin_email' ) );
		if ( $user && user_can( $user->ID, 'edit_comment', $comment_id ) && ! empty( $user->user_email ) ) {
			if ( 0 !== strcasecmp( $user->user_email, get_option( 'admin_email' ) ) ) {
				$emails[] = $user->user_email;
			}
		}

		$switched_locale = switch_to_locale( get_locale() );

		$comments_waiting = $wpdb->get_var( "SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'" );

		// The blogname option is escaped with esc_html on the way into the database in sanitize_option
		// we want to reverse this for the plain text arena of emails.
		$blogname        = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		/* translators: %s: post title */
		$notify_message = sprintf( __( 'A new response to the post "%s" is waiting for your approval', 'webmention' ), get_the_title( $post ) ) . "\r\n";

		$notify_message .= self::get_comment_details( $comment );

		/* translators: Comment moderation. %s: Comment action URL */
		$notify_message .= sprintf( __( 'Approve it: %s', 'default' ), admin_url( "comment.php?action=approve&c={$comment_id}#wpbody-content" ) ) . "\r\n";

		if ( EMPTY_TRASH_DAYS ) {
			/* translators: Comment moderation. %s: Comment action URL */
			$notify_message .= sprintf( __( 'Trash it: %s', 'default' ), admin_url( "comment.php?action=trash&c={$comment_id}#wpbody-content" ) ) . "\r\n";
		} else {
			/* translators: Comment moderation. %s: Comment action URL */
			$notify_message .= sprintf( __( 'Delete it: %s', 'default' ), admin_url( "comment.php?action=delete&c={$comment_id}#wpbody-content" ) ) . "\r\n";
		}

		/* translators: Comment moderation. %s: Comment action URL */
		$notify_message .= sprintf( __( 'Spam it: %s', 'default' ), admin_url( "comment.php?action=spam&c={$comment_id}#wpbody-content" ) ) . "\r\n";

		$notify_message .= sprintf(
			/* translators: Comment moderation. %s: Number of comments awaiting approval */
			_n(
				'Currently %s response is waiting for approval. Please visit the moderation panel:',
				'Currently %s responses are waiting for approval. Please visit the moderation panel:',
				$comments_waiting,
				'webmention'
			),
			number_format_i18n( $comments_waiting )
		) . "\r\n";
		$notify_message .= admin_url( 'edit-comments.php?comment_status=moderated#wpbody-content' ) . "\r\n";

		/* translators: Comment moderation notification email subject. 1: Site name, 2: Post title */
		$subject         = sprintf( __( '[%1$s] Please moderate: "%2$s"', 'default' ), $blogname, $post->post_title );
		$message_headers = '';

		/**
		 * Filters the list of recipients for comment moderation emails.
		 *
		 * @since 3.7.0
		 *
		 * @param array $emails     List of email addresses to notify for comment moderation.
		 * @param int   $comment_id Comment ID.
		 */
		$emails = apply_filters( 'comment_moderation_recipients', $emails, $comment_id );

		/**
		 * Filters the comment moderation email text.
		 *
		 * @since 1.5.2
		 *
		 * @param string $notify_message Text of the comment moderation email.
		 * @param int    $comment_id     Comment ID.
		 */
		$notify_message = apply_filters( 'comment_moderation_text', $notify_message, $comment_id );

		/**
		 * Filters the comment moderation email subject.
		 *
		 * @since 1.5.2
		 *
		 * @param string $subject    Subject of the comment moderation email.
		 * @param int    $comment_id Comment ID.
		 */
		$subject = apply_filters( 'comment_moderation_subject', $subject, $comment_id );

		/**
		 * Filters the comment moderation email headers.
		 *
		 * @since 2.8.0
		 *
		 * @param string $message_headers Headers for the comment moderation email.
		 * @param int    $comment_id      Comment ID.
		 */
		$message_headers = apply_filters( 'comment_moderation_headers', $message_headers, $comment_id );

		foreach ( $emails as $email ) {
			@wp_mail( $email, wp_specialchars_decode( $subject ), $notify_message, $message_headers );
		}

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return true;
	}



}
