<?php

namespace Webmention;

use Webmention\Response;
use Webmention\Entity\Item;
use Webmention\Handler\WP;
use Webmention\Handler\Mf2;
use Webmention\Handler\Meta;
use Webmention\Handler\Jsonld;

/**
 * Class for handling Webmention handlers
*/
class Handler {

	protected $handlers = array();

	/**
	 * Must be instantiated with at least one handler.
	 */
	public function __construct() {
		// MF2 Handler  Class
		require_once WEBMENTION_PLUGIN_DIR . '/includes/Handler/class-mf2.php';
		$this->handlers[] = new MF2();

		// WordPress Handler Class
		require_once WEBMENTION_PLUGIN_DIR . '/includes/Handler/class-wp.php';
		$this->handlers[] = new WP();

		// Meta Handler Class
		require_once WEBMENTION_PLUGIN_DIR . '/includes/Handler/class-meta.php';
		$this->handlers[] = new Meta();

		// JSON-LD Handler  Class
		require_once WEBMENTION_PLUGIN_DIR . '/includes/Handler/class-jsonld.php';
		$this->handlers[] = new Jsonld();
	}

	/**
	 * Appends a Handler to the List.
	 *
	 * @param Webmention\Handler\Base $handler
	 */
	public function push( $handler ) {
		array_push( $this->handlers, $handler );
	}

	/**
	 * Insert a Handler at the front of the list
	 *
	 * @param Webmention\Handler\Base $handler
	 */
	public function unshift( $handler ) {
		array_unshift( $this->handlers[], $handler );
	}


	/**
	 * Iterate through a list of handlers and return an item.
	 *
	 * @param Webmention\Response $response Response Object.
	 * @param string              $target_url The target URL
	 *
	 * @return Webmention\Entity\Item
	 */
	public function parse( Response $response, $target_url ) {
		return $this->parse_aggregated( $response, $target_url );
	}

	/**
	 * Iterate through a list of handlers and return an aggregated item.
	 *
	 * @param Webmention\Response $response Response Object.
	 * @param string              $target_url The target URL
	 *
	 * @return Webmention\Entity\Item
	 */
	public function parse_aggregated( Response $response, $target_url ) {
		$item = new Item();

		foreach ( $this->handlers as $handler ) {
			$handler->set_webmention_item( $item );
			$return = $handler->parse( $response, $target_url );

			if ( is_wp_error( $return ) ) {
				continue;
			}

			if ( ! is_wp_error( $handler->get_webmention_item() ) ) {
				$item = $handler->get_webmention_item();
			}

			if ( $item->is_complete() ) {
				break;
			}
		}
		return $item;
	}

	/**
	 * Iterate through a list of handlers and return an array of items.
	 *
	 * @param Webmention\Response $response Respone Object.
	 * @param string              $target_url The target URL
	 *
	 * @return Webmention\Entity\Item
	 */
	public function parse_grouped( Response $response, $target_url ) {
		$result = array();

		foreach ( $this->handlers as $handler ) {
			$return = $handler->parse( $response, $target_url );

			if ( is_wp_error( $return ) ) {
				continue;
			}

			$item = $handler->get_webmention_item();

			if ( ! is_wp_error( $item ) ) {
				$result[ $handler->get_slug() ] = $item->to_array();
			}
		}

		return $result;
	}
}
