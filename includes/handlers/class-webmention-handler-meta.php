<?php
/**
 * Class for webmention parsing using META tags.
 */
class Webmention_Handler_Meta extends Webmention_Handler_Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'meta';

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @param Webmention_Item $item A Parsed Item. If null, a new one will be created.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( $request, $item = null ) {
		if ( $item instanceof Webmention_Item ) {
			$this->webmention_item = $item;
		} else {
			$this->webmention_item = new Webmention_Item();
		}
		$dom   = clone $request->get_domdocument();
		$xpath = new DOMXPath( $dom );

		// Look for OGP properties
		foreach ( $xpath->query( '//meta[(@name or @property or @itemprop) and @content]' ) as $tag ) {
			$meta_name = $tag->getAttribute( 'property' );
			if ( ! $meta_name ) {
				$meta_name = $tag->getAttribute( 'name' );
			}
			if ( ! $meta_name ) {
				$meta_name = $tag->getAttribute( 'itemprop' );
			}
			$meta_value = $tag->getAttribute( 'content' );

			// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
			if ( strlen( $meta_name ) > 200 ) {
				continue;
			}

			$this->add_property( $meta_name, $meta_value );
		}

		// OGP has no concept of anything but mention so it is always a mention.
		$this->webmention_item->set__response_type = 'mention';

		// If Site Name is not set use domain name less www
		if ( ! $this->webmention_item->has_site_name() && $this->webmention_item->has_url() ) {
			$this->webmention_item->set__site_name( preg_replace( '/^www\./', '', wp_parse_url( $this->webmention_item->get_url(), PHP_URL_HOST ) ) );
		}
	}

	protected function add_property( $key, $value ) {
		if ( in_array( $key, array( 'url', 'og:url' ), true ) ) {
			$this->webmention_item->set_url( $value );
		} elseif ( in_array( $key, array( 'DC.Title', 'og:title' ), true ) ) {
			$this->webmention_item->set_name( $value );
		}
	}
}
