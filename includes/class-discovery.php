<?php

namespace Webmention;

use WP_Http;
use DOMXPath;
use DOMDocument;
use Webmention\Request;
use Webmention\Response;

class Discovery {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
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
	 * Generates WebFinger/host-meta links
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
		if ( $version >= '2.0' ) {
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
			return webmentions_open();
		} else {
			$post_id = webmention_url_to_postid( get_self_link() );
		}

		if ( ! $post_id ) {
			return false;
		}

		if ( webmentions_open( $post_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Finds a Webmention server URI based on the given URL.
	 *
	 * Checks the HTML for the rel="webmention" link and Webmention headers. It does
	 * a check for the Webmention headers first and returns that, if available. The
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

		$response = Request::get( $url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$links = $response->get_links_by( array( 'rel' => 'webmention' ) );

		if ( $links ) {
			return WP_Http::make_absolute_url( $links[0]['uri'], $url );
		}

		return false;
	}
}
