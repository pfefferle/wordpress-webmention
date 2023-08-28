<?php

namespace Webmention\Handler;

use DOMXPath;
use WP_Error;
use DateTimeImmutable;
use Webmention\Response;

/**
 * Class for Webmention parsing using JSON-LD.
 */
class JSONLD extends Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'jsonld';

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
		$this->webmention_item->add_raw( $jsonld );
		$this->webmention_item->add_response_type( 'mention' );
		$return = $this->add_properties( $jsonld );
		return is_wp_error( $return ) ? $return : true;
	}

	/**
	 * Takes JSON-LD and generates a Webmention Item.
	 *
	 * @param array $jsonld Array of JSON-LD objects.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function add_properties( $jsonld ) {
		foreach ( $jsonld as $json ) {
			if ( ! $this->is_jsonld( $json ) ) {
				continue;
			}

			if ( ! in_array( $json['@type'], array( 'WebPage', 'Article', 'NewsArticle', 'BlogPosting', 'SocialMediaPosting' ), true ) ) {
				continue;
			}

			if ( isset( $json['datePublished'] ) ) {
				$this->webmention_item->add_published( new DateTimeImmutable( $json['datePublished'] ) );
			}
			if ( isset( $json['dateModified'] ) ) {
				$this->webmention_item->add_updated( new DateTimeImmutable( $json['dateModified'] ) );
			}
			if ( isset( $json['url'] ) ) {
				$this->webmention_item->add_url( $json['url'] );
			}
			if ( isset( $json['headline'] ) ) {
				$this->webmention_item->add_name( $json['headline'] );
			} elseif ( isset( $json['name'] ) ) {
				$this->webmention_item->add_name( $json['name'] );
			}
			if ( isset( $json['description'] ) ) {
				$this->webmention_item->add_summary( $json['description'] );
			} elseif ( isset( $json['articleBody'] ) ) {
				$this->webmention_item->add_content( $json['articleBody'] );
			}
			if ( isset( $json['keywords'] ) ) {
				$this->webmention_item->add_category( $json['keywords'] );
			}

			if ( isset( $json['image'] ) ) {
				// For now extract only a single image because this is usually multiple sizes.
				if ( wp_is_numeric_array( $json['image'] ) ) {
					$json['image'] = end( $json['image'] );
				}
				if ( is_string( $json['image'] ) ) {
					$this->webmention_item->add_photo( $json['image'] );
				} elseif ( ! $this->is_jsonld( $json['image'] ) && $this->is_jsonld_type( $json['image'], 'ImageObject' ) ) {
					$this->webmention_item->add_photo( $json['image']['url'] );
				}
			}

			if ( isset( $json['author'] ) ) {
				// For now extract only a single author as we only support one.
				if ( wp_is_numeric_array( $json['author'] ) ) {
					$json['author'] = end( $json['author'] );
				}
				if ( $this->is_jsonld( $json['author'] ) ) {
					$author = array(
						'type'  => 'card',
						'name'  => isset( $json['author']['name'] ) ? $json['author']['name'] : null,
						'photo' => isset( $json['author']['image']['url'] ) ? $json['author']['image']['url'] : null,
						'url'   => isset( $json['author']['url'] ) ? $json['author']['url'] : null,
						'me'    => isset( $json['author']['sameAs'] ) ? $json['author']['sameAs'] : null,
						'email' => isset( $json['author']['email'] ) ? $json['author']['email'] : null,
					);
					$this->webmention_item->add_author( array_filter( $author ) );
				}
			}

			if ( isset( $json['isPartOf']['name'] ) ) {
				$this->webmention_item->add_site_name( $json['isPartOf']['name'] );
			}
		}

		$this->webmention_item->add_meta( apply_filters( 'webmention_handler_jsonld_set_properties', array(), $this ) );
		return true;
	}

	protected function is_jsonld( $jsonld ) {
		return ( is_array( $jsonld ) && array_key_exists( '@type', $jsonld ) );
	}

	protected function is_jsonld_type( $jsonld, $type ) {
		return ( array_key_exists( '@type', $jsonld ) && $type === $jsonld['@type'] );
	}
}
