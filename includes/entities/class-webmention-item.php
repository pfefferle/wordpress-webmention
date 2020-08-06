<?php
/**
 * Represents a Remote Webmention
 */
class Webmention_Item {

	/**
	 * The entity type.
	 *
	 * @var string
	 */
	protected $type;

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

	// mabe also an entity
	protected $author = array();

	/**
	 * Array of photos
	 *
	 * @var array
	 */
	protected $photo = array();

	/**
	 * The response name
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
	 * The response content
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
	 * The response type
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
	 * Magic function for getter/setter
	 *
	 * @param string $method
	 * @param array  $params
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
	}

	public function set( $key, $value ) {
		call_user_func( array( $this, 'set_' . $key ), $value );
	}

	/**
	 * Setter for $this->updated
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
	 * Setter for $this->content
	 *
	 * @param string $content
	 *
	 * @return void
	 */
	public function set_content( $content ) {
		$this->content = webmention_sanitize_html( $content );
	}

	/**
	 * Setter for $this->summary
	 *
	 * @param mixed $summary
	 *
	 * @return void
	 */
	public function set_summary( $summary ) {
		$this->summary = webmention_sanitize_html( $summary );
	}

	/**
	 * Setter for $this->published
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
	 * Setter for $this->author
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
	 * Getter for content with fallback to summary
	 *
	 * @return string
	 */
	public function get_content() {
		return $this->content ? $this->content : $this->summary;
	}

	/**
	 * Check if all fields are set
	 *
	 * @return boolean
	 */
	public function is_complete() {
		$vars = $this->to_array();

		// If there is no author information then try something else.
		if ( ! array_key_exists( 'author', $vars ) ) {
			return false;
		}

		// If this is a reply it needs a summary. Summary should be generated from content if no summary.
		if ( 'reply' === $type && ! array_key_exists( 'summary', $vars ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the representative entry as array
	 *
	 * return array;
	 */
	public function to_array() {
		$array = get_object_vars( $this );

		return array_filter( $array );
	}

	/**
	 * Returns the representative entry as JF2 data
	 *
	 * return string the JF2 JSON
	 */
	public function to_json() {
		return wp_json_encode( $this->to_array() );
	}

	/**
	 * Returns the representative entry as a comment array.
	 *
	 * return array;
	 */
	public function to_commentdata_array() {
		$published_gmt = $this->published->setTimeZone( 'GMT' );
		$meta          = array(
			'avatar'   => ifset( $this->author['photo'] ),
			'protocol' => 'webmention', // Since this is the webmention plugin it should always be a webmention.
			'url'      => $this->url, // This is the parsed URL, which may or may not be the same as the source URL, which will be added as source_url.
		);

		$comment = array(
			'comment_author'       => ifset( $this->author['name'] ),
			'comment_author_email' => ifset( $this->author['email'] ),
			'comment_author_url'   => ifset( $this->author['url'] ),
			'comment_content'      => $this->get_content(),
			'comment_date'         => $this->published->format( 'Y-m-d H:i:s' ),
			'comment_date_gmt'     => $published_gmt->format( 'Y-m-d H:i:s' ),
			'comment_type'         => $this->response_type,
			'comment_meta'         => array_filter( $meta ),
		);

		return apply_filters( 'webmention_item_commentdata_array', array_filter( $comment ), $this->get_raw() );
	}

	/**
	 * Returns a property from the raw data in the webmention_item.
	 *
	 * @param $string $key Property Key.
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
