<?php

namespace Webmention;

use WP_Http;
use DOMXPath;
use DOMDocument;

class Discovery {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function int() {
		// endpoint discovery
		add_action( 'wp_head', array( static::class, 'html_header' ), 99 );
		add_action( 'template_redirect', array( static::class, 'http_header' ) );
		add_filter( 'host_meta', array( static::class, 'jrd_links' ) );
		add_filter( 'webfinger_user_data', array( static::class, 'jrd_links' ) );
		add_filter( 'webfinger_post_data', array( static::class, 'jrd_links' ) );

		add_filter( 'nodeinfo_data', array( static::class, 'nodeinfo' ), 10, 2 );
		add_filter( 'nodeinfo2_data', array( static::class, 'nodeinfo2' ), 10 );
	}

	/**
	 * The Webmention autodicovery meta-tags
	 */
	public static function html_header() {
		if ( ! self::should_show_headers() ) {
			return;
		}

		printf( '<link rel="webmention" href="%s" />' . PHP_EOL, get_webmention_endpoint() );
		// backwards compatibility with v0.1
		printf( '<link rel="http://webmention.org/" href="%s" />' . PHP_EOL, get_webmention_endpoint() );
	}

	/**
	 * The Webmention autodicovery http-header
	 */
	public static function http_header() {
		if ( ! self::should_show_headers() ) {
			return;
		}

		header( sprintf( 'Link: <%s>; rel="webmention"', get_webmention_endpoint() ), false );
		// backwards compatibility with v0.1
		header( sprintf( 'Link: <%s>; rel="http://webmention.org/"', get_webmention_endpoint() ), false );
	}

	/**
	 * Generates webfinger/host-meta links
	 */
	public static function jrd_links( $array ) {
		$array['links'][] = array(
			'rel'  => 'webmention',
			'href' => get_webmention_endpoint(),
		);
		// backwards compatibility with v0.1
		$array['links'][] = array(
			'rel'  => 'http://webmention.org/',
			'href' => get_webmention_endpoint(),
		);

		return $array;
	}

	/**
	 * Extend NodeInfo data.
	 *
	 * @since 3.8.9
	 *
	 * @param array $nodeinfo NodeInfo data.
	 * @param array $version  Updated data.
	 *
	 * @return array
	 */
	public static function nodeinfo( $nodeinfo, $version ) {
		if ( '2.0' === $version ) {
			$nodeinfo['protocols'][] = 'webmention';
		} else {
			$nodeinfo['protocols']['inbound'][]  = 'webmention';
			$nodeinfo['protocols']['outbound'][] = 'webmention';
		}

		return $nodeinfo;
	}

	/**
	 * Extend NodeInfo2 data.
	 *
	 * @since 3.8.9
	 *
	 * @param array $nodeinfo NodeInfo2 data.
	 *
	 * @return array
	 */
	public static function nodeinfo2( $nodeinfo ) {
		$nodeinfo['protocols'][] = 'webmention';

		return $nodeinfo;
	}

	/**
	 * Webmention headers to be added
	 *
	 * @return boolean
	 */
	public static function should_show_headers() {
		if ( WEBMENTION_ALWAYS_SHOW_HEADERS ) {
			return true;
		}

		return self::should_receive_mentions();
	}

	/**
	 *
	 *
	 * @return boolean
	 */
	public static function should_receive_mentions() {
		if ( is_singular() ) {
			return pings_open();
		} else {
			$post_id = webmention_url_to_postid( get_self_link() );
		}

		if ( ! $post_id ) {
			return false;
		}

		// If the post type does not support webmentions do not even check if pings_open is set
		if ( ! post_type_supports( get_post_type( $post_id ), 'webmentions' ) ) {
			return false;
		}

		if ( pings_open( $post_id ) ) {
			return true;
		}

		return false;
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
	public static function discover_endpoint( $url ) {
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
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 1048576,
			'redirection'         => 20,
			'user-agent'          => "$user_agent; finding Webmention endpoint",
		);

		$response = wp_safe_remote_head( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		// check link header
		$links = wp_remote_retrieve_header( $response, 'link' );
		if ( $links ) {
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
}
