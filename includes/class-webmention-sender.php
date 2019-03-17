<?php
/**
 * Webmention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class Webmention_Sender {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		// a pseudo hook so you can run a do_action('send_webmention')
		// instead of calling Webmention_Sender::send_webmention
		add_action( 'send_webmention', array( 'Webmention_Sender', 'send_webmention' ), 10, 2 );

		// run webmentions before the other pinging stuff
		add_action( 'do_pings', array( 'Webmention_Sender', 'do_webmentions' ), 5, 1 );

		// Send Webmentions from Every Type that Declared Webmention Support
		$post_types = get_post_types_by_support( 'webmentions' );
		foreach ( $post_types as $post_type ) {
			add_action( 'publish_' . $post_type, array( 'Webmention_Sender', 'publish_hook' ) );
		}

		add_action( 'comment_post', array( 'Webmention_Sender', 'comment_post' ) );

		// remote delete posts
		add_action( 'trashed_post', array( 'Webmention_Sender', 'trash_hook' ) );
		add_action( 'webmention_delete', array( 'Webmention_Sender', 'send_webmentions' ) );
	}

	/**
	 * Marks the post as "no webmentions sent yet"
	 *
	 * @param int $post_id
	 */
	public static function publish_hook( $post_id ) {
		// check if pingbacks are enabled
		if ( get_option( 'default_pingback_flag' ) ) {
			add_post_meta( $post_id, '_mentionme', '1', true );
		}
	}

	/**
	 * send webmentions on new comments
	 *
	 * @param int $id the post id
	 * @param obj $comment the comment object
	 */
	public static function comment_post( $id ) {
		$comment = get_comment( $id );

		// check parent comment
		if ( $comment->comment_parent ) {
			// get parent comment...
			$parent = get_comment( $comment->comment_parent );
			// ...and gernerate target url
			$target = $parent->comment_author_url;

			if ( $target ) {
				$source = add_query_arg( 'replytocom', $comment->comment_ID, get_permalink( $comment->comment_post_ID ) );

				do_action( 'send_webmention', $source, $target );
			}
		}
	}

	/**
	 * Send Webmention delete
	 *
	 * @param int $post_id
	 */
	public static function trash_hook( $post_id ) {
		wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'webmention_delete', array( $post_id ) );
	}

	/**
	 * Send Webmentions
	 *
	 * @param string $source source url
	 * @param string $target target url
	 * @param int $post_id the post_ID (optional)
	 *
	 * @return array of results including HTTP headers
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

		// discover the webmention endpoint
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
	 *   add_action('publish_post', array('Webmention_Sender', 'send_webmentions'));
	 * </code>
	 *
	 * @param int $post_id the post_ID
	 */
	public static function send_webmentions( $post_id ) {
		// get source url
		$source = get_permalink( $post_id );

		// remove `__trashed` from the url
		$source = str_replace( '__trashed', '', $source );

		// get post
		$post = get_post( $post_id );

		// initialize links array
		$links = wp_extract_urls( $post->post_content );

		// filter links
		$targets = apply_filters( 'webmention_links', $links, $post_id );
		$targets = array_unique( $targets );
		$pung    = get_pung( $post );
		$ping    = array();

		foreach ( $targets as $target ) {
			// send webmention
			$response = self::send_webmention( $source, $target, $post_id );

			// check response
			if ( ! is_wp_error( $response ) &&
				wp_remote_retrieve_response_code( $response ) < 400 ) {
				// if not already added to punged urls
				if ( ! in_array( $target, $pung, true ) ) {
					// tell the pingback function not to ping these links again
					$ping[] = $target;
				}
			}

			// reschedule if server responds with a http error 5xx
			if ( wp_remote_retrieve_response_code( $response ) >= 500 ) {
				self::reschedule( $post_id );
			}
		}

		if ( ! empty( $ping ) ) {
			add_ping( $post, $ping );
		}
	}

	/**
	 * Reschedule Webmentions on HTTP code 500
	 *
	 * @param int $post_id the post id
	 */
	public static function reschedule( $post_id ) {
		$tries = get_post_meta( $post_id, '_mentionme_tries', true );

		// check "tries" and set to 0 if null
		if ( ! $tries ) {
			$tries = 0;
		}

		// raise "tries" counter
		$tries++;

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
	 * Do webmentions
	 */
	public static function do_webmentions() {
		// The Ultimate Category Excluder plugin filters get_posts to hide
		// user-defined categories, but we're not displaying posts here, so
		// temporarily disable it.
		if ( function_exists( 'ksuce_exclude_categories' ) ) {
			remove_filter( 'pre_get_posts', 'ksuce_exclude_categories' );
		}

		$mentions = get_posts(
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

		if ( empty( $mentions ) ) {
			return;
		}

		foreach ( $mentions as $mention ) {
			delete_post_meta( $mention, '_mentionme' );
			// send them Webmentions
			self::send_webmentions( $mention );
		}
	}
}
