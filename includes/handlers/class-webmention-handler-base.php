<?php
/**
 * Base class for webmention parsing and post processing.
 */
abstract class Webmention_Handler_Base {

	/**
	 * Parsed Data as Webmention_Item.
	 *
	 * @var Webmention_Item
	 */
	protected $webmention_item;

	/**
	 * Get Webmention_Item
	 *
	 * @return Webmention_Item
	 */
	public function get_webmention_item() {
		return $this->webmention_item;
	}

	/**
	 * Set Webmention_Item
	 *
	 * @param Webmention_Item $webmention_item the Webmention Item
	 * @return WP_Error|true
	 */
	public function set_webmention_item( $webmention_item ) {
		if ( $webmention_item instanceof Webmention_Item ) {
			return WP_Error( 'wrong_data_format', __( '$webmention_item is not an instance of Webmention_Item', 'webmention' ), $webmention_item );
		}

		$this->webmention_item = $webmention_item;
		return true;
	}

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @param Webmention_Item $item A Parsed Item. If null, a new one will be created.
	 * @return WP_Error|true Return error or true if successful.
	 */
	abstract public function parse( $request, $item = null );

}
