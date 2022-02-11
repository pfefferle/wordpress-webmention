<?php

namespace Webmention;

use DOMXPath;
use Webmention\Request;
use Webmention\Handler\Base;

/**
 * Class for webmention parsing using the WordPress API.
 */
class WP extends Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'wp';

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention\Request $request Request Object.
	 * @param string $target_url The target URL
	 *
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( Request $request, $target_url ) {
		$dom = clone $request->get_domdocument();
		if ( is_wp_error( $dom ) ) {
			return $dom;
		}
		$xpath = new DOMXPath( $dom );

		$meta = array();

		// Look for OGP properties
		foreach ( $xpath->query( '//link[@rel="https://api.w.org/"]' ) as $link ) {

		}
	}
}
