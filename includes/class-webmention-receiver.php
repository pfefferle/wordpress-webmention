<?php
/**
 * Webmention Receiver Class
 *
 * @author Matthias Pfefferle
 */
class Webmention_Receiver {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		add_filter( 'query_vars', array( 'Webmention_Receiver', 'query_var' ) );

		// Configure the REST API route
		add_action( 'rest_api_init', array( 'Webmention_Receiver', 'register_routes' ) );
		// Filter the response to allow a webmention form if no parameters are passed
		add_filter( 'rest_pre_serve_request', array( 'Webmention_Receiver', 'serve_request' ), 11, 4 );

		add_filter( 'duplicate_comment_id', array( 'Webmention_Receiver', 'disable_wp_check_dupes' ), 20, 2 );

		// endpoint discovery
		add_action( 'wp_head', array( 'Webmention_Receiver', 'html_header' ), 99 );
		add_action( 'template_redirect', array( 'Webmention_Receiver', 'http_header' ) );
		add_filter( 'host_meta', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_user_data', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_post_data', array( 'Webmention_Receiver', 'jrd_links' ) );

		// Webmention helper
		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'webmention_verify' ), 11, 1 );
		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'check_dupes' ), 12, 1 );

		// Webmention data handler
		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'default_title_filter' ), 21, 1 );
		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'default_content_filter' ), 22, 1 );

		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'auto_approve' ), 13, 1 );

		// Allow for avatars on webmention comment types
		if ( 0 !== (int) get_option( 'webmention_avatars', 1 ) ) {
			add_filter( 'get_avatar_comment_types', array( 'Webmention_Receiver', 'get_avatar_comment_types' ), 99 );
		}

		// Threaded comments support
		add_filter( 'template_include', array( 'Webmention_Receiver', 'comment_template_include' ) );

		// Support Webmention delete
		add_action( 'webmention_data_error', array( 'Webmention_Receiver', 'delete' ) );

		self::register_meta();
	}

	/**
	 * This is more to lay out the data structure than anything else.
	 */
	public static function register_meta() {
		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Protocol Used to Receive', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'protocol', $args );

		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Target URL for the Webmention', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_target_url', $args );

		// For pingbacks the source URL is stored in the author URL. This means you cannot have an author URL that is different than the source.
		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Source URL for the Webmention', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_source_url', $args );

		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Target URL Fragment for the Webmention', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_target_fragment', $args );

		// Purpose of this is to store the original time as there is no modified time in the comment table.
		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Original Creation Time for the Webmention (GMT)', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_created_at', $args );

		// Purpose of this is to store the response code returned during verification
		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Response Code Returned During Webmention Verification', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_response_code', $args );

		// Purpose of this is to store a vouch URL
		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Webmention Vouch URL', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_vouch_url', $args );
	}

	/**
	 * adds some query vars
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function query_var( $vars ) {
		$vars[] = 'replytocom';
		return $vars;
	}

	/**
	 * Show avatars on webmentions if set
	 *
	 * @param array $types list of avatar enabled comment types
	 *
	 * @return array show avatars on webmentions
	 */
	public static function get_avatar_comment_types( $types ) {
		$types[] = 'webmention';
		return array_unique( $types );
	}

	/**
	 * Register the Route.
	 */
	public static function register_routes() {
		register_rest_route(
			'webmention/1.0',
			'/endpoint',
			array(
				array(
					'methods'  => WP_REST_Server::CREATABLE,
					'callback' => array( 'Webmention_Receiver', 'post' ),
				),
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( 'Webmention_Receiver', 'get' ),
				),
			)
		);
	}

	/**
	 * Hooks into the REST API output to output a webmention form.
	 *
	 * This is only done for the webmention endpoint.
	 *
	 * @param bool                      $served  Whether the request has already been served.
	 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request           $request Request used to generate the response.
	 * @param WP_REST_Server            $server  Server instance.
	 *
	 * @return true
	 */
	public static function serve_request( $served, $result, $request, $server ) {
		if ( '/webmention/1.0/endpoint' !== $request->get_route() ) {
			return $served;
		}

		if ( 'GET' === $request->get_method() ) {
			// If someone tries to poll the webmention endpoint return a webmention form.
			if ( ! headers_sent() ) {
				$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
			}

			$template = apply_filters( 'webmention_endpoint_form', plugin_dir_path( __FILE__ ) . '../templates/webmention-endpoint-form.php' );

			load_template( $template );

			return true;
		}

		// render nice HTML views for non API-calls
		if ( $request->get_param( 'format' ) === 'html' ) {
			// If someone tries to poll the webmention endpoint return a webmention form.
			if ( ! headers_sent() ) {
				$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
			}

			// Embed links inside the request.
			$data = $server->response_to_data( $result, false );

			require_once plugin_dir_path( __FILE__ ) . '../templates/webmention-api-message.php';
			return true;
		}

		return $served;
	}

	/**
	 * GET Callback for the webmention endpoint.
	 *
	 * Returns true. Any GET request is intercepted to return a webmention form.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return true
	 */
	public static function get( $request ) {
		return true;
	}

	/**
	 * POST Callback for the webmention endpoint.
	 *
	 * Returns the response.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 *
	 * @uses apply_filters calls "webmention_comment_data" on the comment data
	 * @uses apply_filters calls "webmention_update" on the comment data
	 * @uses apply_filters calls "webmention_success_message" on the success message
	 */
	public static function post( $request ) {
		$params = array_filter( $request->get_params() );

		if ( ! isset( $params['source'] ) ) {
			return new WP_Error( 'source_missing', esc_html__( 'Source is missing', 'webmention' ), array( 'status' => 400 ) );
		}

		$source = urldecode( $params['source'] );

		if ( ! isset( $params['target'] ) ) {
			return new WP_Error( 'target_missing', esc_html__( 'Target is missing', 'webmention' ), array( 'status' => 400 ) );
		}

		$target = urldecode( $params['target'] );

		if ( ! stristr( $target, preg_replace( '/^https?:\/\//i', '', home_url() ) ) ) {
			return new WP_Error( 'target_mismatching_domain', esc_html__( 'Target is not on this domain', 'webmention' ), array( 'status' => 400 ) );
		}

		$comment_post_id = webmention_url_to_postid( $target );

		// check if post id exists
		if ( ! $comment_post_id ) {
			return new WP_Error( 'target_not_valid', esc_html__( 'Target is not a valid post', 'webmention' ), array( 'status' => 400 ) );
		}

		if ( url_to_postid( $source ) === $comment_post_id ) {
			return new WP_Error( 'source_equals_target', esc_html__( 'Target and source cannot direct to the same resource', 'webmention' ), array( 'status' => 400 ) );
		}

		// check if pings are allowed
		if ( ! pings_open( $comment_post_id ) ) {
			return new WP_Error( 'pings_closed', esc_html__( 'Pings are disabled for this post', 'webmention' ), array( 'status' => 400 ) );
		}

		$post = get_post( $comment_post_id );
		if ( ! $post ) {
			return new WP_Error( 'target_not_valid', esc_html__( 'Target is not a valid post', 'webmention' ), array( 'status' => 400 ) );
		}
		// In the event of async processing this needs to be stored here as it might not be available
		// later.
		$comment_meta                          = array();
		$comment_author_ip                     = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
		$comment_agent                         = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$comment_date                          = current_time( 'mysql' );
		$comment_date_gmt                      = current_time( 'mysql', 1 );
		$comment_meta['webmention_created_at'] = $comment_date_gmt;
		$comment_meta['protocol']              = 'webmention';

		if ( isset( $params['vouch'] ) ) {
			// If there is a vouch pass it along
			$vouch = urldecode( $params['vouch'] );
			// Safely store a version of the data
			$comment_meta['webmention_vouch_url'] = esc_url_raw( $vouch );
		}

		// change this if your theme can't handle the Webmentions comment type
		$comment_type = WEBMENTION_COMMENT_TYPE;

		// change this if you want to auto approve your Webmentions
		$comment_approved = WEBMENTION_COMMENT_APPROVE;

		$commentdata = compact( 'comment_type', 'comment_approved', 'comment_agent', 'comment_date', 'comment_date_gmt', 'comment_meta', 'source', 'target', 'vouch' );

		$commentdata['comment_post_ID']   = $comment_post_id;
		$commentdata['comment_author_IP'] = $comment_author_ip;
		// Set Comment Author URL to Source
		$commentdata['comment_author_url'] = esc_url_raw( $commentdata['source'] );
		// Save Source to Meta to Allow Author URL to be Changed and Parsed
		$commentdata['comment_meta']['webmention_source_url'] = $commentdata['comment_author_url'];

		$fragment = wp_parse_url( $commentdata['target'], PHP_URL_FRAGMENT );
		if ( ! empty( $fragment ) ) {
			$commentdata['comment_meta']['webmention_target_fragment'] = $fragment;
		}
		$commentdata['comment_meta']['webmention_target_url'] = $commentdata['target'];

		$commentdata['comment_parent'] = '';
		// check if there is a parent comment
		$query_string = wp_parse_url( $commentdata['target'], PHP_URL_QUERY );
		if ( $query_string ) {
			$query_array = array();
			parse_str( $query_string, $query_array );
			if ( isset( $query_array['replytocom'] ) && get_comment( $query_array['replytocom'] ) ) {
				$commentdata['comment_parent'] = $query_array['replytocom'];
			}
		}

		// add empty fields
		$commentdata['comment_author_email'] = '';

		// Define WEBMENTION_PROCESS_TYPE as true if you want to define an asynchronous handler
		if ( WEBMENTION_PROCESS_TYPE_ASYNC === get_webmention_process_type() ) {
			// Schedule an action a random period of time in the next 2 minutes to handle webmentions.
			wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'webmention_process_schedule', array( $commentdata ) );

			// Return the source and target and the 202 Message
			$return = array(
				'link'    => '', // TODO add API link to check state of comment
				'source'  => $commentdata['source'],
				'target'  => $commentdata['target'],
				'code'    => 'scheduled',
				'message' => apply_filters( 'webmention_schedule_message', esc_html__( 'Webmention is scheduled', 'webmention' ) ),
			);

			return new WP_REST_Response( $return, 202 );
		}

		/**
		 * Filter Comment Data for Webmentions.
		 *
		 * All verification functions and content generation functions are added to the comment data.
		 *
		 * @param array $commentdata
		 *
		 * @return array|null|WP_Error $commentdata The Filtered Comment Array or a WP_Error object.
		 */
		$commentdata = apply_filters( 'webmention_comment_data', $commentdata );

		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			/**
			 * Fires if Error is Returned from Filter.
			 *
			 * Added to support deletion.
			 *
			 * @param array $commentdata
			 */
			do_action( 'webmention_data_error', $commentdata );

			return $commentdata;
		}

		// disable flood control
		remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// update or save webmention
		if ( empty( $commentdata['comment_ID'] ) ) {
			// save comment
			$commentdata['comment_ID'] = wp_new_comment( $commentdata, true );

			/**
			 * Fires when a webmention is created.
			 *
			 * Mirrors comment_post and pingback_post.
			 *
			 * @param int $comment_ID Comment ID.
			 * @param array $commentdata Comment Array.
			 */
			do_action( 'webmention_post', $commentdata['comment_ID'], $commentdata );
		} else {
			// update comment
			wp_update_comment( $commentdata );
			/**
			 * Fires after a webmention is updated in the database.
			 *
			 * The hook is needed as the comment_post hook uses filtered data
			 *
			 * @param int   $comment_ID The comment ID.
			 * @param array $data       Comment data.
			 */
			do_action( 'edit_webmention', $commentdata['comment_ID'], $commentdata );
		}

		if ( is_wp_error( $commentdata['comment_ID'] ) ) {
			return $commentdata['comment_ID'];
		}

		// re-add flood control
		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		// Return select data
		$return = array(
			'link'    => get_comment_link( $commentdata['comment_ID'] ),
			'source'  => $commentdata['source'],
			'target'  => $commentdata['target'],
			'code'    => 'success',
			'message' => apply_filters( 'webmention_success_message', esc_html__( 'Webmention was successful', 'webmention' ) ),
		);

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Verify a webmention and either return an error if not verified or return the array with retrieved
	 * data.
	 *
	 * @param array $data {
	 *     $comment_type
	 *     $comment_author_url
	 *     $comment_author_IP
	 *     $target
	 * }
	 *
	 * @return array|WP_Error $data Return Error Object or array with added fields {
	 *     $remote_source
	 *     $remote_source_original
	 *     $content_type
	 * }
	 *
	 * @uses apply_filters calls "http_headers_useragent" on the user agent
	 */
	public static function webmention_verify( $data ) {
		if ( ! $data || is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			return new WP_Error( 'invalid_data', esc_html__( 'Invalid data passed', 'webmention' ), array( 'status' => 500 ) );
		}

		$response = wp_safe_remote_get( $data['source'], $args );

		// check if source is accessible
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'source_not_found',
				esc_html__( 'Source URL not found', 'webmention' ),
				array(
					'status' => 400,
					'data'   => $data,
				)
			);
		}

		$response = self::head( $data['source'] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = self::fetch( $data['source'] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$remote_source_original = wp_remote_retrieve_body( $response );

		// check if source really links to target
		if ( ! strpos(
			htmlspecialchars_decode( $remote_source_original ),
			str_replace(
				array(
					'http://www.',
					'http://',
					'https://www.',
					'https://',
				),
				'',
				untrailingslashit( preg_replace( '/#.*/', '', $data['target'] ) )
			)
		) ) {
			return new WP_Error(
				'target_not_found',
				esc_html__( 'Cannot find target link', 'webmention' ),
				array(
					'status' => 400,
					'data'   => $data,
				)
			);
		}

		if ( ! function_exists( 'wp_kses_post' ) ) {
			include_once ABSPATH . 'wp-includes/kses.php';
		}

		$remote_source = wp_kses_post( $remote_source_original );
		$content_type  = self::get_content_type( $response );
		$commentdata   = compact( 'remote_source', 'remote_source_original', 'content_type' );

		return array_merge( $commentdata, $data );
	}

	/**
	 * Return Arguments for Retrieving Webmention URLs
	 *
	 * @return array
	 */
	public static function remote_arguments() {

		$wp_version = get_bloginfo( 'version' );
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 153600,
			'redirection'         => 20,
			'headers'             => array(
				'Accept' => 'text/html, text/plain',
			),
		);
		return apply_filters( 'webmention_remote_get_args', $args );
	}


	/**
	 * Do a head request to check the suitability of a URL
	 *
	 * @param string $url The URL to check.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error:array Return error or HTTP API response array.
	 */
	public static function head( $url, $safe = true ) {
		$args = self::remote_arguments();
		if ( $safe ) {
			$response = wp_safe_remote_head( $url, $args );
		} else {
			$response = wp_remote_head( $url, $args );
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$check = self::check_response_code( wp_remote_retrieve_response_code( $response ) );
		if ( is_wp_error( $check ) ) {
						return $check;
		}
		$check = self::check_content_type( wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return $response;
	}

	/**
	 * Check if response is a supported content type
	 *
	 * @param int $code Status Code.
	 *
	 * @return WP_Error|true return an error or that something is supported
	 */
	public static function check_response_code( $code ) {
		switch ( $code ) {
			case 200:
				return true;
			case 404:
				return new WP_Error(
					'resource_not_found',
					__( 'Resource not found', 'webmention' ),
					array(
						'status' => 400,
					)
				);
			case 405:
				return new WP_Error(
					'method_not_allowed',
					__( 'Method not allowed', 'webmention' ),
					array(
						'status' => 400,
						'allow'  => wp_remote_retrieve_header( $response, 'allow' ),
					)
				);
			case 410:
				return new WP_Error(
					'resource_deleted',
					__( 'Resource has been deleted', 'webmention' ),
					array(
						'status' => 400,
					)
				);
			case 452:
				return new WP_Error(
					'resource_removed',
					__( 'Resource removed for legal reasons', 'webmention' ),
					array(
						'status' => 400,
					)
				);
			default:
				return new WP_Error(
					'source_error',
					wp_remote_retrieve_response_message( $response ),
					array(
						'status' => 400,
					)
				);
		}
	}

	/**
	 * Strip charset off content type for matching purposes
	 * @param array $response HTTP_Response array
	 *
	 * @return string|false return either false or the stripped string
	 *
	 */
	public static function get_content_type( $response ) {
		$content_type = wp_remote_retrieve_header( $response, 'Content-Type' );
		// Strip any character set off the content type
		$ct = explode( ';', $content_type );
		if ( is_array( $ct ) ) {
			$content_type = array_shift( $ct );
		}
		return trim( $content_type );
	}

	/**
	 * Check if response is a supportted content type
	 *
	 * @param  string $content_type The content type
	 *
	 * @return WP_Error|true return an error or that something is supported
	 */
	public static function check_content_type( $content_type ) {
		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', $content_type ) ) {
			return new WP_Error(
				'unsupported_content_type',
				__( 'Content Type is not supported', 'webmention' ),
				array(
					'status' => 400,
				)
			);
		}
		return true;
	}

	/**
	 *  Retrieve a URL.
	 *
	 * @param string $url The URL to retrieve.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|array Either an error or the complete return object
	 */
	public static function fetch( $url, $safe = true ) {
		$args = self::remote_arguments();
		if ( $safe ) {
			$response = wp_safe_remote_get( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}
		if ( is_wp_error( $response ) ) {
				return $response;
		}
		$check = self::check_response_code( wp_remote_retrieve_response_code( $response ) );
		if ( is_wp_error( $check ) ) {
				return $check;
		}
		$check = self::check_content_type( wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return $response;
	}

	/**
	 * Disable the WordPress `check dupes` functionality
	 *
	 * @param int $dupe_id ID of the comment identified as a duplicate.
	 * @param array $commentdata Data for the comment being created.
	 *
	 * @return int
	 */
	public static function disable_wp_check_dupes( $dupe_id, $commentdata ) {
		if ( ! $dupe_id ) {
			return $dupe_id;
		}

		$comment_dupe = get_comment( $dupe_id, ARRAY_A );

		if ( $comment_dupe['comment_post_ID'] === $commentdata['comment_post_ID'] ) {
			return $dupe_id;
		}

		if (
			( isset( $commentdata['comment_type'] ) && 'webmention' === $commentdata['comment_type'] ) ||
			( isset( $commentdata['comment_meta'] ) && ! empty( $commentdata['comment_meta']['semantic_linkbacks_type'] ) )
		) {
			return 0;
		}

		return $dupe_id;
	}

	/**
	 * Check if a comment already exists
	 *
	 * @param  array $commentdata the comment, created for the webmention data
	 *
	 * @return array|null the dupe or null
	 */
	public static function check_dupes( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}
		$fragment = wp_parse_url( $commentdata['target'], PHP_URL_FRAGMENT );
		if ( ! empty( $fragment ) ) {
			// Check for the newer meta value before checking for the more traditional location
			$args = array(
				'post_id'    => $commentdata['comment_post_ID'],
				'meta_query' => array(
					array(
						'key'     => 'webmention_source_url',
						'value'   => $commentdata['comment_author_url'],
						'compare' => '=',
					),
					array(
						'key'     => 'webmention_target_fragment',
						'value'   => $fragment,
						'compare' => '=',
					),
				),
			);
		} else {
			$args = array(
				'post_id'    => $commentdata['comment_post_ID'],
				'meta_key'   => 'webmention_source_url',
				'meta_value' => $commentdata['comment_author_url'],
			);
		}

		$comments = get_comments( $args );
		// check result
		if ( ! empty( $comments ) ) {
			$comment                         = $comments[0];
			$commentdata['comment_ID']       = $comment->comment_ID;
			$commentdata['comment_approved'] = $comment->comment_approved;

			return $commentdata;
		}

		// Check in comment_author_url if the newer location is empty
		$args = array(
			'post_id'    => $commentdata['comment_post_ID'],
			'author_url' => $commentdata['comment_author_url'],
		);
		// If there is a fragment in the target URL then use this in the dupe search
		if ( ! empty( $fragment ) ) {
			$args['meta_key']   = 'webmention_target_fragment';
			$args['meta_value'] = $fragment;
		}
		$comments = get_comments( $args );
		// check result
		if ( ! empty( $comments ) ) {
			$comment                         = $comments[0];
			$commentdata['comment_ID']       = $comment->comment_ID;
			$commentdata['comment_approved'] = $comment->comment_approved;
			return $commentdata;
		}

		// check comments sent via salmon are also dupes
		// or anyone else who can't use comment_author_url as the original link,
		// but can use a _crossposting_link meta value.
		// @link https://github.com/pfefferle/wordpress-salmon/blob/master/plugin.php#L192
		$args     = array(
			'post_id'    => $commentdata['comment_post_ID'],
			'meta_key'   => '_crossposting_link',
			'meta_value' => $commentdata['comment_author_url'],
		);
		$comments = get_comments( $args );

		// check result
		if ( ! empty( $comments ) ) {
			$comment                         = $comments[0];
			$commentdata['comment_ID']       = $comment->comment_ID;
			$commentdata['comment_approved'] = $comment->comment_approved;
		}

		return $commentdata;
	}

	/**
	 * Try to make a nice title (username)
	 *
	 * @param array $commentdata the comment-data
	 *
	 * @return array the filtered comment-data
	 */
	public static function default_title_filter( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}

		$match = array();

		$meta_tags = wp_get_meta_tags( $commentdata['remote_source_original'] );

		// use meta-author
		if ( array_key_exists( 'author', $meta_tags ) ) {
			$commentdata['comment_author'] = $meta_tags['author'];
		} elseif ( array_key_exists( 'og:title', $meta_tags ) ) {
			// Use Open Graph Title if set
			$commentdata['comment_author'] = $meta_tags['og:title'];
		} elseif ( preg_match( '/<title>(.+)<\/title>/i', $commentdata['remote_source_original'], $match ) ) { // use title
			$commentdata['comment_author'] = trim( $match[1] );
		} else {
			// or host
			$host = wp_parse_url( $commentdata['comment_author_url'], PHP_URL_HOST );
			// strip leading www, if any
			$commentdata['comment_author'] = preg_replace( '/^www\./', '', $host );
		}

		return $commentdata;
	}

	/**
	 * Try to make a nice comment
	 *
	 * @param array $commentdata the comment-data
	 *
	 * @return array the filtered comment-data
	 */
	public static function default_content_filter( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}

		// get post format
		$post_id     = $commentdata['comment_post_ID'];
		$post_format = get_post_format( $post_id );

		// replace "standard" with "Article"
		if ( ! $post_format || 'standard' === $post_format ) {
			$post_format = 'Article';
		} else {
			$post_formatstrings = get_post_format_strings();
			// get the "nice" name
			$post_format = $post_formatstrings[ $post_format ];
		}

		$host = webmention_extract_domain( $commentdata['comment_author_url'] );

		// generate default text
		// translators: This post format was mentioned on this URL with this domain name
		$commentdata['comment_content'] = sprintf( __( 'This %1$s was mentioned on <a href="%2$s">%3$s</a>', 'webmention' ), $post_format, esc_url( $commentdata['comment_author_url'] ), $host );

		return $commentdata;
	}

	/**
	 * Delete comment if source returns error 410 or 452
	 *
	 * @param WP_Error $error
	 */
	public static function delete( $error ) {
		$error_codes = apply_filters(
			'webmention_supported_delete_codes',
			array(
				'resource_not_found',
				'resource_deleted',
				'resource_removed',
			)
		);

		if ( ! is_wp_error( $error ) ) {
			return;
		}

		if ( ! in_array( $error->get_error_code(), $error_codes, true ) ) {
			return;
		}

		$commentdata = $error->get_error_data();
		$commentdata = self::check_dupes( $commentdata );

		if ( isset( $commentdata['comment_ID'] ) ) {
			wp_delete_comment( $commentdata['comment_ID'] );
		}
	}

	/**
	 * Use the whitelist check function to approve a comment if the source domain is on the whitelist.
	 *
	 * @param array $commentdata
	 *
	 * @return array $commentdata
	 */
	public static function auto_approve( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}
		if ( self::is_source_whitelisted( $commentdata['source'] ) ) {
			$commentdata['comment_approved'] = 1;
		}
		return $commentdata;
	}

	/**
	 * Check the source $url to see if it is on the domain whitelist.
	 *
	 * @param array $author_url
	 *
	 * @return boolean
	 */
	public static function is_source_whitelisted( $url ) {
		$whitelist = get_webmention_approve_domains();
		$host      = webmention_extract_domain( $url );
		if ( empty( $whitelist ) ) {
			return false;
		}

		foreach ( (array) $whitelist as $domain ) {
			$domain = trim( $domain );
			if ( empty( $domain ) ) {
				continue;
			}
			if ( 0 === strcasecmp( $domain, $host ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * replace the template for all URLs with a "replytocom" query-param
	 *
	 * @param string $template the template url
	 *
	 * @return string
	 */
	public static function comment_template_include( $template ) {
		global $wp_query;

		// replace template
		if ( isset( $wp_query->query['replytocom'] ) ) {
			return apply_filters( 'webmention_comment_template', dirname( __FILE__ ) . '/../templates/webmention-comment.php' );
		}

		return $template;
	}

	/**
	 * Webmention headers to be added
	 *
	 * @return boolean
	 */
	public static function should_show_headers() {
		if ( WEBMENTION_ALWAYS_SHOW_HEADERS ) {
			return true;
		}

		return self::should_receive_mentions();
	}

	/**
	 *
	 *
	 * @return boolean
	 */
	public static function should_receive_mentions() {
		if ( is_singular() ) {
			return pings_open();
		} else {
			$post_id = webmention_url_to_postid( get_self_link() );
		}

		if ( ! $post_id ) {
			return false;
		}

		// If the post type does not support webmentions do not even check if pings_open is set
		if ( ! post_type_supports( get_post_type( $post_id ), 'webmentions' ) ) {
			return false;
		}

		if ( pings_open( $post_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The Webmention autodicovery meta-tags
	 */
	public static function html_header() {
		if ( ! self::should_show_headers() ) {
			return;
		}

		printf( '<link rel="webmention" href="%s" />' . PHP_EOL, get_webmention_endpoint() );
		// backwards compatibility with v0.1
		printf( '<link rel="http://webmention.org/" href="%s" />' . PHP_EOL, get_webmention_endpoint() );
	}

	/**
	 * The Webmention autodicovery http-header
	 */
	public static function http_header() {
		if ( ! self::should_show_headers() ) {
			return;
		}

		header( sprintf( 'Link: <%s>; rel="webmention"', get_webmention_endpoint() ), false );
		// backwards compatibility with v0.1
		header( sprintf( 'Link: <%s>; rel="http://webmention.org/"', get_webmention_endpoint() ), false );
	}

	/**
	 * Generates webfinger/host-meta links
	 */
	public static function jrd_links( $array ) {
		$array['links'][] = array(
			'rel'  => 'webmention',
			'href' => get_webmention_endpoint(),
		);
		// backwards compatibility with v0.1
		$array['links'][] = array(
			'rel'  => 'http://webmention.org/',
			'href' => get_webmention_endpoint(),
		);

		return $array;
	}
}
