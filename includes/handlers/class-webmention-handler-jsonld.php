<?php
/**
 * Class for webmention parsing using JSON-LD.
 */
class Webmention_Handler_JSONLD extends Webmention_Handler_Base {

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @param Webmention_Item $item A Parsed Item. If null, a new one will be created.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( $request, $item = null ) {
		$this->set_webmention_item( $item );

		$dom   = clone $request->get_domdocument();
		$xpath = new DOMXPath( $dom );

		$jsonld  = array();
		$content = '';
		foreach ( $xpath->query( "//script[@type='application/ld+json']" ) as $script ) {
			$content  = $script->textContent; // phpcs:ignore
			$jsonld[] = json_decode( $content, true );
		}
		$jsonld = array_filter( $jsonld );
		if ( 1 === count( $jsonld ) && wp_is_numeric_array( $jsonld[0] ) ) {
			$jsonld = $jsonld[0];
		}

		// Set raw data.
		$this->webmention_item->set__raw( $jsonld );
		$this->webmention_item->set__response_type = 'mention';
	}

	protected function is_jsonld( $jsonld ) {
		return ( is_array( $jsonld ) && array_key_exists( '@type', $jsonld ) );
	}

	protected function is_jsonld_type( $jsonld, $type ) {
		return ( array_key_exists( '@type', $jsonld ) && $type === $jsonld['@type'] );
	}
}
