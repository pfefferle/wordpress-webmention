<?php

namespace Webmention\Handler;

use WP_Error;
use Exception;
use DateTimeImmutable;
use Webmention\Response;
use Webmention\Mf2\Parser;
use Webmention\Request;

/**
 * Class for Webmention parsing using Microformats 2.
 */
class MF2 extends Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'mf2';

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
		if ( ! class_exists( '\Webmention\Mf2\Parser' ) ) {
			require_once WEBMENTION_PLUGIN_DIR . '/libraries/mf2/Mf2/Parser.php';
		}

		$source_url = $response->get_url();
		$parser     = new Parser( $dom, $source_url );
		$data       = $parser->parse();

		// Attempts to remove everything but the representative item.
		$item = $this->get_representative_item( $data, $target_url );
		if ( ! $item ) {
			return false;
		}

		$author = $this->get_representative_author( $item, $data );

		// add response type
		$response_type = $this->get_response_type( $item, $data, $target_url );

		$this->set_properties( $item );
		$this->set_property_author( $author );

		$this->webmention_item->add_response_type( wp_slash( $response_type ) );

		$this->webmention_item->add_url( $source_url ); // If there is no URL property then use the retrieved URL.

		return true;
	}


	/**
	 * Takes mf2 item and generates a Webmention Item.
	 *
	 * @param array $mf_array JSON Array of Parsed Microformats.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function set_properties( $mf_array ) {

		// Only store the raw representative item and discard other information.
		$this->webmention_item->add_raw( $mf_array );

		// Retrieve time properties if available.
		$this->webmention_item->add_published( $this->get_datetime_property( 'published', $mf_array ) );
		$this->webmention_item->add_updated( $this->get_datetime_property( 'updated', $mf_array ) );

		$this->webmention_item->add_url( $this->get_plaintext( $mf_array, 'url' ) );

		// Sometimes the featured image is stored in featured. Otherwise try photo.
		$this->webmention_item->add_photo( $this->get_plaintext( $mf_array, 'featured' ) );
		$this->webmention_item->add_photo( $this->get_plaintext( $mf_array, 'photo' ) );

		$content = $this->get_html( $mf_array, 'content' );
		$this->webmention_item->add_content( $content );

		$summary = $this->get_plaintext( $mf_array, 'summary' );
		if ( empty( $summary ) ) {
			$summary = $this->generate_summary( $content );
		}

		$this->webmention_item->add_summary( $summary );
		$this->webmention_item->add_meta( apply_filters( 'webmention_handler_mf2_set_properties', array(), $this ) );

		return true;
	}

	/**
	 * Takes author property and returns simplified array of selected properties.
	 *
	 * @param array $mf_array
	 * @param array Author array.
	 */
	protected function set_property_author( $properties ) {
		$author = array( 'type' => 'card' );
		if ( $this->is_microformat( $properties ) ) {
			foreach ( array( 'name', 'nickname', 'given-name', 'family-name', 'url', 'email', 'photo' ) as $prop ) {
				$author[ $prop ] = $this->get_plaintext( $properties, $prop );
			}
		}

		$this->webmention_item->add_author( array_filter( $author ) );
	}

	/**
	 * Is string a URL.
	 *
	 * @param array $string
	 *
	 * @return bool
	 */
	protected function is_url( $str ) {
		if ( ! is_string( $str ) ) {
			return false;
		}

		// If debugging is on just validate that URL is validly formatted
		if ( WP_DEBUG ) {
			return filter_var( $str, FILTER_VALIDATE_URL ) !== false;
		}

		// If debugging is off limit based on WordPress parameters
		return wp_http_validate_url( $str );
	}

	/**
	 * Is this what type?
	 *
	 * @param array  $mf_array   Parsed Microformats Array
	 * @param string $type Type
	 *
	 * @return bool
	*/
	protected function is_type( $mf_array, $type ) {
		return is_array( $mf_array ) && ! empty( $mf_array['type'] ) && is_array( $mf_array['type'] ) && in_array( $type, $mf_array['type'], true );
	}

	/**
	 * Returns type
	 *
	 * @param array       $mf_array Microformats Array.
	 * @return string|null Return value.
	 */
	protected function get_type( $mf_array ) {
		if ( ! $this->is_microformat( $mf_array ) ) {
			return null;
		}
		return str_replace( 'h-', '', $mf_array['type'][0] );
	}

	/**
	 * Verifies if $mf_array is an array without numeric keys, and has a 'properties' key.
	 *
	 * @param $mf_array
	 *
	 * @return bool
	 */
	protected function is_microformat( $mf_array ) {
		return ( is_array( $mf_array ) && ! wp_is_numeric_array( $mf_array ) && ! empty( $mf_array['type'] ) && isset( $mf_array['properties'] ) );
	}

	/**
	 * Verifies if $mf_array has an 'items' key which is also an array, returns true.
	 *
	 * @param $mf_array
	 *
	 * @return bool
	 */
	protected function is_microformat_collection( $mf_array ) {
		return ( is_array( $mf_array ) && isset( $mf_array['items'] ) && is_array( $mf_array['items'] ) );
	}

	/**
	 * Verifies if property named $propname is in array $mf_array.
	 *
	 * @param array  $mf_array
	 * @param string $propname
	 *
	 * @return bool
	 */
	protected function has_property( array $mf_array, $propname ) {
		return ! empty( $mf_array['properties'][ $propname ] ) && is_array( $mf_array['properties'][ $propname ] );
	}

	/**
	 * Verifies if property named $propname is in array $mf_array and is a valid URL.
	 *
	 * @param array  $mf_array
	 * @param string $propname
	 *
	 * @return bool
	 */
	protected function has_url_property( array $mf_array, $propname ) {
		return ( $this->has_property( $mf_array, $propname ) && ( $this->is_url( $this->get_plaintext( $mf_array, $propname ) ) ) );
	}

	/**
	 * Verifies if rel named $relname is in array $mf_array.
	 *
	 * @param array  $mf_array
	 * @param string $relname
	 *
	 * @return bool
	 */
	protected function has_rel( array $mf_array, $relname ) {
		return ! empty( $mf_array['rels'][ $relname ] ) && is_array( $mf_array['rels'][ $relname ] );
	}

	/**
	 * Verifies if $property is an array without numeric keys and has key 'value' and 'html' set.
	 *
	 * @param $property
	 *
	 * @return bool
	 */
	protected function is_embedded_html( $property ) {
		return is_array( $property ) && ! wp_is_numeric_array( $property ) && isset( $property['value'] ) && isset( $property['html'] );
	}

	/**
	 * If $value is a microformat or embedded html, return $value['value']. Else return v.
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	protected function to_plaintext( $value ) {
		if ( $this->is_microformat( $value ) || $this->is_embedded_html( $value ) ) {
			return $value['value'];
		} elseif ( is_array( $value ) && isset( $value['text'] ) ) {
			return $value['text'];
		}
		return $value;
	}

	/**
	 * Returns property $propname  $fallback.
	 *
	 * @param array       $mf Microformats Array.
	 * @param $propname Property to be retrieved.
	 * @param null|string $fallback Fallback if not available.
	 * @return mixed|null Return value.
	 */
	protected function get_property( array $mf_array, $propname, $fallback = null ) {
		if ( ! empty( $mf_array['properties'][ $propname ] ) && is_array( $mf_array['properties'][ $propname ] ) ) {
			return $mf_array['properties'][ $propname ];
		}
		return $fallback;
	}

	/**
	 * Returns plaintext of $propname with optional $fallback.
	 *
	 * @param array       $mf_array Microformats Array.
	 * @param $propname Property to be retrieved.
	 * @param null|string $fallback Fallback if not available.
	 * @return mixed|null Return value.
	 */
	protected function get_plaintext( array $mf_array, $propname, $fallback = null ) {
		if ( ! array_key_exists( 'properties', $mf_array ) ) {
			return $fallback;
		}
		if ( ! empty( $mf_array['properties'][ $propname ] ) && is_array( $mf_array['properties'][ $propname ] ) ) {
			return $this->to_plaintext( current( $mf_array['properties'][ $propname ] ) );
		}
		return $fallback;
	}

	/**
	 * Returns ['html'] element of $value, or ['value'] or just $value, in order of availablility.
	 *
	 * @param $value Microformats Content.
	 * @return mixed HTML Element if present.
	 */
	protected function to_html( $value ) {
		if ( $this->is_embedded_html( $value ) ) {
			return $value['html'];
		} elseif ( $this->is_microformat( $value ) ) {
			return webmention_sanitize_html( htmlspecialchars( $value['value'] ) );
		}
		return webmention_sanitize_html( htmlspecialchars( $value ) );
	}

	/**
	 * Gets HTML of $propname or if not, $fallback.
	 *
	 * @param array       $mf_array Microformats JSON array.
	 * @param $propname Property Name.
	 * @param null|string $fallback Fallback if property not found.
	 * @return mixed|null Value of proerty.
	 */
	protected function get_html( array $mf_array, $propname, $fallback = null ) {
		if ( ! empty( $mf_array['properties'][ $propname ] ) && is_array( $mf_array['properties'][ $propname ] ) ) {
			return $this->to_html( current( $mf_array['properties'][ $propname ] ) );
		}
		return $fallback;
	}

	/**
	 * Gets the DateTime properties including published or updated, depending on params.
	 *
	 * @param $name string updated or published.
	 * @param array                            $mf_array Microformats JSON array.
	 * @param null|DateTimeImmutable           $fallback What to return if not a DateTime property.
	 * @return mixed|null
	 */
	protected function get_datetime_property( $name, array $mf_array, $fallback = null ) {
		if ( $this->has_property( $mf_array, $name ) ) {
			$return = $this->get_plaintext( $mf_array, $name );
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
	 * helper to find the correct h-entry node
	 *
	 * @param array $mf_array the parsed microformats array
	 * @param string $url the  url
	 *
	 * @return array the h-card node or false
	 */
	public function find_representative_hcard( $mf_array, $url ) {
		if ( ! $this->is_microformat_collection( $mf_array ) ) {
			return false;
		}
		$items = $mf_array['items'];
		if ( ! is_array( $items ) || empty( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( $this->is_type( $item, 'h-card' ) && $this->urls_match( $url, $this->get_plaintext( $item, 'url' ) ) ) {
				return $item;
			}
		}
		return false;
	}


	/**
	 * helper to find the correct h-entry node
	 *
	 * @param array $mf_array the parsed microformats array
	 * @param string $target the target url
	 *
	 * @return array the h-entry node or false
	 */
	public function find_representative_item( $mf_array, $target ) {
		$items = $this->get_items( $mf_array );
		if ( ! is_array( $items ) || empty( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			// check properties
			if ( isset( $item['properties'] ) ) {
				// check properties if target urls was mentioned
				foreach ( $item['properties'] as $key => $values ) {
					// check "normal" links
					if ( $this->compare_urls( $target, $values ) ) {
						return $item;
					}

					// check included h-* formats and their links
					foreach ( $values as $obj ) {
						// check if reply is a "cite"
						if ( isset( $obj['type'] ) && array_intersect( array( 'h-entry', 'h-cite' ), $obj['type'] ) ) {
							// check url
							if ( isset( $obj['properties'] ) ) {
								if ( isset( $obj['properties']['url'] ) ) {
									// check target
									if ( $this->compare_urls( $target, $obj['properties']['url'] ) ) {
										return $item;
									}
								}

								// check properties if target urls was mentioned
								foreach ( $obj['properties'] as $obj_key => $obj_values ) {
									if ( $this->compare_urls( $target, $obj_values ) ) {
										return $item;
									}
									// Make sure this is a numeric array before checking this.
									if ( wp_is_numeric_array( $obj_values ) ) {
										// check content for the link
										if ( 'content' === $obj_key &&
											preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $obj_values[0]['html'], $context ) ) {
											return $item;
										} elseif ( 'summary' === $obj_key &&
											preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $obj_values[0], $context ) ) {
											return $item;
										}
									}
								}
							}
						}
					}
				}

				// check properties if target urls was mentioned
				foreach ( $item['properties'] as $key => $values ) {
					// check content for the link
					if ( 'content' === $key &&
						preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $values[0]['html'], $context ) ) {
						return $item;
					} elseif ( 'summary' === $key &&
						preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $values[0], $context ) ) {
						return $item;
					}
				}
			}
		}

		// return first h-entry
		return false;
	}

	/**
	 * Takes the mf2 json array passed through and returns a cleaned up representative item.
	 *
	 * @param $mf_array The entire mf array.
	 * @param $url The source URL.
	 *
	 * @return array Return the representative item on the page.
	 */
	protected function get_representative_item( $mf_array, $url ) {
		$item = $this->find_representative_item( $mf_array, $url );
		if ( empty( $item ) || ! is_array( $item ) ) {
			return array();
		}

		return $item;
	}

	/**
	 * Helper to find the correct author node.
	 *
	 * @param array $item Item to find an author on.
	 * @param array $mf_array The parsed microformats array.
	 * @param string $source The source url.
	 * @see https://indieweb.org/authorship
	 *
	 * @return array|null the h-card node or null.
	 */
	protected function get_representative_author( $item, $mf_array ) {
		$authorpage = false;

		if ( $this->has_property( $item, 'author' ) ) {
			// Check if any of the values of the author property are an h-card.
			foreach ( $item['properties']['author'] as $author ) {
				if ( $this->is_type( $author, 'h-card' ) ) {
					// 5.1 "if it has an h-card, use it, exit."
					return $author;
				} elseif ( is_string( $author ) ) {
					if ( wp_http_validate_url( $author ) ) {
						// 5.2 "otherwise if author property is an http(s) URL, let the author-page have that URL"
						$authorpage = $author;
					} else {
						// 5.3 "otherwise use the author property as the author name, exit"
						// We can only set the name, no h-card or URL was found.
						$name = $this->get_plaintext( $item, 'author' );
					}
				} else {
					// This case is only hit when the author property is an mf2 object that is not an h-card.
					$name = $this->get_plaintext( $item, 'author' );
				}

				if ( ! $authorpage ) {
					return array(
						'type'       => array( 'h-card' ),
						'properties' => array(
							'name' => array( $name ),
						),
					);
				}
			}
		}

		// Some URLs have a separate h-card. While this is not in the authorship specification assume a top level h-card on a page is the author.
		$items = $this->get_items( $mf_array );
		foreach ( $items as $item ) {
			if ( $this->is_type( $item, 'h-card' ) ) {
				return $item;
			}
		}

		// 6. "if no author page was found" ... check for rel-author link.
		if ( ! $authorpage ) {
			if ( isset( $mf_array['rels'] ) && isset( $mf_array['rels']['author'] ) ) {
				$authorpage = $mf_array['rels']['author'][0];
			}
		}

		// 7. "if there is an author-page URL" .
		if ( $authorpage ) {
			if ( ! $this->urls_match( $authorpage, $this->get_plaintext( $mf_array, 'url' ) ) ) {
				return $this->parse_authorpage( $authorpage );
			}
		}
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

	/**
	 * All supported url types
	 *
	 * @return array
	 */
	protected function get_class_mapper() {
		$class_mapper = array();

		/*
		 * rsvp
		 * @link https://indieweb.org/rsvp
		 */
		$class_mapper['rsvp'] = 'rsvp';

		/*
		 * invite
		 * @link https://indieweb.org/invitation
		 */
		$class_mapper['invitee'] = 'invite';

		/*
		 * repost
		 * @link https://indieweb.org/repost
		 */
		$class_mapper['repost']    = 'repost';
		$class_mapper['repost-of'] = 'repost';

		/*
		 * likes
		 * @link https://indieweb.org/likes
		 */
		$class_mapper['like']    = 'like';
		$class_mapper['like-of'] = 'like';

		/*
		 * favorite
		 * @link https://indieweb.org/favorite
		 */
		$class_mapper['favorite']    = 'favorite';
		$class_mapper['favorite-of'] = 'favorite';

		/*
		 * bookmark
		 * @link https://indieweb.org/bookmark
		 */
		$class_mapper['bookmark']    = 'bookmark';
		$class_mapper['bookmark-of'] = 'bookmark';

		/*
		 * tag
		 * @link https://indieweb.org/tag
		 */
		$class_mapper['tag-of']   = 'tag';
		$class_mapper['category'] = 'tag';

		/*
		 * read
		 * @link https://indieweb.org/read
		 */
		$class_mapper['read-of'] = 'read';
		$class_mapper['read']    = 'read';

		/*
		 * listen
		 * @link https://indieweb.org/listen
		 */
		$class_mapper['listen-of'] = 'listen';
		$class_mapper['listen']    = 'listen';

		/*
		 * watch
		 * @link https://indieweb.org/watch
		 */
		$class_mapper['watch-of'] = 'watch';
		$class_mapper['watch']    = 'watch';

		/*
		 * follow
		 * @link https://indieweb.org/follow
		 */
		$class_mapper['follow-of'] = 'follow';

		/*
		 * replies
		 * @link https://indieweb.org/replies
		 */
		$class_mapper['in-reply-to'] = 'comment';
		$class_mapper['reply']       = 'comment';
		$class_mapper['reply-of']    = 'comment';

		return apply_filters( 'webmention_mf2_class_mapper', $class_mapper );
	}

	/**
	 * All supported url types
	 *
	 * @return array
	 */
	protected function get_rel_mapper() {
		$rel_mapper = array();

		/*
		 * replies
		 * @link https://indieweb.org/in-reply-to
		 */
		$rel_mapper['in-reply-to'] = 'comment';
		$rel_mapper['reply-of']    = 'comment';

		/*
		 * bookmarks
		 * @link https://microformats.org/wiki/rel-design-pattern#rel.3D.22bookmark.22
		 */
		$rel_mapper['bookmark'] = 'bookmark';

		/*
		 * tags
		 * @link https://microformats.org/wiki/rel-tag
		 */
		$rel_mapper['tag'] = 'tag';

		return apply_filters( 'webmention_mf2_rel_mapper', $rel_mapper );
	}

	/**
	 * Check entry classes or document rels for post-type
	 *
	 * @param array  $entry    the represantative entry
	 * @param array  $mf_array the document
	 * @param string $target   the target url
	 *
	 * @return string the post-type
	 */
	protected function get_response_type( $entry, $mf_array, $target ) {
		$classes = $this->get_class_mapper();

		// check properties for target-url
		foreach ( $entry['properties'] as $key => $values ) {
			// check u-* params
			if ( in_array( $key, array_keys( $classes ), true ) ) {
				// check RSVP if available
				if ( 'rsvp' === $key ) {
					return 'rsvp:' . $this->get_first_array_item( $values );
				}

				// check "normal" links
				if ( $this->compare_urls( $target, $values ) ) {
					return $classes[ $key ];
				}

				// iterate in-reply-tos
				foreach ( $values as $obj ) {
					// check if reply is a "cite" or "entry"
					if ( in_array( $this->get_type( $obj ), array( 'cite', 'entry' ), true ) ) {
						// check url
						if ( $this->has_property( $obj, 'url' ) ) {
							// check target
							if ( $this->compare_urls( $target, $obj['properties']['url'] ) ) {
								return $classes[ $key ];
							}
						}

						// check properties if target urls was mentioned
						foreach ( $obj['properties'] as $obj_key => $obj_values ) {
							if ( $this->compare_urls( $target, $obj_values ) ) {
								return $classes[ $key ];
							}
							// check content for the link
							if ( 'content' === $obj_key &&
								preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $obj_values[0]['html'], $context ) ) {
								return $classes[ $key ];
							} elseif ( 'summary' === $obj_key &&
								preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $obj_values[0], $context ) ) {
								return $classes[ $key ];
							}
						}
					}
				}
			}
		}

		// check if site has any rels
		if ( ! isset( $mf_array['rels'] ) ) {
			return 'mention';
		}

		$rels = $this->get_rel_mapper();

		// check rels for target-url
		foreach ( $mf_array['rels'] as $key => $values ) {
			// check rel params
			if ( in_array( $key, array_keys( $rels ), true ) ) {
				foreach ( $values as $value ) {
					if ( $value === $target ) {
						return $rels[ $key ];
					}
				}
			}
		}

		return 'mention';
	}

	/**
	 * Returns the first item in $val if it's a non-empty array, otherwise $val itself.
	 *
	 * @param array|null $val
	 *
	 * @return string
	 */
	protected function get_first_array_item( $val ) {
		if ( $val && is_array( $val ) ) {
			return $val[0];
		}
		return $val;
	}

	/**
	 * Takes a response object and returns an author
	 *
	 * @param string $url Author URL
	 *
	 * @return WP_Error|array Return error or author array if successful.
	 */
	public function parse_authorpage( $url ) {
		$response = Request::get( $url, false );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$dom = $response->get_dom_document();

		if ( is_wp_error( $dom ) ) {
			return $dom;
		}
		if ( ! class_exists( '\Webmention\Mf2\Parser' ) ) {
			require_once WEBMENTION_PLUGIN_DIR . '/libraries/mf2/Mf2/Parser.php';
		}

		$parser = new Parser( $dom, $url );
		$data   = $parser->parse();
		$item   = $this->find_representative_hcard( $data, $url );
		$author = array(
			'type'       => array( 'h-card' ),
			'properties' => array(
				'url' => array( $url ),
			),
		);

		if ( empty( $item ) || ! is_array( $item ) ) {
			$author['properties']['name'] = array( __( 'Anonymous', 'webmention' ) );
		} else {
			foreach ( array( 'name', 'nickname', 'given-name', 'family-name', 'email', 'photo' ) as $prop ) {
				if ( array_key_exists( $prop, $item['properties'] ) ) {
					$author['properties'][ $prop ] = $item['properties'][ $prop ];
				}
			}
		}
		return $author;
	}
}
