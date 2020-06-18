<?php
/**
 * Base class for webmention parsing and post processing.
*/
abstract class Webmention_Handler {

	/**
	 * Parsed Data as Webmention_Entity.
	 *
	 * @var Webmention_Entity
	 */
	protected $webmention_entity;

	public function get_webmention_entity() {
		return $this->webmention_entity;
	}

	public function set_webmention_entity( $webmention_entity ) {
		if ( $webmention_entity instanceof Webmention_Entity ) {
			return WP_Error( 'wrong_data_format', __( '$webmention_entity is not an instance of Webmention_Entity', 'webmention' ), $webmention_entity );
		}

		$this->webmention_entity = $webmention_entity;
		return true;
	}

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @return WP_Error|true Return error or true if successful.
	 */
	abstract public function parse( $request );

}
