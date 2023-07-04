<?php

namespace Webmention;

use WP_REST_Server;
use Webmention\Request;
use Webmention\Handler;

/**
 * Webmention Tools Class
 *
 * @author David Shanske
 */
class Tools {
	/**
	 * Register Webmention tools settings.
	 */
	public static function init() {
		add_action( 'admin_menu', array( static::class, 'admin_menu' ) );
		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$title = esc_html__( 'Webmention', 'webmention' );
		add_management_page(
			$title,
			$title,
			'manage_options',
			'webmention-tools',
			array( static::class, 'tools_page' )
		);
	}

	/**
	 * Register a route to query the parser and return the results.
	 */
	public static function register_routes() {
		register_rest_route(
			'webmention/1.0',
			'/parse',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( static::class, 'read' ),
					'args'                => array(
						'source' => array(
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
						'target' => array(
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
						'mode'   => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						),
					),
					'permission_callback' => function () {
						return current_user_can( 'read' );
					},
				),
			)
		);
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $request
	 * @return void
	 */
	public static function read( $request ) {
		$source = $request->get_param( 'source' );
		$target = $request->get_param( 'target' );
		$mode   = $request->get_param( 'mode' );

		$response = Request::get( $source, false );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$handler = new Handler();

		if ( 'aggregated' === $mode ) {
			$response = $handler->parse_aggregated( $response, $target );
			$response = $response->to_array();
		} elseif ( 'grouped' === $mode ) {
			$response = $handler->parse_grouped( $response, $target );
		} else {
			$response = $handler->parse( $response, $target );
			$response = $response->to_array();
		}

		return $response;
	}

	/**
	 * Load tools page
	 */
	public static function tools_page() {
		load_template( WEBMENTION_PLUGIN_DIR . '/templates/webmention-tools.php' );
	}
}
