<?php

namespace Webmention;

use WP_Error;

/**
 * Encapsulates all Webmention HTTP requests
 */
class Request {
	/**
	 *  Retrieve a URL.
	 *
	 * @param string  $url  The URL to retrieve.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|array Either an error or the complete return object
	 */
	public static function get( $url, $safe = true ) {
		$args = self::get_arguments();

		if ( $safe ) {
			$response = wp_safe_remote_get( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new Response( $url, $response );
	}

	/**
	 * Do a head request to check the suitability of a URL
	 *
	 * @param string $url The URL to check.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|array Return error or HTTP API response array.
	 */
	public static function head( $url, $safe = true ) {
		$args = self::get_arguments();

		if ( $safe ) {
			$response = wp_safe_remote_head( $url, $args );
		} else {
			$response = wp_remote_head( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new Response( $url, $response );
	}

	/**
	 * Return Arguments for Retrieving Webmention URLs
	 *
	 * @return array
	 */
	protected static function get_arguments() {
		$wp_version = get_bloginfo( 'version' );
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 1048576,
			'redirection'         => 20,
			'user-agent'          => "$user_agent; Webmention",
		);
		return apply_filters( 'webmention_request_get_args', $args );
	}
}
