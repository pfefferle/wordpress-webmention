<?php

namespace Webmention;

use WP_Error;

/**
 * Class Request
 *
 * This class encapsulates all Webmention HTTP requests. It provides methods for sending and receiving Webmentions,
 * as well as for validating URLs and handling HTTP responses.
 */
class Request {
	/**
	 * Retrieve a URL.
	 *
	 * This function retrieves the content from the provided URL.
	 * It can use either the safe or unfiltered version of the HTTP API, depending on the $safe parameter.
	 *
	 * @param string  $url  The URL to retrieve.
	 * @param boolean $safe Determines whether to use the safe (true) or unfiltered (false) version of the HTTP API.
	 *
	 * @return WP_Error|Response Returns a WP_Error object if the request fails; otherwise, returns the complete return object.
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

		$response = new Response( $url, $response );

		if ( $response->is_error() ) {
			return $response->get_error();
		}

		return $response;
	}

	/**
	 * Performs a HEAD request to verify the suitability of a URL.
	 *
	 * This function performs a HEAD request to the provided URL to check its suitability.
	 * It can use either the safe or unfiltered version of the HTTP API, depending on the $safe parameter.
	 *
	 * @param string  $url  The URL to be checked.
	 * @param boolean $safe Determines whether to use the safe (true) or unfiltered (false) version of the HTTP API.
	 *
	 * @return WP_Error|array Returns a WP_Error object if the request fails; otherwise, returns an array containing the HTTP API response.
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

		$response = new Response( $url, $response );

		if ( $response->is_error() ) {
			return $response->get_error();
		}

		return $response;
	}

	/**
	 * Return Arguments for Retrieving Webmention URLs
	 *
	 * This function is responsible for returning the arguments necessary for retrieving Webmention URLs.
	 * It does not take any parameters and returns an array.
	 *
	 * @return array An array of arguments for retrieving Webmention URLs.
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
