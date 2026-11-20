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

		$this->add_author_fallback( $xpath, $response->get_url() );
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

	/**
	 * Lets the page stand in for what no handler found out about the author.
	 *
	 * An author without a name is the site itself: it is named after the site and gets its icon as photo.
	 * An author without a URL links to the page.
	 *
	 * @param DOMXPath $xpath The page.
	 * @param string   $url   The URL of the page.
	 *
	 * @return void
	 */
	protected function add_author_fallback( DOMXPath $xpath, $url ) {
		// Without an author, `get_author()` returns a placeholder that already has a name.
		$author = $this->webmention_item->has_author() ? $this->webmention_item->get_author() : array( 'type' => 'card' );

		if ( empty( $author['name'] ) ) {
			$site_name      = $this->webmention_item->get_site_name();
			$author['name'] = $site_name ? $site_name : webmention_extract_domain( $url );

			if ( empty( $author['photo'] ) ) {
				$author['photo'] = $this->get_icon( $xpath, $url );
			}
		}

		if ( empty( $author['url'] ) ) {
			$author['url'] = $url;
		}

		$this->webmention_item->set_author( array_filter( $author ) );
	}

	/**
	 * Returns the icon of a page: the Apple touch icon, otherwise the largest icon.
	 *
	 * @param DOMXPath $xpath The page.
	 * @param string   $url   The URL of the page.
	 *
	 * @return string The icon URL or an empty string.
	 */
	protected function get_icon( DOMXPath $xpath, $url ) {
		$icon = '';
		$best = array( false, -1 );

		foreach ( $xpath->query( '//link[@rel and @href]' ) as $link ) {
			$rels = preg_split( '/\s+/', strtolower( trim( $link->getAttribute( 'rel' ) ) ) );

			if ( ! array_intersect( array( 'icon', 'apple-touch-icon', 'apple-touch-icon-precomposed' ), $rels ) ) {
				continue;
			}

			// The largest declared size, for example "192x192"; icons without a size count as 0.
			preg_match_all( '/(\d+)x\d+/i', $link->getAttribute( 'sizes' ), $sizes );
			$size = $sizes[1] ? max( array_map( 'intval', $sizes[1] ) ) : 0;

			// Apple touch icons are made to be shown at avatar size, so they beat every other icon.
			$rank = array( ! in_array( 'icon', $rels, true ), $size );

			if ( $rank > $best ) {
				$best = $rank;
				$icon = $link->getAttribute( 'href' );
			}
		}

		if ( ! $icon ) {
			return '';
		}

		return esc_url_raw( \WP_Http::make_absolute_url( $icon, $url ), array( 'http', 'https' ) );
	}
}
