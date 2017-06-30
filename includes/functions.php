<?php
/**
 * A wrapper for Webmention_Sender::send_webmention
 *
 * @param string $source source url
 * @param string $target target url
 *
 * @return array of results including HTTP headers
 */
function send_webmention( $source, $target ) {
	return Webmention_Sender::send_webmention( $source, $target );
}

/**
 * Return the text for a webmention form allowing customization by post_id
 *
 * @param int $post_id Post ID
 *
 */
function get_webmention_form_text( $post_id ) {
	return apply_filters( 'webmention_form_text', __( 'Respond on your own site? Send me a <a href="http://indieweb.org/webmention">Webmention</a> by writing something on your website that links to this post and then enter your post URL below.', 'webmention' ), $post_id );
}

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
	$comment_type = apply_filters( 'webmention_comment_type', WEBMENTION_COMMENT_TYPE );

	$args = array(
		'post_id'	=> $post->ID,
		'type'		=> $comment_type,
		'count'		=> true,
		'status'	=> 'approve',
	);

	$comments_query = new WP_Comment_Query;
	return $comments_query->query( $args );
}

/**
 * Return Webmention Endpoint
 *
 * @see https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
 *
 * @return string the Webmention endpoint
 */
function get_webmention_endpoint() {
	return apply_filters( 'webmention_endpoint', get_rest_url( null, '/webmention/1.0/endpoint' ) );
}

/**
 * Return Webmention process type
 *
 * @see https://www.w3.org/TR/webmention/#receiving-webmentions
 *
 * @return string the Webmention process type
 */
function get_webmention_process_type() {
	return apply_filters( 'webmention_process_type', WEBMENTION_PROCESS_TYPE );
}

/**
 * Return the post_id for a URL filtered for webmentions.
 * Allows redirecting to another id to add linkbacks to the home page or archive
 * page or taxonomy page.
 *
 * @param string $url URL
 * @param int Return 0 if no post ID found or a post ID
 *
 * @uses apply_filters calls "webmention_post_id" on the post_ID
 */
function webmention_url_to_postid( $url ) {
	if ( '/' === wp_make_link_relative( trailingslashit( $url ) ) ) {
		return apply_filters( 'webmention_post_id', get_option( 'webmention_home_mentions' ), $url );
	}
	return apply_filters( 'webmention_post_id', url_to_postid( $url ), $url );
}

/**
 * Finds a Webmention server URI based on the given URL
 *
 * Checks the HTML for the rel="webmention" link and webmention headers. It does
 * a check for the webmention headers first and returns that, if available. The
 * check for the rel="webmention" has more overhead than just the header.
 * Supports backward compatability to webmention.org headers.
 *
 * @see https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
 *
 * @param string $url URL to ping
 *
 * @return bool|string False on failure, string containing URI on success
 */
function webmention_discover_endpoint( $url ) {
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

	$wp_version = get_bloginfo( 'version' );

	$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
	$args = array(
		'timeout' => 100,
		'limit_response_size' => 1048576,
		'redirection' => 20,
		'user-agent' => "$user_agent; finding Webmention endpoint",
	);

	$response = wp_safe_remote_head( $url, $args );

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
	$response = wp_safe_remote_get( $url, $args );

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

if ( ! function_exists( 'wp_get_meta_tags' ) ) :
	/**
	 * Parse meta tags from source content
	 * Based on the Press This Meta Parsing Code
	 *
	 * @param string $source_content Source Content
	 *
	 * @return array meta tags
	 */
	function wp_get_meta_tags( $source_content ) {
		$meta_tags = array();

		if ( ! $source_content ) {
			return $meta_tags;
		}

		if ( preg_match_all( '/<meta [^>]+>/', $source_content, $matches ) ) {
			$items = $matches[0];
			foreach ( $items as $value ) {
				if ( preg_match( '/(property|name)="([^"]+)"[^>]+content="([^"]+)"/', $value, $matches ) ) {
					$meta_name  = $matches[2];
					$meta_value = $matches[3];
					// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
					if ( strlen( $meta_name ) > 100 ) {
						continue;
					}
					$meta_tags[ $meta_name ] = $meta_value;
				}
			}
		}
		return $meta_tags;
	}
endif;
