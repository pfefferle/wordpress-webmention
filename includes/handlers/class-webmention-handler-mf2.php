<?php
/**
 * Class for webmention parsing using Microformats 2.
 */
class Webmention_Handler_MF2 extends Webmention_Handler_Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'mf2';

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

		$dom = clone $request->get_domdocument();
		if ( ! class_exists( '\Webmention\Mf2\Parser' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'libraries/mf2/Mf2/Parser.php';
		}
		$url      = $request->get_url();
		$parser   = new Webmention\Mf2\Parser( $domdocument, $url );
		$mf_array = $parser->parse();

		// Attempts to remove everything but the representative item.
		$mf_array = $this->get_representative_item( $mf_array, $url );

		// Only store the raw representative item and discard other information.
		$this->webmention_item->set__raw( $mf_array );

		// Retrieve time properties if available.
		$this->webmention_item->set_published( $this->get_datetime_property( 'published', $mf_array ) );
		$this->webmention_item->set_updated( $this->get_datetime_property( 'updated', $mf_array ) );

		$this->webmention_item->set_url( $url );
		$this->webmention_item->set_author( $this->get_author( $mf_array ) );
		$this->webmention_item->set_category( $this->get_plaintext( $mf_array, 'category' ) );
		$this->webmention_item->set_syndication( $this->get_plaintext( $mf_array, 'syndication' ) );

		// Sometimes the featured image is stored in featured. Otherwise try photo.
		$this->webmention_item->set_photo( $this->get_plaintext( $mf_array, 'featured' ) );
		$this->webmention_item->set_photo( $this->get_plaintext( $mf_array, 'photo' ) );

		$this->webmention_item->set_location( $this->get_location( $mf_array, 'location' ) );

		$this->webmention_item->set_summary( $this->get_html( $mf_array, 'summary' ) );
		$this->webmention_item->set_content( $this->get_html( $mf_array, 'content' ) );
		return true;

	}

	/**
	 * Is string a URL.
	 *
	 * @param array $string
	 *
	 * @return bool
	 */
	protected function is_url( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}

		// If debugging is on just validate that URL is validly formatted
		if ( WP_DEBUG ) {
			return filter_var( $string, FILTER_VALIDATE_URL ) !== false;
		}

		// If debugging is off limit based on WordPress parameters
		return wp_http_validate_url( $string );
	}

	/**
	 * Is this what type?
	 *
	 * @param array  $mf   Parsed Microformats Array
	 * @param string $type Type
	 *
	 * @return bool
	*/
	protected function is_type( $mf, $type ) {
		return is_array( $mf ) && ! empty( $mf['type'] ) && is_array( $mf['type'] ) && in_array( $type, $mf['type'], true );
	}

	/**
	 * Verifies if $mf is an array without numeric keys, and has a 'properties' key.
	 *
	 * @param $mf
	 *
	 * @return bool
	 */
	protected function is_microformat( $mf ) {
		return ( is_array( $mf ) && ! wp_is_numeric_array( $mf ) && ! empty( $mf['type'] ) && isset( $mf['properties'] ) );
	}

	/**
	 * Verifies if property named $propname is in array $mf.
	 *
	 * @param array  $mf
	 * @param string $propname
	 *
	 * @return bool
	 */
	protected function has_property( array $mf, $propname ) {
		return ! empty( $mf['properties'][ $propname ] ) && is_array( $mf['properties'][ $propname ] );
	}

	/**
	 * Verifies if rel named $relname is in array $mf.
	 *
	 * @param array  $mf
	 * @param string $relname
	 *
	 * @return bool
	 */
	protected function has_rel( array $mf, $relname ) {
		return ! empty( $mf['rels'][ $relname ] ) && is_array( $mf['rels'][ $relname ] );
	}

	/**
	 * Verifies if $p is an array without numeric keys and has key 'value' and 'html' set.
	 *
	 * @param $p
	 *
	 * @return bool
	 */
	protected function is_embedded_html( $p ) {
		return is_array( $p ) && ! wp_is_numeric_array( $p ) && isset( $p['value'] ) && isset( $p['html'] );
	}

	/**
	 * If $v is a microformat or embedded html, return $v['value']. Else return v.
	 *
	 * @param $v
	 *
	 * @return mixed
	 */
	protected function to_plaintext( $v ) {
		if ( $this->is_microformat( $v ) || $this->is_embedded_html( $v ) ) {
			return $v['value'];
		} elseif ( is_array( $v ) && isset( $v['text'] ) ) {
			return $v['text'];
		}
		return $v;
	}

	/**
	 * Returns plaintext of $propname with optional $fallback.
	 *
	 * @param array       $mf Microformats Array.
	 * @param $propname Property to be retrieved.
	 * @param null|string $fallback Fallback if not available.
	 * @return mixed|null Return value.
	 */
	protected function get_plaintext( array $mf, $propname, $fallback = null ) {
		if ( ! empty( $mf['properties'][ $propname ] ) && is_array( $mf['properties'][ $propname ] ) ) {
			return $this->to_plaintext( current( $mf['properties'][ $propname ] ) );
		}
		return $fallback;
	}

	/**
		 * Returns ['html'] element of $v, or ['value'] or just $v, in order of availablility.
			 *
				 * @param $v Microformats Content.
					 * @return mixed HTML Element if present.
						 */
	protected function to_html( $v ) {
		if ( $this->is_embedded_html( $v ) ) {
			return $v['html'];
		} elseif ( $this->is_microformat( $v ) ) {
			return webmention_sanitize_html( htmlspecialchars( $v['value'] ) );
		}
		return webmention_sanitize_html( htmlspecialchars( $v ) );
	}

	/**
	 * Gets HTML of $propname or if not, $fallback.
	 *
	 * @param array       $mf Microformats JSON array.
	 * @param $propname Property Name.
	 * @param null|string $fallback Fallback if property not found.
	 * @return mixed|null Value of proerty.
	 */
	protected function get_html( array $mf, $propname, $fallback = null ) {
		if ( ! empty( $mf['properties'][ $propname ] ) && is_array( $mf['properties'][ $propname ] ) ) {
			return $this->to_html( current( $mf['properties'][ $propname ] ) );
		}
																														return $fallback;
	}

	/**
	 * Gets the DateTime properties including published or updated, depending on params.
	 *
	 * @param $name string updated or published.
	 * @param array                            $mf Microformats JSON array.
	 * @param null|DateTimeImmutable           $fallback What to return if not a DateTime property.
	 * @return mixed|null
	 */
	protected function get_datetime_property( $name, array $mf, $fallback = null ) {
		if ( $this->has_property( $mf, $name ) ) {
			$return = $this->get_plaintext( $mf, $name );
		} else {
			return $fallback;
		}
		try {
			return new DateTimeImmutable( $return );
		} catch ( Exception $e ) {
			return $fallback;
		}
	}

	/**
	 * get all top-level items.
	 *
	 * @param array $mf_array the microformats array.
	 * @param array an array of top level elements array.
	 *
	 * @return array Return the top level items in an array.
	 */
	protected function get_items( $mf_array ) {
		if ( ! $this->is_microformat_collection( $mf_array ) ) {
			return array();
		}

			// Get first item.
			$first_item = $mf_array['items'][0];

			// Check if it is an h-feed.
		if ( $this->is_type( $first_item, 'h-feed' ) && array_key_exists( 'children', $first_item ) ) {
			$mf_array['items'] = $first_item['children'];
		}
			// Return entries.
			return $mf_array['items'];
	}

			/**
			 * Helper to find the correct h- node.
			 *
			 * @param array $mf The parsed microformats json.
			 * @param string $url the retrieved url.
			 *
			 * @return array the h- node or false/
			 */
	protected function find_representative_item( $mf, $url ) {
		$items = $this->get_items( $mf );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return false;
		}
		if ( 1 === count( $items ) ) {
			return $items[0];
		}
		// Iterate array
		foreach ( $items as $item ) {
			if ( $this->compare_urls( $url, $item['properties']['url'] ) ) {
				return $item;
			}
		}
		return false;
	}

	/**
	 * Takes the mf2 json array passed through and returns a cleaned up representative item.
	 *
	 * @param $mf The entire mf array.
	 * @param $url The source URL.
	 *
	 * @return array Return the representative item on the page.
	 */
	protected function get_representative_item( $mf, $url ) {
		$item = $this->find_representative_item( $mf, $url );
		if ( empty( $item ) || ! is_array( $item ) ) {
			return array();
		}
		// If entry does not have an author try to find one elsewhere.
		if ( ! $this->has_property( $item, 'author' ) ) {
			$item['properties']['author'] = $this->get_representative_author( $mf, $url );
		}

		// If u-syndication is not set use rel syndication.
		if ( array_key_exists( 'syndication', $mf['rels'] ) && ! $this->has_property( $item, 'syndication' ) ) {
			$item['properties']['syndication'] = $mf['rels']['syndication'];
		}

		return $item;
	}

	/**
	 * Helper to find the correct author node.
	 *
	 * @param array $item Item to find an author on.
	 * @param array $mf The parsed microformats array.
	 * @param string $source The source url.
	 * @see http://indieweb.org/authorship
	 *
	 * @return array|null the h-card node or null.
	 */
	protected function get_representative_author( $item, $mf ) {
		$authorpage = false;

		if ( $this->has_property( $item, 'author' ) ) {
			// Check if any of the values of the author property are an h-card.
			foreach ( $item['properties']['author'] as $a ) {
				if ( $this->is_type( $a, 'h-card' ) ) {
					// 5.1 "if it has an h-card, use it, exit."
					return $a;
				} elseif ( is_string( $a ) ) {
					if ( wp_http_validate_url( $a ) ) {
						// 5.2 "otherwise if author property is an http(s) URL, let the author-page have that URL"
						$authorpage = $a;
					} else {
						// 5.3 "otherwise use the author property as the author name, exit"
						// We can only set the name, no h-card or URL was found.
						$author = $this->get_plaintext( $item, 'author' );
					}
				} else {
					// This case is only hit when the author property is an mf2 object that is not an h-card.
					$author = $this->get_plaintext( $item, 'author' );
				}
				if ( ! $authorpage ) {
					return array(
						'type'       => array( 'h-card' ),
						'properties' => array(
							'name' => array( $author ),
						),
					);
				}
			}
		}

		// 6. "if no author page was found" ... check for rel-author link.
		if ( ! $authorpage ) {
			if ( isset( $mf2['rels'] ) && isset( $mf2['rels']['author'] ) ) {
				$authorpage = $mf2['rels']['author'][0];
			}
		}

		// 7. "if there is an author-page URL" .
		if ( $authorpage ) {
			if ( ! $this->urls_match( $authorpage, $this->get_plaintext( $mf2, 'url' ) ) ) {
				return array(
					'type'       => array( 'h-card' ),
					'properties' => array(
						'url' => array( $authorpage ),
					),
				);
			}
		}
	}

	/**
	 * Returns the first item in $val if it's a non-empty array, otherwise $val itself.
	 */
	protected function first( $val ) {
		if ( $val && is_array( $val ) ) {
			return $val[0];
		}
		return $val;
	}


	protected function get_location( $mf ) {
		$return = array();
		// Check and parse for location property
		if ( $this->has_property( $mf, 'location' ) ) {
			$location = $mf['properties']['location'];
			if ( is_array( $location ) ) {
				if ( array_key_exists( 'latitude', $location ) ) {
					$return['latitude'] = $this->first( $location['latitude'] );
				}
				if ( array_key_exists( 'longitude', $location ) ) {
					$return['longitude'] = $this->first( $location['longitude'] );
				}
				if ( array_key_exists( 'name', $location ) ) {
					$return['name'] = $this->first( $location['name'] );
				}
			} else {
				if ( substr( $location, 0, 4 ) === 'geo:' ) {
					$geo    = explode( ':', substr( urldecode( $location ), 4 ) );
					$geo    = explode( ';', $geo[0] );
					$coords = explode( ',', $geo[0] );
					$return = array(
						'latitude'  => trim( $coords[0] ),
						'longitude' => trim( $coords[1] ),
					);
				} else {
					$return = array( 'name' => $location );
				}
			}
		}
		return $return;
	}

	/**
	 * Takes author property and returns simplified array of selected properties.
	 *
	 * @param array $mf_array
	 * @param array Author array.
	 */
	protected function get_author( $mf_array ) {
		$author = array();
		foreach ( array( 'name', 'url', 'email', 'photo' ) as $prop ) {
			$author[ $prop ] = $this->get_plaintext( $mf_array, $prop );
		}
		return array_filter( $author );
	}

	/**
	 * Compare an url with a list of urls.
	 *
	 * @param string  $needle      The target url.
	 * @param array   $haystack    A list of urls.
	 * @param boolean $schemeless Define if the target url should be checked with http:// and https:// .
	 *
	 * @return boolean
	 */
	public function compare_urls( $needle, $haystack, $schemeless = true ) {
		if ( ! $this->is_url( $needle ) ) {
			return false;
		}
		if ( is_array( reset( $haystack ) ) ) {
			return false;
		}
		if ( true === $schemeless ) {
			// Remove url-scheme.
			$schemeless_target = preg_replace( '/^https?:\/\//i', '', $needle );

			// Add both urls to the needle.
			$needle = array( 'http://' . $schemeless_target, 'https://' . $schemeless_target );
		} else {
			// Make $needle an array.
			$needle = array( $needle );
		}

		// Compare both arrays.
		return array_intersect( $needle, $haystack );
	}

	/**
	 * See if urls match for each component of parsed urls. Return true if so.
	 *
	 * @param $url1
	 * @param $url2
	 * @return bool
	 * @see parseUrl()
	 */
	protected function urls_match( $url1, $url2 ) {
		return ( normalize_url( $url1 ) === normalize_url( $url2 ) );
	}

}
