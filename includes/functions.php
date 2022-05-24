<?php

/**
 * Registers a webmention comment type.
 *
 *
 * @param string $comment_type Key for comment type.
 * @param array  $args         Arguments.
 *
 * @return Webmention\Comment_Type The registered webmention comment type.
 */
function register_webmention_comment_type( $comment_type, $args = array() ) {
	global $webmention_comment_types;

	if ( ! is_array( $webmention_comment_types ) ) {
		$webmention_comment_types = array();
	}

	// Sanitize comment type name.
	$comment_type = sanitize_key( $comment_type );

	$comment_type_object = new \Webmention\Comment_Type( $comment_type, $args );

	$webmention_comment_types[ $comment_type ] = $comment_type_object;

	/**
	 * Fires after a webmention comment type is registered.
	 *
	 *
	 * @param string                   $comment_type        Comment type.
	 * @param \Webmention\Comment_Type $comment_type_object Arguments used to register the comment type.
	 */
	do_action( 'registered_webmention_comment_type', $comment_type, $comment_type_object );

	return $comment_type_object;
}

/**
 * Return the registered custom comment types.
 *
 * @return array The registered custom comment types
 */
function get_webmention_comment_types() {
	global $webmention_comment_types;

	return $webmention_comment_types;
}

/**
 * A wrapper for Webmention\Sender::send_webmention.
 *
 * @since 2.4.0
 *
 * @param string $source source url.
 * @param string $target target url.
 * @return array of results including HTTP headers
 */
function send_webmention( $source, $target ) {
	return \Webmention\Sender::send_webmention( $source, $target );
}

/**
 * Return the text for a webmention form allowing customization by post_id.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function get_webmention_form_text( $post_id ) {
	$text = get_option( 'webmention_comment_form_text', '' );
	if ( empty( $text ) ) {
		$text = get_default_webmention_form_text();
	}
	return wp_kses_post( apply_filters( 'webmention_form_text', $text ), $post_id );
}

/**
 * Return the default text for a webmention form.
 *
 * @return string
 */
function get_default_webmention_form_text() {
	return __( 'To respond on your own website, enter the URL of your response which should contain a link to this post\'s permalink URL. Your response will then appear (possibly after moderation) on this page. Want to update or remove your response? Update or delete your post and re-enter your post\'s URL again. (<a href="https://indieweb.org/webmention">Learn More</a>)', 'webmention' );
}

/**
 * Check the $url to see if it is on the domain allowlist.
 *
 * @param string $url URL to check.
 * @return boolean
 */
function is_webmention_source_allowed( $url ) {
	return \Webmention\Receiver::is_source_allowed( $url );
}

/**
 * Return the Number of Webmentions.
 *
 * @param int $post_id The post ID (optional).
 * @return int the number of Webmentions for one Post
 */
function get_webmentions_number( $post_id = 0 ) {
	$post = get_post( $post_id );

	// change this if your theme can't handle the Webmentions comment type.
	$comment_type = apply_filters( 'webmention_comment_type', WEBMENTION_COMMENT_TYPE );

	$args = array(
		'post_id' => $post->ID,
		'type'    => $comment_type,
		'count'   => true,
		'status'  => 'approve',
	);

	$comments_query = new WP_Comment_Query();

	return $comments_query->query( $args );
}

/**
 * Return Webmention Endpoint.
 *
 * @see https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
 *
 * @return string The Webmention endpoint.
 */
function get_webmention_endpoint() {
	return apply_filters( 'webmention_endpoint', get_rest_url( null, '/webmention/1.0/endpoint' ) );
}

/**
 * Return Webmention process type.
 *
 * @see https://www.w3.org/TR/webmention/#receiving-webmentions
 *
 * @return string The Webmention process type.
 */
function get_webmention_process_type() {
	return apply_filters( 'webmention_process_type', WEBMENTION_PROCESS_TYPE );
}

/**
 * Return the post_id for a URL filtered for webmentions.
 *
 * Allows redirecting to another id to add linkbacks to the home page or archive
 * page or taxonomy page.
 *
 * @since 3.1.0
 *
 * @uses apply_filters calls "webmention_post_id" on the post_ID
 *
 * @param string $url URL.
 * @return int $id Return 0 if no post ID found or a post ID.
 */
function webmention_url_to_postid( $url ) {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	$id = wp_cache_get( base64_encode( $url ), 'webmention_url_to_postid' );

	if ( false !== $id ) {
		return apply_filters( 'webmention_post_id', $id, $url );
	}

	if ( '/' === wp_make_link_relative( trailingslashit( $url ) ) ) {
		return apply_filters( 'webmention_post_id', get_option( 'webmention_home_mentions' ), $url );
	}

	$id = url_to_postid( $url );

	if ( ! $id && post_type_supports( 'attachment', 'webmentions' ) ) {
		$ext = pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );

		if ( ! empty( $ext ) ) {
			$id = attachment_url_to_postid( $url );
		}
	}

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	wp_cache_set( base64_encode( $url ), $id, 'webmention_url_to_postid', 300 );

	return apply_filters( 'webmention_post_id', $id, $url );
}

/**
 * Extract the domain for a url, sans www.
 *
 * @since 3.8.9
 *
 * @param string $url URL to extract domain from.
 * @return string|string[]|null
 */
function webmention_extract_domain( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	// strip leading www, if any.
	return preg_replace( '/^www\./', '', $host );
}

/**
 * Retrieve list of approved domains.
 *
 * @return array|mixed|string|void
 */
function get_webmention_approve_domains() {
	$allowlist = get_option( 'webmention_approve_domains' );
	$allowlist = trim( $allowlist );
	$allowlist = explode( "\n", $allowlist );

	return $allowlist;
}

/**
 * Finds a Webmention server URI based on the given URL.
 *
 * Checks the HTML for the rel="webmention" link and webmention headers. It does
 * a check for the webmention headers first and returns that, if available. The
 * check for the rel="webmention" has more overhead than just the header.
 * Supports backward compatability to webmention.org headers.
 *
 * @see https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
 *
 * @param string $url URL to ping.
 *
 * @return bool|string False on failure, string containing URI on success
 */
function webmention_discover_endpoint( $url ) {
	return \Webmention\Discovery::discover_endpoint( $url );
}

/* Backward compatibility for function available in version 5.1 and above */
if ( ! function_exists( 'is_avatar_comment_type' ) ) :
	function is_avatar_comment_type( $comment_type ) {
		/**
		 * Filters the list of allowed comment types for retrieving avatars.
		 *
		 * @since 3.0.0
		 *
		 * @param array $types An array of content types. Default only contains 'comment'.
		 */
		$allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );

			return in_array( $comment_type, (array) $allowed_comment_types, true );
	}
endif;

/* Backward compatibility for function available in version 5.3 and above */
if ( ! function_exists( 'get_self_link' ) ) :
	/**
	 * Returns the link for the currently displayed feed.
	 *
	 * @since 5.3.0
	 *
	 * @return string Correct link for the atom:self element.
	 */
	function get_self_link() {
		$host = wp_parse_url( home_url() );
		return set_url_scheme( 'http://' . $host['host'] . wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}
endif;


/**
 * Use DOMDocument to extract URLs from HTML content
 *
 * @param string  $content            HTML Content to extract URLs from.
 * @param boolean $support_media_urls Extract media URLs not just traditional links
 *
 * @return array URLs found in passed string.
 */
function webmention_extract_urls( $content, $support_media_urls = false ) {

	// If no content is provided, do not attempt to parse it for URLs.
	if ( '' === $content ) {
		return array();
	}

	$response = new \Webmention\Response();
	$response->set_body( $content );
	$doc = $response->get_dom_document( false );

	$xpath = new DOMXPath( $doc );

	$attributes = array(
		'cite' => array( 'blockquote', 'del', 'ins', 'q' ),
		'href' => array( 'a', 'area' ),
	);

	$media_attributes = array(
		'data'   => array( 'object' ),
		'poster' => array( 'video' ),
		'src'    => array( 'audio', 'embed', 'iframe', 'img', 'input', 'source', 'track', 'video' ),
	);

	if ( $support_media_urls ) {
		$attributes = array_merge( $attributes, $media_attributes );
	}

	$urls = array();

	foreach ( $attributes as $attribute => $elements ) {
		foreach ( $elements as $element ) {
			foreach ( $xpath->query( sprintf( '//%1$s[@%2$s]', $element, $attribute ) ) as $url ) {
				$urls[] = $url->getAttribute( $attribute );
			}
		}
	}

	return array_filter( $urls );
}


/**
 * Returns whether this is a webmention comment type
 * @param int|WP_Comment $comment
 * @return array
 */
function is_webmention_comment_type( $comment ) {
	$comment = get_comment( $comment );
	if ( ! $comment ) {
		return false;
	}
	$types = array( apply_filters( 'webmention_comment_type', WEBMENTION_COMMENT_TYPE ) );
	return in_array( $comment->comment_type, $types, true );

}

/**
 * Returns a string indicating the comment type
 * @param int|WP_Comment $comment
 * @return string
 */
function get_webmention_comment_type_string( $comment ) {
	global $webmention_comment_types;
	$comment = get_comment( $comment );
	if ( ! $comment ) {
		return false;
	}
	$type = get_comment_type( $comment );

	$name = null;

	if ( is_array( $webmention_comment_types ) && array_key_exists( $type, $webmention_comment_types ) ) {
		$name = $webmention_comment_types[ $type ]->singular;
	}
	if ( ! $name ) {
		switch ( $type ) {
			case 'comment':
				$name = _x( 'Comment', 'noun', 'default' );
				break;
			case 'pingback':
				$name = __( 'Pingback', 'default' );
				break;
			case 'trackback':
				$name = __( 'Trackback', 'default' );
				break;
			case 'webmention':
				$name = __( 'Webmention', 'webmention' );
				break;
			default:
				$name = __( 'Response', 'webmention' );
		}
	}

	/**
	 * Filters the returned comment type string.
	 *
	 * @param string     $name The name of the comment type
	 * @param string     $type      The comment type.
	 */
	return apply_filters( 'webmention_comment_string', $name, $type );
}

/**
 *  Sanitize HTML. To be used on content elements after parsing.
 *
 * @param string $content The HTML to Sanitize.
 *
 * @return string Sanitized HTML.
 */
function webmention_sanitize_html( $content ) {
	if ( ! is_string( $content ) ) {
		return $content;
	}

	// Strip HTML Comments.
	$content = preg_replace( '/<!--(.|\s)*?-->/', '', $content );

	// Only allow approved HTML elements
	$allowed = array(
		'a'          => array(
			'href'     => array(),
			'name'     => array(),
			'hreflang' => array(),
			'rel'      => array(),
		),
		'abbr'       => array(),
		'b'          => array(),
		'br'         => array(),
		'code'       => array(),
		'ins'        => array(),
		'del'        => array(),
		'em'         => array(),
		'i'          => array(),
		'q'          => array(),
		'strike'     => array(),
		'strong'     => array(),
		'time'       => array(),
		'blockquote' => array(),
		'pre'        => array(),
		'p'          => array(),
		'h1'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'h5'         => array(),
		'h6'         => array(),
		'ul'         => array(),
		'li'         => array(),
		'ol'         => array(),
		'span'       => array(),
		'img'        => array(
			'src'    => array(),
			'alt'    => array(),
			'title'  => array(),
			'srcset' => array(),
		),
		'video'      => array(
			'src'      => array(),
			'duration' => array(),
			'poster'   => array(),
		),
		'audio'      => array(
			'duration' => array(),
			'src'      => array(),
		),
		'track'      => array(),
		'source'     => array(),
	);
	return trim( wp_kses( $content, $allowed ) );
}

/**
 * Inverse of wp_parse_url
 *
 * Slightly modified from p3k-utils (https://github.com/aaronpk/p3k-utils)
 * Copyright 2017 Aaron Parecki, used with permission under MIT License
 *
 * @link http://php.net/parse_url
 * @param  string $parsed_url the parsed URL (wp_parse_url)
 * @return string             the final URL
 */
if ( ! function_exists( 'build_url' ) ) {
	function build_url( $parsed_url ) {
		$scheme   = ! empty( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = ! empty( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = ! empty( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = ! empty( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = ! empty( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = ! empty( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$query    = ! empty( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment = ! empty( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}
}

if ( ! function_exists( 'normalize_url' ) ) {
	// Adds slash if no path is in the URL, and convert hostname to lowercase
	function normalize_url( $url ) {

		$parts = wp_parse_url( $url );
		if ( empty( $parts['path'] ) ) {
			$parts['path'] = '/';
		}
		if ( isset( $parts['host'] ) ) {
			$parts['host'] = strtolower( $parts['host'] );
			return build_url( $parts );
		}
	}
}

if ( ! function_exists( 'ifset' ) ) {
	/**
	 * If set, return otherwise false.
	 *
	 * @param mixed $var Check if set.
	 *
	 * @return mixed|false Return either $var or $return.
	 */
	function ifset( &$var, $return = false ) {
		return isset( $var ) ? $var : $return;
	}
}

/**
 * Return enabled status of Homepage Webmentions.
 *
 * @since 3.8.9
 *
 * @param bool $open    Whether the current post is open for pings.
 * @param int  $post_id The post ID.
 * @return boolean if pings are open
 */
function webmention_pings_open( $open, $post_id ) {
	if ( get_option( 'webmention_home_mentions' ) === $post_id ) {
		return true;
	}

	return $open;
}

/**
 * Retrieve the default comment status for a given post type.
 *
 * @since 3.8.9
 *
 * @param string $status       Default status for the given post type,
 *                             either 'open' or 'closed'.
 * @param string $post_type    Post type to check.
 * @param string $comment_type Type of comment. Default is `comment`.
 *
 * @return string
 */
function webmention_get_default_comment_status( $status, $post_type, $comment_type ) {
	if ( 'webmention' === $comment_type ) {
		return post_type_supports( $post_type, 'webmentions' ) ? 'open' : 'closed';
	}
	// Since support for the pingback comment type is used to keep pings open...
	if ( ( 'pingback' === $comment_type ) ) {
		return ( post_type_supports( $post_type, 'webmentions' ) ? 'open' : $status );
	}

	return $status;
}

/**
 * Render the webmention comment form.
 *
 * Can be filtered to load a custom template of your choosing.
 *
 * @since 3.8.9
 */
function webmention_comment_form() {
	$template = apply_filters( 'webmention_comment_form', plugin_dir_path( __FILE__ ) . '../templates/webmention-comment-form.php' );

	if ( ( 1 === (int) get_option( 'webmention_show_comment_form', 1 ) ) && pings_open() ) {
		load_template( $template );
	}
}
