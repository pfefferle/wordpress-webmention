<?php

namespace Webmention;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_HTTP_ResponseInterface;
use Webmention\Request;
use Webmention\Response;
use Webmention\Handler;

/**
 * Webmention Receiver Class
 *
 * @author Matthias Pfefferle
 */
class Receiver {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		// Configure the REST API route
		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );
		// Filter the response to allow a Webmention form if no parameters are passed
		add_filter( 'rest_pre_serve_request', array( static::class, 'serve_request' ), 11, 4 );

		add_filter( 'duplicate_comment_id', array( static::class, 'disable_wp_check_dupes' ), 20, 2 );

		// Webmention helper
		add_filter( 'webmention_comment_data', array( static::class, 'webmention_verify' ), 11, 1 );
		add_filter( 'webmention_comment_data', array( static::class, 'check_dupes' ), 12, 1 );

		// Webmention data handler
		add_filter( 'webmention_comment_data', array( static::class, 'default_commentdata' ), 21, 1 );

		add_filter( 'pre_comment_approved', array( static::class, 'auto_approve' ), 11, 2 );

		// Support Webmention delete
		add_action( 'webmention_data_error', array( static::class, 'delete' ) );

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
			'description'  => esc_html__( 'Last Modified Time for the Webmention (GMT)', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_last_modified', $args );

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

		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Canonical URL for the Webmention', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'url', $args );

		$args = array(
			'type'         => 'string',
			'description'  => esc_html__( 'Avatar URL', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'avatar', $args );
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
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( static::class, 'post' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( static::class, 'get' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Hooks into the REST API output to output a Webmention form.
	 *
	 * This is only done for the Webmention endpoint.
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
			// If someone tries to poll the Webmention endpoint return a Webmention form.
			if ( ! headers_sent() ) {
				$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
			}

			$template = apply_filters( 'webmention_endpoint_form', plugin_dir_path( __FILE__ ) . '../templates/webmention-endpoint-form.php' );

			load_template( $template );

			return true;
		}

		// render nice HTML views for non API-calls
		if ( $request->get_param( 'format' ) === 'html' ) {
			// If someone tries to poll the Webmention endpoint return a Webmention form.
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
	 * GET Callback for the Webmention endpoint.
	 *
	 * Returns true. Any GET request is intercepted to return a Webmention form.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return true
	 */
	public static function get( $request ) {
		return true;
	}

	/**
	 * POST Callback for the Webmention endpoint.
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
		$source = $request->get_param( 'source' );
		$target = $request->get_param( 'target' );
		$vouch  = $request->get_param( 'vouch' );

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

		// check if webmentions are allowed
		if ( ! webmentions_open( $comment_post_id ) ) {
			return new WP_Error( 'webmentions_closed', esc_html__( 'Webmentions are disabled for this post', 'webmention' ), array( 'status' => 400 ) );
		}

		$post = get_post( $comment_post_id );
		if ( ! $post ) {
			return new WP_Error( 'target_not_valid', esc_html__( 'Target is not a valid post', 'webmention' ), array( 'status' => 400 ) );
		}

		// In the event of async processing this needs to be stored here as it might not be available
		// later.
		$comment_meta             = array();
		$comment_author_ip        = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
		$comment_agent            = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$comment_date             = current_time( 'mysql' );
		$comment_date_gmt         = current_time( 'mysql', 1 );
		$comment_meta['protocol'] = 'webmention';

		if ( $vouch ) {
			// If there is a vouch pass it along
			$vouch = urldecode( $vouch );
			// Safely store a version of the data
			$comment_meta['webmention_vouch_url'] = esc_url_raw( $vouch );
		}

		// change this if your theme can't handle the Webmentions comment type
		$comment_type = WEBMENTION_COMMENT_TYPE;

		$commentdata = compact( 'comment_type', 'comment_agent', 'comment_date', 'comment_date_gmt', 'comment_meta', 'source', 'target', 'vouch' );

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
		// Set last modified time
		$commentdata['comment_meta']['webmention_last_modified'] = $comment_date_gmt;

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
			// Schedule an action a random period of time in the next 2 minutes to handle Webmentions.
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
			if ( ! is_array( $commentdata['comment_meta'] ) ) {
				$commentdata['comment_meta'] = array();
			}

			// save comment but remove content filtering because we filter our own content
			remove_filter( 'pre_comment_content', 'wp_filter_post_kses' );
			remove_filter( 'pre_comment_content', 'wp_filter_kses' );

			$commentdata['comment_ID'] = wp_new_comment( $commentdata, true );

			// restore filter after add
			if ( current_user_can( 'unfiltered_html' ) ) {
				add_filter( 'pre_comment_content', 'wp_filter_post_kses' );
			} else {
				add_filter( 'pre_comment_content', 'wp_filter_kses' );
			}

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

	public static function request_parameters() {
		$params = array();

		$params['source'] = array(
			'required'          => true,
			'type'              => 'string',
			'validate_callback' => 'wp_http_validate_url',
			'sanitize_callback' => 'esc_url',
		);

		$params['target'] = array(
			'required'          => true,
			'type'              => 'string',
			'validate_callback' => 'wp_http_validate_url',
			'sanitize_callback' => 'esc_url',
		);

		return $params;
	}

	/**
	 * Verify a Webmention and either return an error if not verified or return the array with retrieved
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

		$response = Request::get( $data['source'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// check if source really links to target
		if ( ! strpos(
			htmlspecialchars_decode( $response->get_body() ),
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

		$commentdata = array(
			'content_type'           => $response->get_content_type(),
			'remote_source_original' => $response->get_body(),
			'remote_source'          => webmention_sanitize_html( $response->get_body() ),
		);

		return array_merge( $commentdata, $data );
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

		if ( ! empty( $commentdata['comment_meta']['protocol'] ) && 'webmention' === $commentdata['comment_meta']['protocol'] ) {
			return 0;
		}

		return $dupe_id;
	}

	/**
	 * Check if a comment already exists
	 *
	 * @param  array $commentdata the comment, created for the Webmention data
	 *
	 * @return array|null the dupe or null
	 */
	public static function check_dupes( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}

		// This check should never be tripped as all current webmentions should have the source url property.
		if ( ! array_key_exists( 'comment_meta', $commentdata ) && ! array_key_exists( 'webmention_source_url', $commentdata['comment_meta'] ) ) {
			return $commentdata;
		}

		$fragment = wp_parse_url( $commentdata['target'], PHP_URL_FRAGMENT );
		// Meta Query for searching for the URL
		$meta_query = array(
			'relation' => 'OR',
			// This would catch incoming webmentions with the same source URL
			array(
				'key'     => 'webmention_source_url',
				'value'   => $commentdata['comment_meta']['webmention_source_url'],
				'compare' => '=',
			),

			// This should catch incoming webmentions with the same canonical URL for Bridgy
			array(
				'key'     => 'url',
				'value'   => $commentdata['comment_meta']['webmention_source_url'],
				'compare' => '=',
			),
			// check comments sent via salmon are also dupes
			// or anyone else who can't use comment_author_url as the original link,
			// but can use a _crossposting_link meta value.
			// @link https://github.com/pfefferle/wordpress-salmon
			array(
				'key'     => '_crossposting_link',
				'value'   => $commentdata['comment_meta']['webmention_source_url'],
				'compare' => '=',
			),

			// This would catch incoming activitypub matches, which uses source_url
			array(
				'key'     => 'source_url',
				'value'   => $commentdata['comment_meta']['webmention_source_url'],
				'compare' => '=',
			),
		);
		$args = array(
			'post_id'    => $commentdata['comment_post_ID'],
			'meta_query' => array( $meta_query ),
			'status'     => 'any',
		);

		if ( ! empty( $fragment ) ) {
			// Ensure that if there is a fragment it is matched
			$args['meta_query'][] = array(
				'key'     => 'webmention_target_fragment',
				'value'   => $fragment,
				'compare' => '=',
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

		return $commentdata;
	}

	/**
	 * Try to make a nice comment
	 *
	 * @param array $commentdata the comment-data
	 *
	 * @return array the filtered comment-data
	 */
	public static function default_commentdata( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}

		$response = Request::get( $commentdata['source'] );

		$handler = new Handler();
		$item    = $handler->parse_aggregated( $response, $commentdata['target'] );

		if ( ! $item->verify() ) {
			return new WP_Error( 'incomplete_item', __( 'Not enough data available', 'webmention' ) );
		}

		$commentdata_array = $item->to_commentdata_array();

		return array_replace_recursive( $commentdata, $commentdata_array );
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
	 * Use the approved check function to approve a comment if the source domain is on the approve list.
	 *
	 * @param int|string/WP_Error $approved The approval status. Accepts 1, 0, spam, or WP_Error.
	 * @param array $commentdata
	 *
	 * @return array $commentdata
	 */
	public static function auto_approve( $approved, $commentdata ) {
		if ( is_wp_error( $approved ) ) {
			return $approved;
		}
		// Exit if there is no source to investigate
		if ( ! array_key_exists( 'source', $commentdata ) ) {
			return $approved;
		}
		if ( array_key_exists( 'comment_meta', $commentdata ) ) {
			if ( ! array_key_exists( 'protocol', $commentdata['comment_meta'] ) || 'webmention' !== $commentdata['comment_meta']['protocol'] ) {
				return $approved;
			}
		}
		// If this is set auto approve all Webmentions
		if ( 1 === WEBMENTION_COMMENT_APPROVE ) {
			return 1;
		}

		return self::is_source_allowed( $commentdata['source'] ) ? 1 : 0;
	}

	/**
	 * Check the source $url to see if it is on the domain approve list.
	 *
	 * @param array $author_url
	 *
	 * @return boolean
	 */
	public static function is_source_allowed( $url ) {
		$approvelist = get_webmention_approve_domains();
		$host        = webmention_extract_domain( $url );
		if ( empty( $approvelist ) ) {
			return false;
		}

		foreach ( (array) $approvelist as $domain ) {
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
}
