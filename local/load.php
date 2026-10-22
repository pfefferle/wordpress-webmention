<?php
/**
 * Load the Webmention development tools.
 *
 * This directory is excluded from the release and only loaded when
 * `wp_get_environment_type()` is `local`.
 *
 * @package Webmention
 */

namespace Webmention\Development;

/**
 * Allow unsafe HTTP request URLs (localhost, private IPs, etc.) in local development.
 *
 * Disables WordPress SSRF protection (`reject_unsafe_urls`) so that
 * HTTP requests to localhost, private IPs, etc. are permitted during
 * local development. Must NOT run in production.
 *
 * @param array $parsed_args HTTP request arguments.
 *
 * @return array Filtered HTTP request arguments.
 *
 * @see wp_http_validate_url()
 */
function allow_unsafe_http_request_urls( $parsed_args ) {
	$parsed_args['reject_unsafe_urls'] = false;

	return $parsed_args;
}
\add_filter( 'http_request_args', __NAMESPACE__ . '\allow_unsafe_http_request_urls' );

/*
 * Treat localhost and private IPs as valid URLs in local development.
 *
 * @see includes/handler/class-mf2.php :: is_url()
 */
\add_filter( 'http_request_host_is_external', '__return_true' );

/**
 * Allow any port in local development.
 *
 * @param int[]  $ports Array of allowed ports.
 * @param string $host  Host name of the requested URL.
 * @param string $url   Requested URL.
 *
 * @return int[] Filtered array of allowed ports.
 *
 * @see wp_http_validate_url()
 */
function allow_any_port( $ports, $host, $url ) {
	$ports[] = (int) \wp_parse_url( $url, PHP_URL_PORT );

	return $ports;
}
\add_filter( 'http_allowed_safe_ports', __NAMESPACE__ . '\allow_any_port', 10, 3 );
