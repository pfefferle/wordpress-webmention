<?php

namespace Webmention\Handler;

use DOMXPath;
use WP_Error;
use Webmention\Response;

/**
 * Class for Webmention parsing using META tags.
 */
class Meta extends Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'meta';

	/**
	 * Takes a response object and parses it.
	 *
	 * @param Webmention\Response $response Response Object.
	 * @param string $target_url The target URL
	 *
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( Response $response, $target_url ) {
		$dom = $response->get_dom_document();

		if ( is_wp_error( $dom ) ) {
			return $dom;
		}

		$xpath = new DOMXPath( $dom );

		$meta = array();

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

			$meta[ $meta_name ] = $meta_value;
		}

		$this->webmention_item->add_raw( $meta );

		$this->add_properties( $meta );

		if (
			! $this->webmention_item->get_name() &&
			isset( $xpath->query( '//title' )->item( 0 )->textContent )
		) {
			$this->webmention_item->add_name( trim( $xpath->query( '//title' )->item( 0 )->textContent ) );
		}

		// If Site Name is not set use domain name less www
		if ( ! $this->webmention_item->has_site_name() && $this->webmention_item->has_url() ) {
			$this->webmention_item->add_site_name( preg_replace( '/^www\./', '', wp_parse_url( $this->webmention_item->get_url(), PHP_URL_HOST ) ) );
		}
	}

	/**
	 * Set meta-properties to Webmention\Entity\Item
	 *
	 * @param string $key   The meta-key.
	 *
	 * @return void
	 */
	protected function add_properties( $meta ) {
		$mapping = array(
			'url'       => array( 'url', 'og:url' ),
			'name'      => array( 'og:title', 'twitter:title', 'dc:title', 'DC.Title' ),
			'content'   => array( 'og:description', 'twitter:description', 'dc:desciption', 'DC.Desciption', 'description' ),
			'summary'   => array( 'og:description', 'twitter:description', 'dc:desciption', 'DC.Desciption', 'description' ),
			'published' => array( 'article:published_time', 'article:published', 'DC.Date', 'dc:date', 'citation_date', 'datePublished' ),
			'updated'   => array( 'article:modified_time', 'article:modified' ),
			'site_name' => array( 'og:site_name' ),
			'author'    => array( 'DC.creator', 'author' ),
			'photo'     => array( 'og:image', 'twitter:image' ),
		);

		foreach ( $mapping as $key => $values ) {
			foreach ( $values as $value ) {
				if ( array_key_exists( $value, $meta ) ) {
					$this->webmention_item->add( $key, $meta[ $value ] );
					break;
				}
			}
		}

		$this->webmention_item->add_meta( apply_filters( 'webmention_handler_meta_set_properties', array(), $this ) );
	}
}
