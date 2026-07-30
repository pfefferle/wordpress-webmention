<?php

namespace Webmention;

use WP_Error;
use WP_Post;

/**
 * Webmention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class Sender {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		// a pseudo hook so you can run a do_action('send_webmention')
		// instead of calling \Webmention\Sender::send_webmention
		add_action( 'send_webmention', array( static::class, 'send_webmention' ), 10, 2 );

		// run webmentions before the other pinging stuff
		add_action( 'do_pings', array( static::class, 'do_webmentions' ), 5, 1 );

		// Send Webmentions from Every Type that Declared Webmention Support
		$post_types = get_post_types_by_support( 'webmentions' );
		foreach ( $post_types as $post_type ) {
			add_action( 'save_' . $post_type, array( static::class, 'save_hook' ), 3 );
			add_action( 'publish_' . $post_type, array( static::class, 'publish_hook' ), 3 );
			add_action( 'trashed_' . $post_type, array( static::class, 'trash_hook' ) );
		}

		add_action( 'wp_trash_post', array( self::class, 'trash_post' ), 1 );
		add_action( 'untrash_post', array( self::class, 'untrash_post' ), 1 );

		add_action( 'comment_post', array( static::class, 'comment_post' ) );

		// remote delete posts
		add_action( 'webmention_delete', array( static::class, 'send_webmentions' ) );
		self::register_meta();
	}

	/**
	 * Register post meta.
	 */
	public static function register_meta() {
		$post_types = \get_post_types_by_support( 'webmentions' );
		foreach ( $post_types as $post_type ) {
			\register_post_meta(
				$post_type,
				'webmentions_disabled_pings',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'boolean',
				)
			);
		}
	}

	/**
	 * Saves optional settings on save.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_hook( $post_id ) {
		// Verify nonce.
		if ( ! isset( $_POST['webmention_post_nonce'] ) || ! \wp_verify_nonce( $_POST['webmention_post_nonce'], 'webmention_post_metabox' ) ) {
			return;
		}

		// Check user permissions.
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['webmentions_disabled_pings'] ) ) {
			\update_post_meta( $post_id, 'webmentions_disabled_pings', (bool) $_POST['webmentions_disabled_pings'] );
		}
	}

	/**
	 * Marks the post as "no Webmentions sent yet"
	 *
	 * @param int $post_id Post ID.
	 */
	public static function publish_hook( $post_id ) {
		if ( \get_post_meta( $post_id, 'webmentions_disabled_pings', 1 ) ) {
			return;
		}

		\add_post_meta( $post_id, '_mentionme', '1', true );
		// Post Types Other than Post Do Not Trigger Pings. This will unless it is already scheduled.
		if ( ! wp_next_scheduled( 'do_pings' ) ) {
			wp_schedule_single_event( time(), 'do_pings' );
		}
	}

	/**
	 * Send Webmentions on new comments.
	 *
	 * @param int $id the post id.
	 */
	public static function comment_post( $id ) {
		$comment = get_comment( $id );

		if ( ! $comment ) {
			return;
		}

		// check parent comment
		if ( $comment->comment_parent ) {
			// get parent comment...
			$parent = get_comment( $comment->comment_parent );
			// ...and gernerate target url
			$target = get_url_from_webmention( $parent );

			if ( $target ) {
				$source = add_query_arg( 'replytocom', $comment->comment_ID, get_permalink( $comment->comment_post_ID ) );

				do_action( 'send_webmention', $source, $target );
			}
		}
	}

	/**
	 * Send Webmention delete.
	 *
	 * @param int $post_id Trashed post ID.
	 */
	public static function trash_hook( $post_id ) {
		wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'webmention_delete', array( $post_id ) );
	}

	/**
	 * Store permalink in meta, to send delete Webmention.
	 *
	 * @param string $post_id The Post ID.
	 *
	 * @return void
	 */
	public static function trash_post( $post_id ) {
		add_post_meta(
			$post_id,
			'_webmention_canonical_url',
			get_permalink( $post_id ),
			true
		);
	}

	/**
	 * Delete permalink from meta
	 *
	 * @param string $post_id The Post ID
	 *
	 * @return void
	 */
	public static function untrash_post( $post_id ) {
		delete_post_meta( $post_id, '_webmention_canonical_url' );
	}

	/**
	 * Send Webmentions.
	 *
	 * @param string $source source url.
	 * @param string $target target url.
	 * @param int    $post_id the post_ID (optional).
	 *
	 * @return array|WP_Error array of results including HTTP headers or WP_Error object if failed.
	 */
	public static function send_webmention( $source, $target, $post_id = null ) {
		// stop selfpings on the same URL
		if ( ( 1 === (int) get_option( 'webmention_disable_selfpings_same_url' ) ) && ( $source === $target ) ) {
			return false;
		}

		// stop selfpings on the same domain
		if ( ( 1 === (int) get_option( 'webmention_disable_selfpings_same_domain' ) ) && ( wp_parse_url( $source, PHP_URL_HOST ) === wp_parse_url( $target, PHP_URL_HOST ) ) ) {
			return false;
		}

		// discover the Webmention endpoint
		$webmention_server_url = webmention_discover_endpoint( $target );

		// if I can't find an endpoint, perhaps you can!
		$webmention_server_url = apply_filters( 'webmention_server_url', $webmention_server_url, $target );

		$wp_version = get_bloginfo( 'version' );

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 1048576,
			'redirection'         => 20,
			'user-agent'          => "$user_agent; sending Webmention",
			'headers'             => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
		);

		$body = array(
			'source' => rawurlencode( $source ),
			'target' => rawurlencode( $target ),
		);

		// Allows for additional URL parameters to be added such as Vouch.
		$body         = apply_filters( 'webmention_send_vars', $body, $post_id );
		$args['body'] = build_query( $body );

		if ( $webmention_server_url ) {
			$response = wp_safe_remote_post( $webmention_server_url, $args );
		} else {
			$response = false;
		}

		// use the response to do something useful such as logging success or failure.
		do_action( 'webmention_post_send', $response, $source, $target, $post_id );

		return $response;
	}

	/**
	 * Send Webmentions if new Post was saved
	 *
	 * You can still hook this function directly into the `publish_post` action:
	 *
	 * <code>
	 *   add_action( 'publish_post', array( \Webmention\Sender, 'send_webmentions' ) );
	 * </code>
	 *
	 * @param int $post_id the post_ID.
	 *
	 * @return array|bool array of results or false if failed.
	 */
	public static function send_webmentions( $post_id ) {
		if ( \get_post_meta( $post_id, 'webmentions_disabled_pings', 1 ) ) {
			return;
		}

		$source = get_post_meta( $post_id, '_webmention_canonical_url', true );

		if ( ! $source ) {
			$source = get_permalink( $post_id );
		}

		// get post
		$post = get_post( $post_id );

		// check if post exists
		if ( ! $post ) {
			return false;
		}

		$support_media_urls = ( 0 === get_option( 'webmention_send_media_mentions' ) );

		// initialize links array
		$urls = webmention_extract_urls( $post->post_content, $support_media_urls );

		// filter links
		$targets   = apply_filters( 'webmention_links', $urls, $post_id );
		$targets   = array_unique( $targets );
		$mentioned = get_post_meta( $post->ID, '_webmentioned', true );
		$mentioned = empty( $mentioned ) ? array() : $mentioned;

		// Detect whether the post content changed since the last send. Re-sending the
		// same, unchanged post to already-notified targets caused the repeated
		// Webmentions reported in https://github.com/pfefferle/wordpress-webmention/issues/619.
		$content_hash    = md5( $post->post_content );
		$last_hash       = get_post_meta( $post->ID, '_webmention_content_hash', true );
		$content_changed = ( $content_hash !== $last_hash );

		// Find previously sent Webmentions that are no longer linked and send them one last time.
		$deletes = array_diff( $mentioned, $targets );

		// When the content changed, notify every current target so receivers re-fetch the
		// updated post. Otherwise only (re)send to targets we have not yet confirmed as sent,
		// which lets HTTP 5xx retries through while skipping already-notified targets.
		if ( $content_changed ) {
			$to_send = $targets;
		} else {
			$to_send = array_diff( $targets, $mentioned );
		}

		$mentions = array();

		foreach ( $to_send as $target ) {
			// send Webmention
			$response = self::send_webmention( $source, $target, $post_id );

			if ( ! $response ) {
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );

			// check response
			if ( ! is_wp_error( $response ) && $code < 400 ) {
				$mentions[] = $target;
			} elseif ( $code >= 500 ) {
				// reschedule if server responds with a http error 5xx
				self::reschedule( $post_id );
			}
		}

		// Deletes that fail with a 5xx are retained so the delete is retried next run.
		$retained_deletes = array();

		foreach ( $deletes as $deleted ) {
			// send delete Webmention
			$response = self::send_webmention( $source, $deleted, $post_id );

			// reschedule if server responds with a http error 5xx
			if ( wp_remote_retrieve_response_code( $response ) >= 500 ) {
				self::reschedule( $post_id );
				$retained_deletes[] = $deleted;
			}
		}

		// On a content change every target is re-sent, so only targets confirmed this run
		// count as notified; a previously-notified target that fails must be retried, not
		// carried forward. On unchanged content the targets we did not re-send this run
		// remain notified.
		$carried = $content_changed ? array() : array_intersect( $mentioned, $targets );

		// Persist the set of notified targets: carried-forward targets, plus targets
		// notified this run, plus deletes still awaiting retry.
		$notified = array_values(
			array_unique(
				array_merge(
					$carried,
					$mentions,
					$retained_deletes
				)
			)
		);

		update_post_meta( $post->ID, '_webmentioned', $notified );
		update_post_meta( $post->ID, '_webmention_content_hash', $content_hash );

		// Keep WordPress' own `pinged` list in sync so the core pinging subsystem does
		// not treat these links as un-pinged.
		if ( ! empty( $notified ) ) {
			$pung = get_pung( $post );
			self::update_ping( $post, array_values( array_unique( array_merge( $pung, $notified ) ) ) );
		}

		return $mentions;
	}

	public static function update_ping( $post_id, $pinged ) {
		global $wpdb;
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( ! is_array( $pinged ) ) {
			return false;
		}

		$new = implode( "\n", $pinged );

		$wpdb->update( $wpdb->posts, array( 'pinged' => $new ), array( 'ID' => $post->ID ) );
		clean_post_cache( $post->ID );

		return $new;
	}

	/**
	 * Reschedule Webmentions on HTTP code 500
	 *
	 * @param int $post_id the post id.
	 */
	public static function reschedule( $post_id ) {
		$tries = get_post_meta( $post_id, '_mentionme_tries', true );

		// check "tries" and set to 0 if null
		if ( ! $tries ) {
			$tries = 0;
		}

		// raise "tries" counter
		++$tries;

		// rescedule only three times
		if ( $tries <= 3 ) {
			// save new tries value
			update_post_meta( $post_id, '_mentionme_tries', $tries );

			// and rescedule
			add_post_meta( $post_id, '_mentionme', '1', true );

			wp_schedule_single_event( time() + ( $tries * 900 ), 'do_pings' );
		} else {
			delete_post_meta( $post_id, '_mentionme_tries' );
		}
	}

	/**
	 * Do Webmentions
	 */
	public static function do_webmentions() {
		// The Ultimate Category Excluder plugin filters get_posts to hide
		// user-defined categories, but we're not displaying posts here, so
		// temporarily disable it.
		if ( function_exists( 'ksuce_exclude_categories' ) ) {
			remove_filter( 'pre_get_posts', 'ksuce_exclude_categories' );
		}

		$post_ids = get_posts(
			array(
				'meta_key'  => '_mentionme',
				'post_type' => get_post_types_by_support( 'webmentions' ),
				'fields'    => 'ids',
				'nopaging'  => true,
			)
		);

		if ( function_exists( 'ksuce_exclude_categories' ) ) {
			add_filter( 'pre_get_posts', 'ksuce_exclude_categories' );
		}

		if ( empty( $post_ids ) ) {
			return;
		}

		foreach ( $post_ids as $post_id ) {
			\delete_post_meta( $post_id, '_mentionme' );
			// send them Webmentions
			self::send_webmentions( $post_id );
		}
	}
}
