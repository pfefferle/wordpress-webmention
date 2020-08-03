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
	public function parse( Webmention_Request $request ) {
		$dom = clone $request->get_domdocument();
		if ( ! class_exists( '\Webmention\Mf2\Parser' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '../../libraries/mf2/Mf2/Parser.php';
		}

		$url      = $request->get_url();
		$parser   = new Webmention\Mf2\Parser( $dom, $url );
		$mf_array = $parser->parse();

		// Attempts to remove everything but the representative item.
		$mf_array = $this->get_representative_item( $mf_array, $url );
		$return   = $this->add_properties( $mf_array );
		$this->webmention_item->set_url( $url ); // If there is no URL property then use the retrieved URL.
		return is_wp_error( $return ) ? $return : true;
	}


	/**
	 * Takes mf2 json and generates a Webmention Item.
	 *
	 * @param array $mf_array JSON Array of Parsed Microformats.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function add_properties( $mf_array ) {

		// Only store the raw representative item and discard other information.
		$this->webmention_item->set_raw( $mf_array );

		// Retrieve time properties if available.
		$this->webmention_item->set_published( $this->get_datetime_property( 'published', $mf_array ) );
		$this->webmention_item->set_updated( $this->get_datetime_property( 'updated', $mf_array ) );

		$this->webmention_item->set_url( $this->get_plaintext( $mf_array, 'url' ) );
		$this->webmention_item->set_author( $this->get_author( $mf_array ) );
		$this->webmention_item->set_category( $this->get_property( $mf_array, 'category' ) );
		$this->webmention_item->set_syndication( $this->get_plaintext( $mf_array, 'syndication' ) );

		// Sometimes the featured image is stored in featured. Otherwise try photo.
		$this->webmention_item->set_photo( $this->get_plaintext( $mf_array, 'featured' ) );
		$this->webmention_item->set_photo( $this->get_plaintext( $mf_array, 'photo' ) );

		$this->webmention_item->set_location( $this->get_location( $mf_array ) );

		$this->webmention_item->set_summary( $this->get_html( $mf_array, 'summary' ) );
		$this->webmention_item->set_content( $this->get_html( $mf_array, 'content' ) );
		$this->webmention_item->set_response_type( $this->response_type_discovery( $mf_array ) );

		return true;
	}

	/**
	 * Returns the response type based on parsed microformats.
	 * Adds some experimental response types.
	 * @link http://ptd.spec.indieweb.org/#response-algorithm
	 *
	 * @param array $mf_array JSON Array of Parsed Microformats.
	 * @return string Response Type. Default 'mention'.
	 */
	public function response_type_discovery( $mf_array ) {
		if ( ! class_exists( '\Webmention\Emoji' ) ) {
			require_once plugin_dir_path( __FILE__ ) . '../../libraries/emoji-detector/src/Emoji.php';
		}
		// Sanity check.
		if ( ! $this->is_microformat( $mf_array ) ) {
			return 'mention';
		}

		/*
		 * rsvp
		 * @link http://indieweb.org/rsvp
		 */
		if ( $this->has_property( $mf_array, 'rsvp' ) ) {
			return sprintf( 'rsvp:%1$s', $this->get_plaintext( $mf_array, 'rsvp' ) );
		}

		/*
		 * invitation
		 * Make sure invitation is to this site.
		 * @link http://indieweb.org/invitation
		 */
		if ( $this->has_url_property( $mf_array, 'invite' ) && $this->urls_match( $this->get_plaintext( $mf_array, 'invite' ), home_url() ) ) {
			return 'invite';
		}

		/*
		 * tag-reply
		 * @link http://indieweb.org/tag-reply
		 */
		if ( $this->has_url_property( $mf_array, 'tag-of' ) ) {
			return 'tag-reply';
		}

		/*
		 * person-tag
		 * @link http://indieweb.org/person-tag
		 */
		if ( $this->has_url_property( $mf_array, 'category' ) && $this->is_type( $mf_array, 'h-card' ) ) {
			return 'person-tag';
		}

		/*
		 * reposts
		 * @link http://indieweb.org/reposts
		 */
		if ( $this->has_url_property( $mf_array, 'repost-of' ) ) {
			return 'repost';
		}

		/*
		 * likes
		 * @link http://indieweb.org/likes
		 */
		if ( $this->has_url_property( $mf_array, 'like-of' ) ) {
			return 'like';
		}

		/*
		 * Semantic Linkbacks supported favorites and it should be supported here.
		 * @link http://indieweb.org/favorites
		 */
		if ( $this->has_url_property( $mf_array, 'favorite-of' ) ) {
			return 'favorite';
		}

		/*
		 * bookmarks
		 * @link http://indieweb.org/bookmarks
		 */
		if ( $this->has_url_property( $mf_array, 'bookmark-of' ) ) {
			return 'bookmark';
		}

		/*
		 * read
		 * @link http://indieweb.org/read
		 */
		if ( $this->has_url_property( $mf_array, 'read-of' ) ) {
			return 'read';
		}

		/*
		 * listen
		 * @link http://indieweb.org/listen
		 */
		if ( $this->has_url_property( $mf_array, 'listen-of' ) ) {
			return 'listen';
		}

		/*
		 * watch
		 * @link http://indieweb.org/watch
		 */
		if ( $this->has_url_property( $mf_array, 'watch-of' ) ) {
			return 'watch';
		}

		/*
		 * follow
		 * @link http://indieweb.org/follow
		 */
		if ( $this->has_url_property( $mf_array, 'follow-of' ) ) {
			return 'follow';
		}

		if ( \Webmention\Emoji\is_single_emoji( $this->get_plaintext( $mf_array, 'content' ) ) ) {
			return 'reacji';
		}

		/*
		 * replies
		 * @link http://indieweb.org/replies
		 */
		if ( $this->has_url_property( $mf_array, 'in-reply-to' ) || $this->has_rel( $mf_array, 'in-reply-to' ) ) {
			return 'reply';
		}

		return 'mention';

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
	 * Returns type
	 *
	 * @param array       $mf Microformats Array.
	 * @return string|null Return value.
	 */
	protected function get_type( array $mf ) {
		if ( ! $this->is_microformat( $mf ) ) {
			return null;
		}
		return str_replace( 'h-', '', $mf['type'][0] );
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
	 * Verifies if $mf has an 'items' key which is also an array, returns true.
	 *
	 * @param $mf
	 *
	 * @return bool
	 */
	protected function is_microformat_collection( $mf ) {
		return ( is_array( $mf ) && isset( $mf['items'] ) && is_array( $mf['items'] ) );
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
	 * Verifies if property named $propname is in array $mf and is a valid URL.
	 *
	 * @param array  $mf
	 * @param string $propname
	 *
	 * @return bool
	 */
	protected function has_url_property( array $mf, $propname ) {
		return ( $this->has_property( $mf, $propname ) && ( $this->is_url( $this->get_plaintext( $mf, $propname ) ) ) );
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
	 * Returns property $propname  $fallback.
	 *
	 * @param array       $mf Microformats Array.
	 * @param $propname Property to be retrieved.
	 * @param null|string $fallback Fallback if not available.
	 * @return mixed|null Return value.
	 */
	protected function get_property( array $mf, $propname, $fallback = null ) {
		if ( ! empty( $mf['properties'][ $propname ] ) && is_array( $mf['properties'][ $propname ] ) ) {
			return $mf['properties'][ $propname ];
		}
		return $fallback;
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
		$first_item = current( $mf_array['items'] );

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
			if ( $this->urls_match( $url, $this->get_plaintext( $item, 'url' ) ) ) {
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

	protected function get_location( $mf ) {
		$return = array();
		// Check and parse for location property
		if ( $this->has_property( $mf, 'location' ) ) {
			$mf = current( $this->get_property( $mf, 'location' ) );
		} else {
			return null;
		}

		if ( $this->is_microformat( $mf ) ) {
			foreach ( array( 'latitude', 'longitude', 'label', 'name', 'locality', 'region', 'country-name', 'altitude' ) as $prop ) {
				$return[ $prop ] = $this->get_plaintext( $mf, $prop );
			}
			$return['type'] = $this->get_type( $mf );
		} else {
			if ( substr( $mf, 0, 4 ) === 'geo:' ) {
				$geo    = explode( ':', substr( urldecode( $mf ), 4 ) );
				$geo    = explode( ';', $geo[0] );
				$coords = explode( ',', $geo[0] );
				$return = array(
					'type'      => 'geo',
					'latitude'  => trim( $coords[0] ),
					'longitude' => trim( $coords[1] ),
					'altitude'  => trim( ifset( $coords[2], '' ) ),
				);
			} else {
				$return = array(
					'type'  => 'adr',
					'label' => $mf,
				);
			}
		}
		return array_filter( $return );
	}

	/**
	 * Takes author property and returns simplified array of selected properties.
	 *
	 * @param array $mf_array
	 * @param array Author array.
	 */
	protected function get_author( $mf_array ) {
		$properties = current( $this->get_property( $mf_array, 'author' ) );
		if ( empty( $properties ) ) {
			return null;
		}
		$author = array( 'type' => 'card' );
		if ( $this->is_microformat( $properties ) ) {
			foreach ( array( 'name', 'nickname', 'given-name', 'family-name', 'url', 'email', 'photo' ) as $prop ) {
				$author[ $prop ] = $this->get_plaintext( $properties, $prop );
			}
		} else {
			$text = $this->get_plaintext( $mf_array, 'author' );
			if ( wp_http_validate_url( $text ) ) {
				$author['url'] = $text;
			} else {
				$author['name'] = $text;
			}
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
