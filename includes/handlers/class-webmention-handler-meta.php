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
		$dom  = clone $request->get_domdocument();
		$meta = $this->parse_meta( $dom );

		// Set raw data.
		$this->webmention_item->set__raw( $meta );

		// OGP has no concept of anything but mention so it is always a mention.
		$this->webmention_item->set__response_type = 'mention';

		$this->ogp_to_jf2( $meta );
		$this->dublin_core_to_jf2( $meta );

		// If Site Name is not set use domain name less www
		if ( ! $this->webmention_item->has_site_name() && $this->webmention_item->has_url() ) {
			$this->webmention_item->set__site_name( preg_replace( '/^www\./', '', wp_parse_url( $this->webmention_item->get_url(), PHP_URL_HOST ) ) );
		}

		$this->webmention_item->set_name( $meta['title'] );

		if ( ! $this->webmention_item->has_published() ) {
			if ( isset( $meta['citation_date'] ) ) {
				$this->webmention_item->set_published( new DateTimeImmutable( $meta['citation_date'] ) );
			} elseif ( isset( $meta['datePublished'] ) ) {
				$this->webmention_item->set_published( new DateTimeImmutable( $meta['datePublished'] ) );
			}
		}

		if ( ! $this->webmention_item->has_author() ) {
			$this->webmention->item->set_author( array( 'name' => $meta['author'] ) );
		}
	}

	/**
	 * Takes a DOMDocument and returns an array of parsed meta properties.
	 *
	 * @param DOMDocument $dom DOMDocument.
	 * @return array Associative array.
	 */
	private function parse_meta( $dom ) {
		$xpath = new DOMXPath( $dom );
		$meta  = array();
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
			$meta = $this->add_property( $meta, $meta_name, $meta_value );
		}

		$meta['title'] = trim( $xpath->query( '//title' )->item( 0 )->textContent );
		$meta          = $this->parse_ogp( $meta );
		if ( isset( $meta['og'] ) ) {
			$meta['og'] = $this->parse_ogp( $meta['og'] );
		}
		return $meta;
	}


	protected function ogp_to_jf2( $meta ) {
		// Start by looking at OGP.
		if ( isset( $meta['og'] ) ) {
			$og = $meta['og'];
			if ( isset( $og['url'] ) ) {
				$this->webmention_item->set_url( $og['url'] );
			}
			if ( isset( $og['title'] ) ) {
				$this->webmention_item->set_name( $og['title'] );
			}
			if ( isset( $og['description'] ) ) {
				$this->webmention_item->set_summary( $og['description'] );
			}
			if ( isset( $og['image'] ) ) {
				$image = $og['image'];
				if ( is_string( $image ) ) {
					$this->webmention_item->set_photo( array( $image ) );
				} elseif ( wp_is_numeric_array( $image ) ) {
					$this->webmention_item->set_photo( array( $image[0] ) );
				} else {
					$this->webmention_item->set_photo( array( $image['secure_url'] ) );
				}
			}
			if ( isset( $og['site_name'] ) ) {
				$this->webmention_item->set__site_name( $og['site_name'] );
			}

			if ( isset( $og['longitude'] ) ) {
				$this->webmention_item->set_location(
					array(
						'longitude' => $og['longitude'],
						'latitude'  => $og['longitude'],
					)
				);
			}
			if ( isset( $og['type'] ) ) {
				$type = $og['type'];
				if ( isset( $meta[ $type ]['tag'] ) ) {
					$this->webmention_item->set_category( $meta[ $type ]['tag'] );
				}
				if ( ! empty( $meta[ $type ]['author'] ) ) {
					$this->webmention_item->set_author( array( 'name' => $meta[ $type ]['author'] ) );
				}
				if ( 'article' === $type ) {
					if ( isset( $meta['article']['published_time'] ) ) {
						$this->webmention_item->set_published( new DateTimeImmutable( $meta['article']['published_time'] ) );
					} elseif ( isset( $meta['article']['published'] ) ) {
						$this->webmention_item->set_published( new DateTimeImmutable( $meta['article']['published'] ) );
					}
					if ( isset( $meta['article']['modified_time'] ) ) {
						$this->webmention_item->set_updated( new DateTimeImmutable( $meta['article']['modified_time'] ) );
					} elseif ( isset( $meta['article']['modified'] ) ) {
						$this->webmention_item->set_updated( new DateTimeImmutable( $meta['article']['modified'] ) );
					}
				}
			}
		}
	}

	protected function dublin_core_to_jf2( $meta ) {
		$item = array();
		// Then look at Dublin Core Properties
		if ( isset( $meta['dc'] ) ) {
			$dc = $meta['dc'];
			if ( isset( $dc['Title'] ) ) {
				$item['name'] = $dc['Title'];
			}
			if ( isset( $dc['Creator'] ) ) {
				if ( is_string( $dc['Creator'] ) ) {
					$this->webmention_item->set_author( array( 'name' => $dc['Creator'] ) );
				}
			}
			if ( isset( $dc['Description'] ) ) {
				$this->webmention_item->set_summary( $dc['Description'] );
			}
			if ( isset( $dc['Date'] ) ) {
				$this->webmention_item->set_published( new DateTimeImmutable( $dc['Date'] ) );
			}
		}
	}

	protected function add_property( $array, $key, $value ) {
		if ( ! isset( $array[ $key ] ) ) {
			$array[ $key ] = $value;
		} elseif ( is_string( $array[ $key ] ) ) {
			$array[ $key ] = array( $array[ $key ], $value );
		} elseif ( is_array( $array[ $key ] ) ) {
			$array[ $key ][] = $value;
		}
		return $array;
	}

	/**
	 * Tries to parse OGP Meta tags by hierarchy.
	 */
	protected function parse_ogp( $meta ) {
		$return = array();
		if ( isset( $meta ) && is_array( $meta ) ) {
			foreach ( $meta as $key => $value ) {
				$nmeame = explode( ':', $key );
				if ( 1 === count( $name ) ) {
					$name = explode( '.', $key );
				}
				if ( 1 < count( $name ) ) {
					$name = $name[0];
					$key  = str_replace( $name . ':', '', $key );
					$key  = str_replace( $name . '.', '', $key );
					if ( is_array( $value ) ) {
						$value = array_unique( $value );
						if ( 1 === count( $value ) ) {
							$value = array_shift( $value );
						}
					}
					if ( ! isset( $return[ $name ] ) ) {
						$return[ $name ] = array(
							$key => $value,
						);
					} else {
						if ( is_string( $return[ $name ] ) ) {
							$return[ $name ] = array( $return[ $name ] );
						}
						$return[ $name ][ $key ] = $value;
					}
				} else {
					$return[ $key ] = $value;
				}
			}
		}
		return $return;
	}
}
