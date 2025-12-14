<?php

namespace Webmention;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Webmention\Request;
use Webmention\Handler;
use Webmention\Avatar_Store;
use Webmention\Avatar;

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
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$title = esc_html__( 'Webmention', 'webmention' );
		$hook = add_management_page(
			$title,
			$title,
			'manage_options',
			'webmention-tools',
			array( static::class, 'tools_page' )
		);
		// Enqueue scripts only on tools page
		add_action( 'load-' . $hook, array( static::class, 'enqueue_tools_scripts' ) );
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

		register_rest_route(
			'webmention/1.0',
			'/avatars',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( static::class, 'get_avatars' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);

		register_rest_route(
			'webmention/1.0',
			'/avatar/replace',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( static::class, 'replace_avatar' ),
					'args'                => array(
						'author_url' => array(
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
						'host'       => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'avatar_url' => array(
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
					),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);

		register_rest_route(
			'webmention/1.0',
			'/avatar/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( static::class, 'refresh_avatar' ),
					'args'                => array(
						'author_url' => array(
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
						'host'       => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);

		register_rest_route(
			'webmention/1.0',
			'/avatar/cleanup',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( static::class, 'cleanup_orphaned_avatars' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);

		register_rest_route(
			'webmention/1.0',
			'/avatar/store-existing',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( static::class, 'store_existing_avatars' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
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
	 * Get all stored avatars.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_avatars( $request ) {
		if ( ! function_exists( 'normalize_url' ) ) {
			return new WP_Error( 'missing_function', __( 'normalize_url function is required.', 'webmention' ), array( 'status' => 500 ) );
		}

		$avatars = Avatar_Store::get_all_stored_avatars();

		if ( ! is_array( $avatars ) ) {
			return new WP_Error( 'avatar_retrieval_failed', __( 'Failed to retrieve avatars.', 'webmention' ), array( 'status' => 500 ) );
		}

		// Author URLs are already included in get_all_stored_avatars()
		return rest_ensure_response( $avatars );
	}

	/**
	 * Replace avatar for an author.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function replace_avatar( $request ) {
		$author_url = $request->get_param( 'author_url' );
		$host       = $request->get_param( 'host' );
		$avatar_url = $request->get_param( 'avatar_url' );

		$result = Avatar_Store::replace_avatar( $author_url, $host, $avatar_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'avatar_url' => $result,
			)
		);
	}

	/**
	 * Refresh avatar from author URL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function refresh_avatar( $request ) {
		$author_url = $request->get_param( 'author_url' );
		$host       = $request->get_param( 'host' );

		$result = Avatar_Store::refresh_avatar( $author_url, $host );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'avatar_url' => $result,
			)
		);
	}

	/**
	 * Store avatars for existing comments that don't have stored avatars yet.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function store_existing_avatars( $request ) {
		if ( ! function_exists( 'normalize_url' ) ) {
			return new WP_Error( 'missing_function', __( 'normalize_url function is required.', 'webmention' ), array( 'status' => 500 ) );
		}

		// Get all webmention comments - we'll check each one to see if avatar is stored locally
		$comments = get_comments(
			array(
				'type'   => 'webmention',
				'status' => 'approve',
				'number' => 100, // Process in batches
			)
		);

		if ( empty( $comments ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'No webmention comments found.', 'webmention' ),
					'processed' => 0,
				)
			);
		}

		// Filter to only comments that don't have locally stored avatars
		$upload_url = Avatar_Store::upload_directory( '', true );
		$comments_to_process = array();
		foreach ( $comments as $comment ) {
			// Skip if user ID is set
			if ( $comment->user_id ) {
				continue;
			}

			$existing_avatar = Avatar::get_avatar_meta( $comment );
			
			// Skip if avatar is already stored locally
			if ( $existing_avatar && str_contains( $existing_avatar, $upload_url ) ) {
				// Verify file actually exists
				$filepath = Avatar_Store::avatar_url_to_filepath( $existing_avatar );
				if ( $filepath && file_exists( $filepath ) ) {
					continue; // Already stored and file exists
				}
			}

			$comments_to_process[] = $comment;
		}

		if ( empty( $comments_to_process ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'All comments already have stored avatars.', 'webmention' ),
					'processed' => 0,
				)
			);
		}

		$comments = $comments_to_process;

		$processed = 0;
		$stored    = 0;
		$errors    = array();

		foreach ( $comments as $comment ) {
			$processed++;

			// Skip if user ID is set (let something else handle that)
			if ( $comment->user_id ) {
				continue;
			}

			// Get author URL first (needed for both paths)
			$author_url = normalize_url( get_comment_author_url( $comment ) );
			if ( ! $author_url ) {
				$errors[] = sprintf( __( 'Comment #%d: No author URL available', 'webmention' ), $comment->comment_ID );
				continue;
			}

			$host = webmention_extract_domain( get_url_from_webmention( $comment ) );
			if ( ! $host ) {
				$errors[] = sprintf( __( 'Comment #%d: Could not determine host', 'webmention' ), $comment->comment_ID );
				continue;
			}

			// Check if avatar already exists in meta and is stored locally
			$existing_avatar = Avatar::get_avatar_meta( $comment );
			$upload_url = Avatar_Store::upload_directory( '', true );
			
			// If avatar exists and is already stored locally, skip
			if ( $existing_avatar && str_contains( $existing_avatar, $upload_url ) ) {
				// Verify file actually exists
				$filepath = Avatar_Store::avatar_url_to_filepath( $existing_avatar );
				if ( $filepath && file_exists( $filepath ) ) {
					continue; // Already stored and file exists
				}
			}

			// If avatar exists in meta but is external (not stored), try to store it
			if ( $existing_avatar && ! str_contains( $existing_avatar, $upload_url ) ) {
				// Store the external avatar URL locally
				$stored_url = Avatar_Store::sideload_avatar( $existing_avatar, $host, $author_url );
				if ( $stored_url ) {
					update_comment_meta( $comment->comment_ID, 'avatar', $stored_url );
					$stored++;
					continue;
				}
			}

			// If no avatar in meta or storing external URL failed, try to fetch from author URL
			$refresh_result = Avatar_Store::refresh_avatar( $author_url, $host );
			if ( ! is_wp_error( $refresh_result ) ) {
				$stored++;
			} else {
				$errors[] = sprintf( __( 'Comment #%d: %s', 'webmention' ), $comment->comment_ID, $refresh_result->get_error_message() );
			}
		}

		$response_data = array(
			'success'   => true,
			'processed' => $processed,
			'stored'    => $stored,
		);

		if ( $stored > 0 ) {
			$response_data['message'] = sprintf( __( 'Stored avatars for %d comment(s).', 'webmention' ), $stored );
		} else {
			$response_data['message'] = __( 'No avatars were stored. They may already be stored or unavailable.', 'webmention' );
		}

		if ( ! empty( $errors ) ) {
			$response_data['errors'] = $errors;
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * Clean up orphaned avatars.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function cleanup_orphaned_avatars( $request ) {
		$deleted = Avatar_Store::cleanup_orphaned_avatars();

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => sprintf( __( 'Cleaned up %d orphaned avatar file(s).', 'webmention' ), $deleted ),
				'deleted' => $deleted,
			)
		);
	}

	/**
	 * Enqueue scripts for tools page
	 */
	public static function enqueue_tools_scripts() {
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
	}

	/**
	 * Load tools page
	 */
	public static function tools_page() {
		self::enqueue_tools_scripts();
		load_template( WEBMENTION_PLUGIN_DIR . '/templates/webmention-tools.php' );
	}
}
