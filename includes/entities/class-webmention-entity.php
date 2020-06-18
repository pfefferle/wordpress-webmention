<?php
/**
 * Represents a Remote Webmention
 */
class Webmention_Entity {

	protected $type;

	protected $published;

	protected $url;

	// mabe also an entity
	protected $author = array( 'type', 'name', 'url', 'photo' );

	protected $category = array();

	protected $photo;

	protected $content;

	protected $_respnse_type;

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

		if ( strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
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
}