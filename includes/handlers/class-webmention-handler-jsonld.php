<?php
/**
 * Class for webmention parsing using JSON-LD.
 */
class Webmention_Handler_JSONLD extends Webmention_Handler_Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'jsonld';

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @param Webmention_Item $item A Parsed Item. If null, a new one will be created.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( $request, $item = null ) {
		if ( $item instanceof Webmention_Item ) {
			$this->set_webmention_item( $item );
		}

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

		foreach ( $jsonld as $json ) {
			if ( ! $this->is_jsonld( $json ) ) {
				continue;
			}
			if ( in_array( $json['@type'], array( 'WebPage', 'Article', 'NewsArticle' ), true ) ) {
				if ( isset( $json['datePublished'] ) ) {
					$this->webmention_item->set_published( new DateTimeImmutable( $json['datePublished'] ) );
				}
				if ( isset( $json['dateModified'] ) ) {
					$this->webmention_item->set_updated( new DateTimeImmutable( $json['dateModified'] ) );
				}

				$this->webmention_item->set_name( $json['headline'] );
				$this->webmention_item->set_name( $json['name'] );
				$this->webmention_item->set_summary( $json['description'] );
				$this->webmention_item->set_category( $json['keywords'] );

				if ( isset( $json['articleBody'] ) ) {
					$html            = webmention_sanitize_html( $json['articleBody'] );
					$json['content'] = array(
						'html'  => $html,
						'value' => wp_strip_all_tags( $html ),
					);
				}

				if ( isset( $json['image'] ) ) {
					// For now extract only a single image because this is usually multiple sizes.
					if ( wp_is_numeric_array( $json['image'] ) ) {
						$json['image'] = end( $json['image'] );
					}
					if ( is_string( $json['image'] ) ) {
						$this->webmention_item->set_photo( $json['image'] );
					} elseif ( ! $this->is_jsonld( $image ) && $this->is_jsonld_type( $json['image'], 'ImageObject' ) ) {
						$this->webmention_item->set_photo( $json['image']['url'] );
					}
				}

				if ( isset( $json['author'] ) ) {
					// For now extract only a single author as we only support one.
					if ( wp_is_numeric_array( $json['author'] ) ) {
						$json['author'] = end( $json['author'] );
					}
					if ( $this->is_jsonld( $json['author'] ) ) {
						$author = array(
							'name'  => isset( $json['author']['name'] ) ? $json['author']['name'] : null,
							'email' => isset( $json['author']['email'] ) ? $json['author']['name'] : null,
							'photo' => isset( $json['image'] ) ? $json['author']['image'] : null,
							'url'   => isset( $json['url'] ) ? $json['author']['url'] : null,
							'me'    => isset( $json['sameAs'] ) ? $json['author']['sameAs'] : null,
							'email' => isset( $json['email'] ) ? $json['author']['email'] : null,
						);
						$this->webmention_item->set_author( array_filter( $author ) );
					}
				}
			}
		}
	}

	protected function is_jsonld( $jsonld ) {
		return ( is_array( $jsonld ) && array_key_exists( '@type', $jsonld ) );
	}

	protected function is_jsonld_type( $jsonld, $type ) {
		return ( array_key_exists( '@type', $jsonld ) && $type === $jsonld['@type'] );
	}
}
