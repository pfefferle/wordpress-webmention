<?php
/**
 * Class for handling webmention handlers
*/
class Webmention_Handler {

	protected $handlers = array();

	/**
	 * Must be instantiated with at least one handler.
	 */
	public function __construct() {
		// Meta Handler Class
		require_once dirname( __FILE__ ) . '/class-webmention-handler-meta.php';
		$this->handlers[] = new Webmention_Handler_Meta();

		// MF2 Handler  Class
		require_once dirname( __FILE__ ) . '/class-webmention-handler-mf2.php';
		$this->handlers[] = new Webmention_Handler_Mf2();

		// JSON-LD Handler  Class
		require_once dirname( __FILE__ ) . '/class-webmention-handler-jsonld.php';
		$this->handlers[] = new Webmention_Handler_Jsonld();
	}

	/**
	 * Appends a Handler to the List.
	 *
	 * @param Webmention_Handler_Base $handler
	 *
	 */
	public function push( $handler ) {
		array_push( $this->handlers, $handler );
	}

	/**
	 * Insert a Handler at the front of the list
	 *
	 * @param Webmention_Handler_Base $handler
	 *
	 */
	public function unshift( $handler ) {
		array_unshift( $this->handlers[], $handler );
	}


	/**
	 * Iterate through a list of handlers and return an item.
	 *
	 * @return Webmention_Handler_Item
	 */
	public function parse( $request ) {
		foreach ( $this->handlers as $handler ) {
			$handler->parse( $request );
			$item = $handler->get_webmention_item();
			if ( $item->is_complete() ) {
				break;
			}
		}
		return $item;
	}

	public function parse_aggregated( $request ) {
		$item = new Webmention_Item();

		foreach ( $this->handlers as $handler ) {
			$handler->set_webmention_item( $item );
			$handler->parse( $request );
			$item = $handler->get_webmention_item();

			if ( $item->is_complete() ) {
				break;
			}
		}
		return $item;
	}

	/**
	 * Iterate through a list of handlers and return an item.
	 *
	 * @return Webmention_Handler_Item
	 */
	public function parse_grouped( $request ) {
		$result = array();

		foreach ( $this->handlers as $handler ) {
			$handler->parse( $request );
			$item = $handler->get_webmention_item();

			if ( ! is_wp_error( $item ) ) {
				$result[ $handler->get_slug() ] = $item->to_array();
			}
		}

		return $result;
	}
}


