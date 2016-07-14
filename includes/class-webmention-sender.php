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

		add_action( 'publish_post', array( 'Webmention_Sender', 'publish_post_hook' ) );
	}

	/**
	 * Marks the post as "no webmentions sent yet"
	 *
	 * @param int $post_ID
	 */
	public static function publish_post_hook( $post_ID ) {
		// check if pingbacks are enabled
		if ( get_option( 'default_pingback_flag' ) ) {
			add_post_meta( $post_ID, '_mentionme', '1', true );
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
		global $wp_version;

		// stop selfpings on the same URL
		if ( ( get_option( 'webmention_disable_selfpings_same_url' ) === '1' ) &&
			 ( $source === $target ) ) {
			return false;
		}

		// stop selfpings on the same domain
		if ( ( get_option( 'webmention_disable_selfpings_same_domain' ) === '1' ) &&
			 ( parse_url( $source, PHP_URL_HOST ) === parse_url( $target, PHP_URL_HOST ) ) ) {
			return false;
		}

		// discover the webmention endpoint
		$webmention_server_url = self::discover_endpoint( $target );

		// if I can't find an endpoint, perhaps you can!
		$webmention_server_url = apply_filters( 'webmention_server_url', $webmention_server_url, $target );

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 100,
			'limit_response_size' => 1048576,
			'redirection' => 20,
			'user-agent' => "$user_agent; sending Webmention",
			'body' => 'source=' . urlencode( $source ) . '&target=' . urlencode( $target ),
		);
		if ( $webmention_server_url ) {
			$response = wp_remote_post( $webmention_server_url, $args );
		}
		else {
			$response = false;
		}
		// use the response to do something useful
		do_action( 'webmention_post_send', $response, $source, $target, $post_ID );

		return $response;
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
		$links = array();

		// Find all external links in the source
		if ( preg_match_all( '/<a[^>]+href=.(https?:\/\/[^\'\"]+)/i', $post->post_content, $matches ) ) {
			$links = $matches[1];
		}

		// filter links
		$targets = apply_filters( 'webmention_links', $links, $post_ID );
		$targets = array_unique( $targets );

		foreach ( $targets as $target ) {
			// send webmention
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

			// rescedule if server responds with a http error 5xx
			if ( wp_remote_retrieve_response_code( $response ) >= 500 ) {
				self::reschedule( $post_ID );
			}
		}
	}

	/**
	 * Rescedule Webmentions on HTTP code 500
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
	 * Do webmentions
	 */
	public static function do_webmentions() {
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
	 * Checks the HTML for the rel="http://webmention.org/" link and http://webmention.org/ headers. It does
	 * a check for the http://webmention.org/ headers first and returns that, if available. The
	 * check for the rel="http://webmention.org/" has more overhead than just the header.
	 *
	 * @param string $url URL to ping
	 *
	 * @return bool|string False on failure, string containing URI on success
	 */
	public static function discover_endpoint( $url ) {
		global $wp_version;

		/** @todo Should use Filter Extension or custom preg_match instead. */
		$parsed_url = wp_parse_url( $url );

		if ( ! isset( $parsed_url['host'] ) ) { // Not an URL. This should never happen.
			return false;
		}

		// do not search for a Webmention server on our own uploads
		$uploads_dir = wp_upload_dir();
		if ( 0 === strpos( $url, $uploads_dir['baseurl'] ) ) {
			return false;
		}

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 100,
			'limit_response_size' => 1048576,
			'redirection' => 20,
			'user-agent' => "$user_agent; finding Webmention endpoint",
		);

		$response = wp_remote_head( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		// check link header
		if ( $links = wp_remote_retrieve_header( $response, 'link' ) ) {
			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(\.org)?\/?[\"\']?/i', $link, $result ) ) {
						return WP_Http::make_absolute_url( $result[1], $url );
					}
				}
			} else {
				if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(\.org)?\/?[\"\']?/i', $links, $result ) ) {
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

		// unicode to HTML entities
		$contents = mb_convert_encoding( $contents, 'HTML-ENTITIES', mb_detect_encoding( $contents ) );

		libxml_use_internal_errors( true );

		$doc = new DOMDocument();
		$doc->loadHTML( $contents );

		$xpath = new DOMXPath( $doc );

		// check <link> and <a> elements
		// checks only body>a-links
		foreach ( $xpath->query( '(//link|//a)[contains(concat(" ", @rel, " "), " webmention ") or contains(@rel, "webmention.org")]/@href' ) as $result ) {
			return WP_Http::make_absolute_url( $result->value, $url );
		}

		return false;
	}
}
