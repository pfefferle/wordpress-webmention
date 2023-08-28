<?php

namespace Webmention;

/**
 * Allow localhost URLs if WP_DEBUG is true.
 *
 * @param array  $r   Array of HTTP request args.
 *
 * @return array $args Array or string of HTTP request arguments.
 */
function allow_localhost( $r ) {
	$r['reject_unsafe_urls'] = false;

	return $r;
}
add_filter( 'http_request_args', 'Webmention\allow_localhost', 10 );
