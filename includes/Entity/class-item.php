<?php

namespace Webmention\Entity;

use DateTimeZone;
use DateTimeImmutable;

/**
 * Represents a Remote Webmention
 */
class Item {
	/**
	 * The publish time.
	 *
	 * @var DateTimeImmutable
	 */
	protected $published;

	/**
	 * The updated time.
	 *
	 * @var DateTimeImmutable
	 */
	protected $updated;

	/**
	 * The source URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Array of authors.
	 *
	 * @var array
	 */
	protected $author = array();

	/**
	 * Array of photos.
	 *
	 * @var array
	 */
	protected $photo = array();

	/**
	 * The response name.
	 *
	 * @var string
	 */
	protected $name;


	/**
	 * The site name, if available.
	 *
	 * @example comment-meta
	 *
	 * @var string
	 */
	protected $site_name;

	/**
	 * The response content.
	 *
	 * @var string
	 */
	protected $content;

	/**
	 * The response summary.
	 *
	 * @var string
	 */
	protected $summary;

	/**
	 * The response type.
	 *
	 * @example comment-meta
	 *
	 * @var string
	 */
	protected $response_type;

	/**
	 * The raw document as JSON, HTML, XML, ...
	 *
	 * @example comment-meta
	 *
	 * @var mixed
	 */
	protected $raw;

	/**
	 * Optional Parsed Properties to be Saved in Comment Meta.
	 *
	 * @var array
	 */
	protected $meta;

	/**
	 * Magic function for getter/setter.
	 *
	 * @param string $method
	 * @param array  $params
	 *
	 * @return void
	 */
	public function __call( $method, $params ) {
		if ( ! array_key_exists( 1, $params ) ) {
			$params[1] = false;
		}
		$var = strtolower( substr( $method, 4 ) );

		if ( strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( strncasecmp( $method, 'has', 3 ) === 0 ) {
			return ! empty( $this->$var );
		}

		if ( strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = current( $params );
		}

		if ( strncasecmp( $method, 'add', 3 ) === 0 ) {
			if ( ! $this->$var ) {
				call_user_func( array( $this, 'set_' . $var ), current( $params ) );
				return true;
			}

			return false;
		}
	}

	/**
	 * Generic adder.
	 *
	 * @param string $key   The object property name.
	 * @param mixed  $value The object property value.
	 *
	 * @return boolean
	 */
	public function add( $key, $value ) {
		if ( empty( $this->$key ) ) {
			call_user_func( array( $this, 'set_' . $key ), $value );
			return true;
		}

		return false;
	}

	/**
	 * Generic setter.
	 *
	 * @param string $key   The object property name.
	 * @param mixed  $value The object property value.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		call_user_func( array( $this, 'set_' . $key ), $value );
	}

	/**
	 * Setter for "updated".
	 *
	 * @param mixed $updated
	 *
	 * @return void
	 */
	public function set_updated( $updated ) {
		if ( is_null( $updated ) ) {
			return;
		}
		if ( $updated instanceof DateTimeImmutable ) {
			$this->updated = $updated;
		} else {
			$this->updated = new DateTimeImmutable( $updated );
		}
	}

	/**
	 * Setter for "content".
	 *
	 * @param string $content
	 *
	 * @return void
	 */
	public function set_content( $content ) {
		$this->content = webmention_sanitize_html( $content );
	}

	/**
	 * Setter for "summary".
	 *
	 * @param mixed $summary
	 *
	 * @return void
	 */
	public function set_summary( $summary ) {
		$this->summary = webmention_sanitize_html( $summary );
	}

	/**
	 * Setter for "published".
	 *
	 * @param mixed $published
	 *
	 * @return void
	 */
	public function set_published( $published ) {
		if ( is_null( $published ) ) {
			return;
		}
		if ( $published instanceof DateTimeImmutable ) {
			$this->published = $published;
		} else {
			$this->published = new DateTimeImmutable( $published );
		}
	}

	/**
	 * Setter for "author".
	 *
	 * @param mixed $author
	 *
	 * @return void
	 */
	public function set_author( $author ) {
		if ( is_string( $author ) ) {
			if ( wp_http_validate_url( $author ) ) {
				$this->author = array(
					'type' => 'card',
					'url'  => $author,
				);
			} else {
				$this->author = array(
					'type' => 'card',
					'name' => $author,
				);
			}
		} else {
			$this->author = $author;
		}
	}

	/**
	 * Getter for "author".
	 *
	 * @param string $propery The author property to return
	 *
	 * @return array|string
	 */
	public function get_author( $property = null ) {
		if ( ! $property ) {
			if ( $this->author ) {
				return $this->author;
			}

			return array(
				'type' => 'card',
				'name' => 'anonymous',
			);
		}

		if ( isset( $this->author[ $property ] ) ) {
			return $this->author[ $property ];
		} elseif ( 'name' === $property ) {
			return 'anonymous';
		} else {
			return '';
		}
	}

	/**
	 * Getter for "content" with fallback to "summary".
	 *
	 * @return string
	 */
	public function get_content() {
		$text_len = $this->str_length( $this->content );

		if ( ( 0 === $text_len ) ) {
			if ( $this->summary ) {
				return $this->summary;
			} elseif ( $this->name ) {
				return $this->name;
			}
		}

		// If this is set to a comment type or if if the content if less than maximum, always return.
		if ( 'comment' === $this->response_type || $text_len <= MAX_INLINE_MENTION_LENGTH ) {
			return $this->content;
		}

		// If there is a summary that isn't empty, return that, if not the name.
		if ( $this->summary ) {
			return $this->summary;
		} elseif ( $this->name ) {
			return $this->name;
		}

		return '';
	}

	/**
	 * Getter for response type with fallback to "mention".
	 *
	 * @return string
	 */
	public function get_response_type() {
		$response_type = $this->response_type ? $this->response_type : 'mention';
		// Reclassify short mentions as comments
		if ( 'mention' === $response_type ) {
			$text     = $this->get_content();
			$text_len = $this->str_length( $text );
			if ( ( 0 < $text_len ) && ( $text_len <= MAX_INLINE_MENTION_LENGTH ) ) {
				return 'comment';
			}
		}
		return $response_type;
	}

	/**
	 * String length function
	 * @return int
	 */
	public function str_length( $text ) {
		if ( ! is_string( $text ) ) {
			return 0;
		}
		return mb_strlen( wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES ) ) );
	}


	/**
	 * Getter for "published".
	 *
	 * @param DateTimeZone|null $timezone Optional Timezone Override.
	 * @return DateTimeImmutable
	 */
	public function get_published( $timezone = null ) {
		if ( ! $this->published && ! $this->published instanceof DateTimeImmutable ) {
			return new DateTimeImmutable();
		}

		if ( $timezone && $timezone instanceof DatetimeZone ) {
			return $this->published->setTimeZone( $timezone );
		}

		return $this->published;
	}

	/**
	 * Getter for "meta".
	 *
	 * @return array
	 */
	public function get_meta() {
		if ( ! is_array( $this->meta ) ) {
			return array();
		}

		return $this->meta;
	}

	/**
	 * Check if all fields are set.
	 *
	 * @return boolean
	 */
	public function is_complete() {
		$properties = get_object_vars( $this );
		return ! (bool) in_array( null, array_values( $properties ), true );
	}

	/**
	 * Check if all fields for a valid comment are available.
	 *
	 * @return boolean
	 */
	public function verify() {
		// If there is no author information then try something else.
		if ( empty( $this->get_author( 'name' ) ) ) {
			return false;
		}

		// If this is a reply it needs a summary. Summary should be generated from content if no summary.
		if ( 'comment' === $this->get_response_type() && empty( $this->get_content() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the representative entry as array.
	 *
	 * @return array
	 */
	public function to_array() {
		$array = array();
		$vars  = get_object_vars( $this );

		foreach ( $vars as $key => $value ) {
			// if value is empty, try to get it from a getter.
			if ( empty( $value ) ) {
				$value = call_user_func( array( $this, 'get_' . $key ) );
			}

			// if value is still empty, ignore it for the array and continue.
			if ( ! empty( $value ) ) {
				$array[ $key ] = $value;
			}
		}

		return array_filter( $array );
	}

	/**
	 * Returns the representative entry as JF2 data.
	 *
	 * @return string The JF2 JSON
	 */
	public function to_json() {
		return wp_json_encode( $this->to_array() );
	}

	/**
	 * Returns the representative entry as a comment array.
	 *
	 * @return array
	 */
	public function to_commentdata_array() {
		$this->meta['avatar']   = $this->get_author( 'photo' );
		$this->meta['protocol'] = 'webmention'; // Since this is the Webmention plugin it should always be a Webmention.
		$this->meta['url']      = $this->get_url(); // This is the parsed URL, which may or may not be the same as the source URL, which will be added as source_url.
		$comment                = array(
			'comment_author'       => $this->get_author( 'name' ),
			'comment_author_email' => $this->get_author( 'email' ),
			'comment_author_url'   => $this->get_author( 'url' ),
			'comment_content'      => $this->get_content(),
			'comment_date'         => $this->get_published( wp_timezone() )->format( 'Y-m-d H:i:s' ),
			'comment_date_gmt'     => $this->get_published( new DatetimeZone( 'GMT' ) )->format( 'Y-m-d H:i:s' ),
			'comment_type'         => $this->get_response_type(),
			'comment_meta'         => array_filter( $this->get_meta() ),
			'remote_source_raw'    => $this->get_raw(),
		);

		return apply_filters( 'webmention_item_commentdata_array', array_filter( $comment ), $this );
	}

	/**
	 * Returns a property from the raw data in the webmention_item.
	 *
	 * @param string $key Property key.
	 *
	 * @return mixed Return property or false if not found.
	 */
	public function get_raw( $key = null ) {
		if ( ! $key ) {
			return $this->raw;
		}

		$raw = $this->raw;

		if ( array_key_exists( $key, $raw ) ) {
			return $raw[ $key ];
		}

		return false;
	}
}
