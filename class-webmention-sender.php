<?php

/**
 * A wrapper for Webmention_Sender::send_Webmention
 *
 * @param string $source source url
 * @param string $target target url
 *
 * @return array of results including HTTP headers
 */
function send_webmention( $source, $target ) {
	return Webmention_Sender::send_webmention( $source, $target );
}

// initialize plugin
add_action( 'init', array( 'Webmention_Sender', 'init' ) );

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

		// run Webmentions before the other pinging stuff
		add_action( 'do_pings', array( 'Webmention_Sender', 'do_webmentions' ), 5, 1 );

		add_action( 'publish_post', array( 'Webmention_Sender', 'publish_hook' ) );
		add_action( 'publish_page', array( 'Webmention_Sender', 'publish_hook' ) );
    //send salmention when comment on post approved
    add_action('transition_comment_status', array( 'Webmention_Sender', 'comment_transition' ), 10, 3);

	}

	/**
	* Is This URL Valid
	*
	* Runs a validity check on the URL. Based on built-in WordPress Validations
	*
	* @param $url URL to fetch
	*
	* @return boolean
	**/
	public static function is_valid_url( $url ) {
		$original_url = $url;
		$url = wp_kses_bad_protocol( $url, array( 'http', 'https' ) );
		if ( ! $url || strtolower( $url ) !== strtolower( $original_url ) ) {
			return false; }
		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url || empty( $parsed_url['host'] ) ) {
			return false; }
		if ( isset( $parsed_url['user'] ) || isset( $parsed_url['pass'] ) ) {
			return false; }
		return true;
	}

	/**
	 * Marks the post as "no Webmentions sent yet"
	 *
	 * @param int $post_ID
	 */
	public static function publish_hook( $post_ID ) {
		// check if pingbacks are enabled
		if ( get_option( 'default_pingback_flag' ) ) {
			add_post_meta( $post_ID, '_mentionme', '1', true );
		}
	}

	public static function comment_transition($new_status, $old_status, $comment) {
		if($old_status != $new_status) {
			if($new_status == 'approved') {
        self::publish_hook( $comment->comment_post_ID );
			}
		}
	}



	/**
	* Fires on Deleted Webmentions
	*
	* @param int $post_ID
	*/
	public static function transition_post_hook( $new, $old, $post ) {
		// check if pingbacks are enabled
		if ( get_option( 'default_pingback_flag' ) ) {
			// For Sending Webmentions on Delete
		}
	}


	/**
	 * Send Webmentions
	 *
	 * @param string $source source url
	 * @param string $target target url
	 * @param int $post_ID the post_ID (optional)
	 *
	 * @return array of results including HTTP headers
	 */
	public static function send_webmention( $source, $target, $post_ID = null ) {
		// stop selfpings on the same URL
		if ( ( get_option( 'webmention_disable_selfpings_same_url' ) === '1' ) &&
			 ( $source === $target ) ) {
			return false;
		}

		// stop selfpings on the same domain
		$disable_selfpings_domain = apply_filters( 'webmention_disable_selfpings', 0 );
		if ( ( 1 == $disable_selfpings_domain) && ( 0 !== url_to_postid( $target ) ) ) {
			return false;
		}

		// discover the Webmention endpoint
		$webmention_server_url = self::discover_endpoint( $target );

		// if I can't find an endpoint, perhaps you can!
		$webmention_server_url = apply_filters( 'webmention_server_url', $webmention_server_url, $target );

		global $wp_version;
		$user_agent = apply_filters( 'http_headers_useragent', 'Webmention (WordPress/' . $wp_version . ')' );

		$args = array(
			'body' => 'source=' . urlencode( $source ) . '&target=' . urlencode( $target ),
			'timeout' => 100,
		'user-agent' => $user_agent,

		);

		if ( $webmention_server_url ) {
			$response = wp_remote_post( $webmention_server_url, $args );

			// use the response to do something usefull
			do_action( 'webmention_post_send', $response, $source, $target, $post_ID );

			return $response;
		}

		return false;
	}

	/**
	 * Send Webmentions if new Post was saved
	 *
	 * You can still hook this function directly into the `publish_post` action:
	 *
	 * <code>
	 *	 add_action('publish_post', array('Webmention_Sender', 'send_webmentions'));
	 * </code>
	 *
	 * @param int $post_ID the post_ID
	 */
	public static function send_webmentions($post_ID) {
		// get source url
		$source = get_permalink( $post_ID );

		// get post
		$post = get_post( $post_ID );

		// initialize links array
		$links = wp_extract_urls( $post->post_content );
		if ( ! $links ) {
			$links = array();
		}

		// filter links
		$targets = apply_filters( 'webmention_links', $links, $post_ID );
		$targets = array_unique( $targets );

		foreach ( $targets as $target ) {
			// send Webmention
			if ( WP_DEBUG ) {
				error_log( 'WEBMENTION SENT: '. $source .  ' -> ' . $target );
			}
			$response = self::send_webmention( $source, $target, $post_ID );

			// check response
			if ( ! is_wp_error( $response ) &&
				wp_remote_retrieve_response_code( $response ) < 400 ) {
				$pung = get_pung( $post_ID );

				// if not already added to punged urls
				if ( ! in_array( $target, $pung ) ) {
					// tell the pingback function not to ping these links again
					add_ping( $post_ID, $target );
				}
			}

			// reschedule if server responds with a http error 5xx or an error code 429
			$code = wp_remote_retrieve_response_code( $response );
			if ( ( $code >= 500 ) || ( 429 == $code ) ) {
				self::reschedule( $post_ID );
			}
		}
	}

	/**
	 * Reschedule Webmentions
	 *
	 * @param int $post_ID the post id
	 */
	public static function reschedule( $post_ID ) {
		$tries = get_post_meta( $post_ID, '_mentionme_tries', true );

		// check "tries" and set to 0 if null
		if ( ! $tries ) {
			$tries = 0;
		}

		// raise "tries" counter
		$tries = $tries + 1;

		// rescedule only three times
		if ( $tries <= 3 ) {
			// save new tries value
			update_post_meta( $post_ID, '_mentionme_tries', $tries );

			// and rescedule
			add_post_meta( $post_ID, '_mentionme', '1', true );

			wp_schedule_single_event( time() + ( $tries * 900 ), 'do_pings' );
		} else {
			delete_post_meta( $post_ID, '_mentionme_tries' );
		}
	}

	/**
	 * Do Webmentions
	 */
	public static function do_webmentions() {
		// get all posts that should be "mentioned"
		$mentions = get_posts( array( 'meta_key' => '_mentionme', 'post_type' => 'any', 'fields' => 'ids', 'nopaging' => true ) );
		if ( empty( $mentions ) ) {
			return;
		}
		foreach ( $mentions as $mention ) {  
					delete_post_meta( $mention , '_mentionme' );
					// send them Webmentions
					self::send_webmentions( $mention );
		}
	}

	/**
	 * Finds a Webmention server URI based on the given URL
	 *
	 * Checks the HTML for the rel=wwebmention" link and headers. It does
	 * a check for the link headers first and returns them, if available. The
	 * check for the html headers has more overhead than just the link header.
	 *
	 * @param string $url URL to ping
	 *
	 * @return bool|string False on failure, string containing URI on success
	 */
	public static function discover_endpoint( $url ) {
		global $wp_version;
		$user_agent = apply_filters( 'http_headers_useragent', 'Webmention (WordPress/' . $wp_version . ')' );
		$args = array(
		  'timeout' => 10,
		  'limit_response_size' => 1048576,
		  'redirection' => 20,
		  'user-agent' => $user_agent,
		);
		if ( ! self::is_valid_url( $url ) ) { // Not an URL. This should never happen.
			return false;
		}

		// do not search for a Webmention server on our own uploads
		$uploads_dir = wp_upload_dir();
		if ( 0 === strpos( $url, $uploads_dir['baseurl'] ) ) {
			return false;
		}

		$response = wp_remote_head( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		// check link header
		if ( $links = wp_remote_retrieve_header( $response, 'link' ) ) {
			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention?\/?[\"\']?/i', $link, $result ) ) {
						return WP_Http::make_absolute_url( $result[1], $url );
					}
				}
			} else {
				if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention?\/?[\"\']?/i', $links, $result ) ) {
					return WP_Http::make_absolute_url( $result[1], $url );
				}
			}
		}

		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return false;
		}

		// now do a GET since we're going to look in the html headers (and we're sure its not a binary file)
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$contents = wp_remote_retrieve_body( $response );

		// boost performance and use alreade the header
		$header = substr( $contents, 0, stripos( $contents, '</head>' ) );

		// unicode to HTML entities
		$contents = mb_convert_encoding( $contents, 'HTML-ENTITIES', mb_detect_encoding( $contents ) );

		libxml_use_internal_errors( true );

		$doc = new DOMDocument();
		$doc->loadHTML( $contents );

		$xpath = new DOMXPath( $doc );

		// check <link> elements
		// checks only head-links
		foreach ( $xpath->query( '//head/link[contains(concat(" ", @rel, " "), " webmention ") or contains(@rel, "webmention.org")]/@href' ) as $result ) {
			return WP_Http::make_absolute_url( $result->value, $url );
		}

		// check <a> elements
		// checks only body>a-links
		foreach ( $xpath->query( '//body//a[contains(concat(" ", @rel, " "), " webmention ") or contains(@rel, "webmention.org")]/@href' ) as $result ) {
			return WP_Http::make_absolute_url( $result->value, $url );
		}

		return false;
	}

}

if ( ! function_exists( 'get_webmentions_number' ) ) :
	/**
	 * Return the Number of Webmentions
	 *
	 * @param int $post_id The post ID (optional)
	 *
	 * @return int the number of Webmentions for one Post
	 */
	function get_webmentions_number( $post_id = 0 ) {
		$post = get_post( $post_id );

		// change this if your theme can't handle the Webmentions comment type
		$webmention_comment_type = defined( 'WEBMENTION_COMMENT_TYPE' ) ? WEBMENTION_COMMENT_TYPE : 'webmention';
		$comment_type = apply_filters( 'webmention_comment_type', $webmention_comment_type );

		$args = array(
			'post_id' => $post->ID,
			'type'	=> $comment_type,
			'count'	 => true,
			'status'	=> 'approve',
		);

		$comments_query = new WP_Comment_Query;
		return $comments_query->query( $args );
	}
endif;
