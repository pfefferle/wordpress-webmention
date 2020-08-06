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
	 * Generate Summary from Content.
	 *
	 * @param string $content Content.
	 * @return string Summary.
	 */
	public function generate_summary( $content ) {
		$content = wp_strip_all_tags( $content );
		return wp_trim_words( $content, 25 );
	}

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @param string $target_url The target URL
	 *
	 * @return WP_Error|true Return error or true if successful.
	 */
	abstract public function parse( Webmention_Request $request, $target_url );
}
