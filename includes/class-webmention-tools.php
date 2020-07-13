<?php
/**
 * Webmention Tools Class
 *
 * @author David Shanske
 */
class Webmention_Tools {
	/**
	 * Register Webmention tools settings.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$title      = esc_html__( 'Webmention', 'webmention' );
		$tools_page = add_management_page(
			$title,
			$title,
			'manage_options',
			'webmention',
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
						'url' => array(
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
					),
					'permission_callback' => function () {
						return current_user_can( 'read' );
					},
				),
			)
		);
	}

	public static function read( $request ) {
		$url              = $request->get_param( 'url' );
		$request = new Webmention_Request();
		$return  = $request->fetch( $url );

		if ( is_wp_error( $return ) ) {
			return $return;
		}
		$json = array();
		$meta = new Webmention_Handler_Meta();
		$return = $meta->parse( $request );
		if ( is_wp_error( $return ) ) {
			return $return;
		}
		$item = $meta->get_webmention_item();
		$json['meta'] = $item->to_array();


		$jsonld = new Webmention_Handler_JSONLD();
		$return = $jsonld->parse( $request );
		if ( is_wp_error( $return ) ) {
			return $return;
		}
		$item = $jsonld->get_webmention_item();
		$json['jsonld'] = $item->to_array();

		$mf2 = new Webmention_Handler_MF2();
		$return = $mf2->parse( $request );
		if ( is_wp_error( $return ) ) {
			return $return;
		}
		$item = $mf2->get_webmention_item();
		$json['mf2'] = $item->to_array();

		return $json;
	}

	/**
	 * Load tools page
	 */
	public static function tools_page() {
		load_template( dirname( __FILE__ ) . '/../templates/webmention-tools.php' );
	}

}
