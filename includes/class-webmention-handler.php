<?php
/**
 * Base class for webmention parsing and post processing.
*/
abstract class Webmention_Handler {

	/**
	 * Parsed Data.
	 *
	 * @var array
	 */
	protected $data = array();

	public function __get( $name ) {
		return array_key_exists( $name, $data ) ? $data[ $name ] : null;
	}

	public function __set( $name, $value ) {
		$this->data[ $name ] = $value;
	}

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @return WP_Error|true Return error or true if successful.
	 */
	abstract public function parse( $request );

}
