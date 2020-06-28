<?php
/**
 * Class for handling webmention handlers
*/
class Webmention_Handler {

	protected $handlers = array();

	/**
	 * Must be instantiated with at least one handler.
	 *
	 * @param array|Webmention_Handler_Base $handler
	 *
	 */
	public function __construct( $handler ) {
		if ( is_array( $handler ) ) {
			$this->handlers = $handler;
		}
		$this->handlers = array( $handler );
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
	 *
	 */
	public function parse( $request ) {
		$item = null;
		foreach ( $handlers as $handler ) {
			$handler->parse( $request, $item );
			$item = $handler->get_webmention_item();
			if ( $item->is_complete() ) {
				break;
			}
		}
		return $item;
	}

}


