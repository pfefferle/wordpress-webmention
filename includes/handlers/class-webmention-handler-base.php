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
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Constructor.
	 *
	 * @param Webmention_Item $webmention_item The Webmention item.
	 */
	public function __construct( $webmention_item = null ) {
		if ( $webmention_item instanceof Webmention_Item ) {
			$this->webmention_item = $webmention_item;
		} else {
			$this->webmention_item = new Webmention_Item();
		}
	}

	/**
	 * Get Webmention_Item
	 *
	 * @return Webmention_Item
	 */
	public function get_webmention_item() {
		return $this->webmention_item;
	}

	/**
	 * Get Name of Slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Set Webmention_Item
	 *
	 * @param Webmention_Item $webmention_item the Webmention Item
	 * @return WP_Error|true
	 */
	public function set_webmention_item( $webmention_item ) {
		if ( ! ( $webmention_item instanceof Webmention_Item ) ) {
			return new WP_Error( 'wrong_data_format', __( '$webmention_item is not an instance of Webmention_Item', 'webmention' ), $webmention_item );
		}

		$this->webmention_item = $webmention_item;
		return true;
	}

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 *
	 * @return WP_Error|true Return error or true if successful.
	 */
	abstract public function parse( Webmention_Request $request );

	/**
	 * Returns a property from the raw data in the webmention_item.
	 * Each handler will implement this to transform raw data appropriately.
	 *
	 * @param $string $key Property Key. If not provided all properties returned.
	 *
	 * @return mixed Return property or false if not found.
	 */
	abstract public function get_raw( $key = null );
}
