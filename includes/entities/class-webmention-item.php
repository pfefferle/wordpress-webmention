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
	 * Array of category strings
	 *
	 * @var array
	 */
	protected $category = array();

	/**
	 * Array of photos
	 *
	 * @var array
	 */
	protected $photo = array();


	/**
	 * Location array.
	 *
	 * @var array
	 */
	protected $location = array();

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
	 * Any syndication links.
	 *
	 * @var array
	 */
	protected $syndication;

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
	 * Transform to commentdata
	 *
	 * @return WP_Comment
	 */
	public function to_commentdata() {

	}

	/**
	 * Check if all fields are set
	 *
	 * @return boolean
	 */
	public function is_complete() {
		// returns false for now
		// @todo implement
		return false;
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
}
