<?php
/**
 * Allow localhost URLs if WP_DEBUG is true.
 *
 * @param array  $r   Array of HTTP request args.
 * @param string $url The request URL.
 * @return array $args Array or string of HTTP request arguments.
 */
function webmention_allow_localhost( $r, $url ) {
	$r['reject_unsafe_urls'] = false;

	return $r;
}
add_filter( 'http_request_args', 'webmention_allow_localhost', 10, 2 );
