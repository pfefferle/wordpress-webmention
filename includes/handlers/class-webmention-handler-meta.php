<?php
/**
 * Class for webmention parsing using META tags.
*/
class Webmention_Handler_Meta extends Webmention_Handler_Base {

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
		}
		$dom   = clone $request->get_domdocument();
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
			$meta = self::add_property( $meta, $meta_name, $meta_value );
		}

		$meta['title'] = trim( $xpath->query( '//title' )->item( 0 )->textContent );
		$meta          = self::parse_ogp( $meta );
		if ( isset( $meta['og'] ) ) {
			$meta['og'] = self::parse_ogp( $meta['og'] );
		}

		$this->webmention_item = new Webmention_Item();

		// Set raw data.
		$this->webmention_item->set__raw( $meta );
		$this->webmention_item->set__response_type = 'mention';

		$item = $this->ogp( $meta );
		$item = array_merge( $item, $this->dublin_core( $meta ) );

		// If Site Name is not set use domain name less www
		if ( ! isset( $item['_site_name'] ) && isset( $item['url'] ) ) {
			$item['_site_name'] = preg_replace( '/^www\./', '', wp_parse_url( $item['url'], PHP_URL_HOST ) );
		}

		if ( ! isset( $item['name'] ) ) {
			$item['name'] = $meta['title'];
		}

		if ( isset( $meta['citation_date'] ) && ! isset( $item['published'] ) ) {
			$item['published'] = new DateTimeImmutable( $meta['citation_date'] );
		} elseif ( isset( $meta['datePublished'] ) ) {
			$item['published'] = new DateTimeImmutable( $meta['datePublished'] );
		}

		if ( ! isset( $item['author'] ) ) {
			$item['author'] = array( 'name' => $meta['author'] );
		}

		$item = array_filter( $item );
		foreach ( $item as $key => $value ) {
			$key = 'set_' . $key;
			$this->webmention_item->$key( $value );
		}
	}

	protected function ogp( $meta ) {
		$item = array();
		// Start by looking at OGP.
		if ( isset( $meta['og'] ) ) {
			$og = $meta['og'];
			if ( isset( $og['url'] ) ) {
				$item['url'] = $og['url'];
			}
			if ( isset( $og['title'] ) ) {
				$item['name'] = $og['title'];
			}
			if ( isset( $og['description'] ) ) {
				$item['summary'] = $og['description'];
			}
			if ( isset( $og['image'] ) ) {
				$image = $og['image'];
				if ( is_string( $image ) ) {
					$item['photo'] = array( $image );
				} elseif ( wp_is_numeric_array( $image ) ) {
					$item['photo'] = array( $image[0] );
				} else {
					$item['photo'] = array( $image['secure_url'] );
				}
			}
			if ( isset( $og['site_name'] ) ) {
				$item['_site_name'] = $og['site_name'];
			}

			if ( isset( $og['longitude'] ) ) {
				$item['location'] = array(
					'longitude' => $og['longitude'],
					'latitude'  => $og['longitude'],
				);
			}
			if ( isset( $og['type'] ) ) {
				$type = $og['type'];
				if ( isset( $meta[ $type ]['tag'] ) ) {
					$item['category'] = $meta[ $type ]['tag'];
				}
				if ( ! empty( $meta[ $type ]['author'] ) ) {
					$item['author'] = array( 'name' => $meta[ $type ]['author'] );
				}
				if ( 'article' === $type ) {
					if ( isset( $meta['article']['published_time'] ) ) {
						$item['published'] = new DateTimeImmutable( $meta['article']['published_time'] );
					} elseif ( isset( $meta['article']['published'] ) ) {
						$item['published'] = new DateTimeImmutable( $meta['article']['published'] );
					}
					if ( isset( $meta['article']['modified_time'] ) ) {
						$item['updated'] = new DateTimeImmutable( $meta['article']['modified_time'] );
					} elseif ( isset( $meta['article']['modified'] ) ) {
						$item['updated'] = new DateTimeImmutable( $meta['article']['modified'] );
					}
				}
			}
		}
		return $item;
	}

	protected function dublin_core( $meta ) {
		$item = array();
		// Then look at Dublin Core Properties
		if ( isset( $meta['dc'] ) ) {
			$dc = $meta['dc'];
			if ( isset( $dc['Title'] ) ) {
				$item['name'] = $dc['Title'];
			}
			if ( isset( $dc['Creator'] ) ) {
				if ( is_string( $dc['Creator'] ) ) {
					$item['author'] = array( 'name' => $dc['Creator'] );
				}
			}
			if ( isset( $dc['Description'] ) ) {
				$item['summary'] = $dc['Description'];
			}
			if ( isset( $dc['Date'] ) && ! isset( $item['published'] ) ) {
				$item['published'] = ( new DateTimeImmutable( $dc['Date'] ) );
			}
		}
		return $item;
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
