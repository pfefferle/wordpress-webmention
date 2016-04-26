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

		// admin settings
		add_action( 'admin_init', array( 'Webmention_Sender', 'admin_register_settings' ) );
		// endpoint discovery
		add_action( 'wp_head', array( 'Webmention_Sender', 'html_header' ), 99 );
		add_action( 'send_headers', array( 'Webmention_Sender', 'http_header' ) );
		add_filter( 'host_meta', array( 'Webmention_Sender', 'jrd_links' ) );
		add_filter( 'webfinger_user_data', array( 'Webmention_Sender', 'jrd_links' ) );
		add_filter( 'webfinger_post_data', array( 'Webmention_Sender', 'jrd_links' ) );

		// run Webmentions before the other pinging stuff
		add_action( 'do_pings', array( 'Webmention_Sender', 'do_webmentions' ), 5, 1 );

		add_action( 'publish_post', array( 'Webmention_Sender', 'publish_post_hook' ) );
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
		/** @todo Should use Filter Extension or custom preg_match instead. */
		$parsed_url = @parse_url( $url );
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

		// discover the Webmention endpoint
		$webmention_server_url = self::discover_endpoint( $target );

		// if I can't find an endpoint, perhaps you can!
		$webmention_server_url = apply_filters( 'webmention_server_url', $webmention_server_url, $target );

		$args = array(
			'body' => 'source=' . urlencode( $source ) . '&target=' . urlencode( $target ),
			'timeout' => 100,
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
		$links = array();

		// Find all external links in the source
		if ( preg_match_all( '/<a[^>]+href=.(https?:\/\/[^\'\"]+)/i', $post->post_content, $matches ) ) {
			$links = $matches[1];
		}

		// filter links
		$targets = apply_filters( 'webmention_links', $links, $post_ID );
		$targets = array_unique( $targets );

		foreach ( $targets as $target ) {
			// send Webmention
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
	 * Do Webmentions
	 */
	public static function do_webmentions() {
		global $wpdb;

		// get all posts that should be "mentioned"
		// TODO: Replace with WP_Query
		$mentions = $wpdb->get_results( "SELECT ID, meta_id FROM {$wpdb->posts}, {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_mentionme'" );

		// iterate mentions
		foreach ( $mentions as $mention ) {
			delete_metadata_by_mid( 'post', $mention->meta_id );

			// send them Webmentions
			self::send_webmentions( $mention->ID );
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

		if ( ! self::is_valid_url( $url ) ) { // Not an URL. This should never happen.
			return false;
		}

		// do not search for a Webmention server on our own uploads
		$uploads_dir = wp_upload_dir();
		if ( 0 === strpos( $url, $uploads_dir['baseurl'] ) ) {
			return false;
		}

		$response = wp_remote_head( $url, array( 'timeout' => 100, 'httpversion' => '1.0' ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		// check link header
		if ( $links = wp_remote_retrieve_header( $response, 'link' ) ) {
			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention?\/?[\"\']?/i', $link, $result ) ) {
						return self::make_url_absolute( $url, $result[1] );
					}
				}
			} else {
				if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention?\/?[\"\']?/i', $links, $result ) ) {
					return self::make_url_absolute( $url, $result[1] );
				}
			}
		}

		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return false;
		}

		// now do a GET since we're going to look in the html headers (and we're sure its not a binary file)
		$response = wp_remote_get( $url, array( 'timeout' => 10, 'httpversion' => '1.0', 'limit_response_size' => 1048576 ) );

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
			return self::make_url_absolute( $url, $result->value );
		}

		// check <a> elements
		// checks only body>a-links
		foreach ( $xpath->query( '//body//a[contains(concat(" ", @rel, " "), " webmention ") or contains(@rel, "webmention.org")]/@href' ) as $result ) {
			return self::make_url_absolute( $url, $result->value );
		}

		return false;
	}

	/**
	 * The Webmention autodicovery meta-tags
	 */
	public static function html_header() {
		$endpoint = apply_filters( 'webmention_endpoint', site_url( '?webmention=endpoint' ) );

		echo '<link rel="webmention" href="' . $endpoint . '" />' . "\n";
	}

	/**
	 * The Webmention autodicovery http-header
	 */
	public static function http_header() {
		$endpoint = apply_filters( 'webmention_endpoint', site_url( '?webmention=endpoint' ) );

		header( 'Link: <' . $endpoint . '>; rel="webmention"', false );
	}

	/**
	 * Generates webfinger/host-meta links
	 */
	public static function jrd_links( $array ) {
		$endpoint = apply_filters( 'webmention_endpoint', site_url( '?webmention=endpoint' ) );

		$array['links'][] = array( 'rel' => 'webmention', 'href' => $endpoint );

		return $array;
	}

	/**
	 * Converts relative to absolute urls
	 *
	 * Based on the code of 99webtools.com
	 *
	 * @link http://99webtools.com/relative-path-into-absolute-url.php
	 *
	 * @param string $base the base url
	 * @param string $rel the relative url
	 *
	 * @return string the absolute url
	 */
	public static function make_url_absolute( $base, $rel ) {
		if ( 0 === strpos( $rel, '//' ) ) {
			return parse_url( $base, PHP_URL_SCHEME ) . ':' . $rel;
		}
		// return if already absolute URL
		if ( parse_url( $rel, PHP_URL_SCHEME ) != '' ) {
			return $rel;
		}
		// queries and	anchors
		if ( '#' == $rel[0]  || '?' == $rel[0] ) {
			return $base . $rel;
		}
		// parse base URL and convert to local variables:
		// $scheme, $host, $path
		extract( parse_url( $base ) );
		// remove	non-directory element from path
		$path = preg_replace( '#/[^/]*$#', '', $path );
		// destroy path if relative url points to root
		if ( '/' == $rel[0] ) {
			$path = '';
		}
		// dirty absolute URL
		$abs = "$host";
		// check port
		if ( isset( $port ) && ! empty( $port ) ) {
			$abs .= ":$port";
		}
		// add path + rel
		$abs .= "$path/$rel";
		// replace '//' or '/./' or '/foo/../' with '/'
		$re = array( '#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#' );
		for ( $n = 1; $n > 0; $abs = preg_replace( $re, '/', $abs, -1, $n ) ) { }
		// absolute URL is ready!
		return $scheme . '://' . $abs;
	}

	/**
	 * Register Webmention admin settings.
	 */
	public static function admin_register_settings() {
		register_setting( 'discussion', 'webmention_disable_selfpings_same_url' );
		register_setting( 'discussion', 'webmention_disable_selfpings_same_domain' );

		add_settings_field( 'webmention_discussion_settings', __( 'Webmention Settings', 'Webmention' ), array( 'Webmention_Sender', 'discussion_settings' ), 'discussion', 'default' );
	}

	/**
	 * Add Webmention options to the WordPress discussion settings page.
	 */
	public static function discussion_settings () {
?>
	<fieldset>
		<label for="webmention_disable_selfpings_same_url">
			<input type="checkbox" name="webmention_disable_selfpings_same_url" id="webmention_disable_selfpings_same_url" value="1" <?php
				echo checked( true, get_option( 'webmention_disable_selfpings_same_url' ) );  ?> />
			<?php _e( 'Disable self-pings on the same URL <small>(for example "http://example.com/?p=123")</small>', 'Webmention' ) ?>
		</label>

		<br />

		<label for="webmention_disable_selfpings_same_domain">
			<input type="checkbox" name="webmention_disable_selfpings_same_domain" id="webmention_disable_selfpings_same_domain" value="1" <?php
				echo checked( true, get_option( 'webmention_disable_selfpings_same_domain' ) );  ?> />
			<?php _e( 'Disable self-pings on the same Domain <small>(for example "example.com")</small>', 'Webmention' ) ?>
		</label>
	</fieldset>
<?php
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
