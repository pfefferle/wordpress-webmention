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
	protected $author = array( 'type', 'name', 'url', 'photo' );

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
		$var = strtolower( substr( $method, 4 ) );

		if ( strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( strncasecmp( $method, 'has', 3 ) === 0 ) {
			return ! empty( $this->$var );
		}

		if ( strncasecmp( $method, 'set', 3 ) === 0 ) {
			if ( $this->$var ) {
				if ( isset( $params[1] ) && true === $params[1] ) {
					$this->$var = $params[0];
				}
			} elseif ( isset( $params[1] ) ) {
				$this->$var = $params[0];
			}
		}
	}

	public function to_commentdata() {

	}

	/**
	 * Check if all fields are set
	 *
	 * @return boolean
	 */
	public function is_complete() {

	}

	/**
	 * Returns the representative entry as JF2 data
	 *
	 * return string the JF2 JSON
	 */
	public function to_json() {
		$array = get_object_vars( $this );

		return wp_json_encode( $array );
	}
}
