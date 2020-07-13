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
	 * @var string
	 */
	protected $_site_name;

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
	 * @var string
	 */
	protected $_response_type;

	/**
	 * The raw document as JSON, HTML, XML, ...
	 *
	 * @var mixed
	 */
	protected $_raw;

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
			$value     = $params[0] ? $params[0] : null;
			$overwrite = $params[1] ? $params[1] : false;

			$this->set( $var, $value, $overwrite );
		}
	}

	public function set( $key, $value, $overwrite = false ) {
		if ( in_array( $key, array( 'raw', 'site_name', 'response_type' ), true ) ) {
			$key = '_' . $key;
		}

		if ( $this->$key ) {
			if ( isset( $overwrite ) && false === $overwrite ) {
				return;
			}
		}

		if ( in_array( $key, array( 'updated', 'published' ), true ) && ! $value instanceof DateTimeImmutable ) {
			$value = new DateTimeImmutable( $value );
		}

		$this->$key = $value;
	}

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