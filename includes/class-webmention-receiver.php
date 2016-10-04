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

		// Configure the REST API route
		add_action( 'rest_api_init', array( 'Webmention_Receiver', 'register_routes' ) );
		// Filter the response to allow plaintext
		add_filter( 'rest_pre_serve_request', array( 'Webmention_Receiver', 'serve_request' ), 9, 4 );

		add_action( 'admin_comment_types_dropdown', array( 'Webmention_Receiver', 'comment_types_dropdown' ) );

		// endpoint discovery
		add_action( 'wp_head', array( 'Webmention_Receiver', 'html_header' ), 99 );
		add_action( 'send_headers', array( 'Webmention_Receiver', 'http_header' ) );
		add_filter( 'host_meta', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_user_data', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_post_data', array( 'Webmention_Receiver', 'jrd_links' ) );

		// default handlers
		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'check_dupes' ), 1, 1 );
		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'default_title_filter' ), 9, 1 );
		add_filter( 'webmention_comment_data', array( 'Webmention_Receiver', 'default_content_filter' ), 10, 1 );
		// Webmention Handler
		add_action( 'webmention_request', array( 'Webmention_Receiver', 'synchronous_handler' ) );
		// Alternative Basic Async Handler
		// add_action( 'webmention_request', array( 'Webmention_Receiver', 'basic_asynchronous_handler' ) );
		// add_action( 'async_process_webmention', array( 'Webmention_Receiver', 'process_webmention' ) );

	}

	/**
	 * Register the Routes.
	 */
	public static function register_routes() {
		register_rest_route( 'webmention', '/endpoint', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( 'Webmention_Receiver', 'post' ),
				'args' => array(
					'source' => array(
						'required' => 'true',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => 'wp_http_validate_url',
					),
					'target' => array(
						'required' => 'true',
						'sanitize_callback' => 'esc_url_raw',
						'validate_callback' => 'wp_http_validate_url',
					),
				),
			),
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( 'Webmention_Receiver', 'get' ),
				),
			)
		);
	}

	/**
	 * Hooks into the REST API output to output alternatives to JSON.
	 *
	 * This is only done for the webmention endpoint.
	 *
	 * @access private
	 * @since 0.1.0
	 *
	 * @param bool                      $served  Whether the request has already been served.
	 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request           $request Request used to generate the response.
	 * @param WP_REST_Server            $server  Server instance.
	 * @return true
	 */
	public static function serve_request( $served, $result, $request, $server ) {
		if ( '/webmention/endpoint' !== $request->get_route() ) {
			return $served;
		}
		if ( 'GET' !== $request->get_method() ) {
			return $served;
		}
		// If someone tries to poll the webmention endpoint return a webmention form.
		if ( ! headers_sent() ) {
			$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
		}
		get_header();
		self::webmention_form();
		get_footer();
		return true;
	}

	/**
	 * Post Callback for the webmention endpoint.
	 *
	 * Returns the response.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get( $request ) {
		return '';
	}

	/**
	 * Generates a webmention form
	 */
	public static function webmention_form() {
		?>
		<br />
		<form id="webmention-form" action="<?php echo get_webmention_endpoint(); ?>" method="post">
		<p>
			<label for="webmention-source"><?php _e( 'Source URL:', 'webmention' ); ?></label>
			<input id="webmention-source" size="15" type="url" name="source" placeholder="Where Did You Link to?" />
		</p>
		<p>
			<label for="webmention-target"><?php _e( 'Target URL(must be on this site):', 'webmention' ); ?></label>
			<input id="webmention-target" size="15" type="url" name="target" placeholder="What Did You Link to?" />
			<br /><br/>
			<input id="webmention-submit" type="submit" name="submit" value="Send" />
		</p>
		</form>
		<p><?php _e( 'Webmention is a way for you to tell me "Hey, I have written a response to your post."', 'webmention' ); ?> </p>
		<p><?php _e( 'Learn more about webmentions at <a href="http://webmention.net">webmention.net</a>', 'webmention' ); ?> </p>
		<?php
	}


	/**
	 * Post Callback for the webmention endpoint.
	 *
	 * Returns the response.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 *
	 * @uses apply_filters calls "webmention_post_id" on the post_ID
	 *
	 */
	public static function post( $request ) {
		$params = array_filter( $request->get_params() );
		if ( ! isset( $params['source'] ) ) {
			return new WP_Error( 'source' , 'Source is Missing', array( 'status' => 400 ) );
		}
		if ( ! isset( $params['target'] ) ) {
			return new WP_Error( 'target', 'Target is Missing', array( 'status' => 400 ) );
		}
		$source = $params['source'];
		$target = $params['target'];
		if ( ! stristr( $target, preg_replace( '/^https?:\/\//i', '', home_url() ) ) ) {
			return new WP_Error( 'target', 'Target is Not on this Domain', array( 'status' => 400 ) );
		}

		$comment_post_ID = url_to_postid( $target );
		// add some kind of a "default" id to add linkbacks to a specific post/page
		$comment_post_ID = apply_filters( 'webmention_post_id', $comment_post_ID, $target );
		if ( url_to_postid( $source ) === $comment_post_ID ) {
			return new WP_Error( 'sourceequalstarget', 'Target and Source cannot direct to the same resource', array( 'status' => 400 ) );
		}
		// check if post id exists
		if ( ! $comment_post_ID ) {
			return new WP_Error( 'targetnotvalid', 'Target is Not a Valid Post', array( 'status' => 400 ) );
		}
		// check if pings are allowed
		if ( ! pings_open( $comment_post_ID ) ) {
			return new WP_Error( 'pingsclosed', 'Pings are Disabled for this Post', array( 'status' => 400 ) );
		}
		$post = get_post( $comment_post_ID );
		if ( ! $post ) {
			return new WP_Error( 'targetnotvalid', 'Target is Not a Valid Post', array( 'status' => 400 ) );
		}
		// In the event of async processing this needs to be stored here as it might not be available
		// later.
		$comment_author_IP = preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] );
		$comment_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT']: '';
		$comment_date = current_time( 'mysql' );
		$comment_date_gmt = current_time( 'mysql', 1 );

		// change this if your theme can't handle the Webmentions comment type
		$comment_type = WEBMENTION_COMMENT_TYPE;

		// change this if you want to auto approve your Webmentions
		$comment_approved = WEBMENTION_COMMENT_APPROVE;

		$commentdata = compact( 'comment_type', 'comment_approved', 'comment_agent', 'comment_date', 'comment_date_gmt', 'comment_post_ID', 'comment_author_IP', 'source', 'target' );
		// be sure to return an error message or response to the end of your request handler
		return apply_filters( 'webmention_request', $commentdata );
	}

	public static function basic_asynchronous_handler( $data ) {
		// Schedule the Processing to Be Completed sometime in the next 3 minutes
		wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'async_process_webmention', array( $data ) );
		return new WP_REST_Response( $data, 202 );
	}
	public static function synchronous_handler( $data ) {
		$data = self::process_webmention( $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		// Return select data
		$return = array(
			'link' => apply_filters( 'webmention_success_message', get_comment_link( $data['comment_ID'] ) ),
			'source' => $data['source'],
			'target' => $data['target'],
		);
		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Webmention Processor.
	 *
	 *
	 *
	 * @param array $data

	 * @uses apply_filters calls "webmention_comment_data" to filter the comment-data array
	 * @uses apply_filters calls "webmention_update" to filter the update (Deprecated in 4.7)
	 * @uses do_action calls "webmention_post" on the comment_ID to be pingback
	 *	and trackback comparable.
	 */

	public static function process_webmention( $data ) {
		if ( ! $data ) {
			error_log( 'Webmention Data Failed' );
			return $data;
		}
		$data = self::webmention_verify( $data );
		if ( is_wp_error( $data ) ) {
			// Allows for Error Logging or Handling
			do_action( 'webmention_receive_error', $data );
			return $data;
		}
		// Set Comment Author URL to Source
		$data['comment_author_url'] = $data['source'];
		// add empty fields
		$data['comment_parent'] = $data['comment_author_email'] = '';

		$data = apply_filters( 'webmention_comment_data', $data );

		// disable flood control
		remove_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );
		// update or save webmention
		if ( empty( $data['comment_ID'] ) ) {
			// save comment
			$data['comment_ID'] = wp_new_comment( $data );
			do_action( 'webmention_post', $data['comment_ID'], $data );
		} else {
			// Temporary placeholder for webmention updates until Core supports an edit comment filter
			$data = apply_filters( 'webmention_update', $data );
			// save comment
			wp_update_comment( $data );
		}
		// re-add flood control
		add_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );
		if ( WP_DEBUG ) {
			error_log( sprintf( __( 'Webmention from %1$s to %2$s processed. Keep the web talking! :-)' ),
			$data['source'], $data['target'] ) );
		}
		// Return the comment data
		return $data;
	}

	/**
	 * Verify a webmention and either return an error if not verified or return the array with retrieved
	 * data.
	 *
	 * @param array $data {
	 * 	@param $comment_type
	 * 	@param $comment_author_url
	 * 	@param $comment_author_IP
	 * 	@param $target
	 * }
	 *
	 * @return array|WP_Error $data Return Error Object or array with added fields {
	 * 	@param $remote_source
	 * 	@param $remote_source_original
	 * 	@param $content_type
	 * }
	 */
	public static function webmention_verify( $data ) {
		global $wp_version;
		if ( ! is_array( $data ) || empty( $data ) ) {
			return new WP_Error( 'invaliddata', 'Invalid Data Passed', array( 'status' => 500 ) );
		}
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 10,
			'limit_response_size' => 153600,
			'redirection' => 5,
			'user-agent' => "$user_agent; verifying Webmention from " . $data['comment_author_IP'],
		);

		$response = wp_safe_remote_head( $data['source'], $args );

		// check if source is accessible
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sourceurl', 'Source URL not found', array( 'status' => 400 ) );
		}
		// A valid response code from the other server would not be considered an error.
		$response_code = wp_remote_retrieve_response_code( $response );
		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return new WP_Error( 'content-type', 'Content Type is Media', array( 'status' => 400 ) );
		}
		switch ( $response_code ) {
			case 200:
				$response = wp_safe_remote_get( $data['source'], $args );
				break;
			case 410:
				return new WP_Error( 'deleted', 'Page has Been Deleted', array( 'status' => 400, 'data' => $data ) );
			case 452:
				return new WP_Error( 'removed', 'Page Removed for Legal Reasons', array( 'status' => 400, 'data' => $data ) );
			default:
				return new WP_Error( 'sourceurl', wp_remote_retrieve_response_message( $response ), array( 'status' => 400 ) );
		}
		$remote_source_original = wp_remote_retrieve_body( $response );

		// check if source really links to target
		if ( ! strpos( htmlspecialchars_decode( $remote_source_original ), str_replace( array(
			'http://www.',
			'http://',
			'https://www.',
			'https://',
		), '', untrailingslashit( preg_replace( '/#.*/', '', $data['target'] ) ) ) ) ) {
			return new WP_Error( 'targeturl', 'Cannot find target link.', array( 'status' => 400 ) );
		}
		if ( ! function_exists( 'wp_kses_post' ) ) {
			include_once( ABSPATH . 'wp-includes/kses.php' );
		}
		$remote_source = wp_kses_post( $remote_source_original );
		$content_type = wp_remote_retrieve_header( $response, 'Content-Type' );
		$commentdata = compact( 'remote_source', 'remote_source_original', 'content_type' );

		return array_merge( $commentdata, $data );
	}

	/**
	 * Try to make a nice comment
	 *
	 * @param array $commentdata the comment-data
	 *
	 * @return array the filtered comment-data
	 */
	public static function default_content_filter( $commentdata ) {
		// get post format
		$post_id = $commentdata['comment_post_ID'];
		$post_format = get_post_format( $post_id );

		// replace "standard" with "Article"
		if ( ! $post_format || 'standard' === $post_format ) {
			$post_format = 'Article';
		} else {
			$post_formatstrings = get_post_format_strings();
			// get the "nice" name
			$post_format = $post_formatstrings[ $post_format ];
		}

		$host = parse_url( $commentdata['comment_author_url'], PHP_URL_HOST );

		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );

		// generate default text
		$commentdata['comment_content'] = sprintf( __( 'This %1$s was mentioned on <a href="%2$s">%3$s</a>', 'webmention' ), $post_format, esc_url( $commentdata['comment_author_url'] ), $host );

		return $commentdata;
	}

	/**
	 * Check if a comment already exists
	 *
	 * @param  array      $commentdata the comment, created for the webmention data
	 *
	 * @return array|null              the dupe or null
	 */
	public static function check_dupes( $commentdata ) {
		$args = array(
			'comment_post_ID' => $commentdata['comment_post_ID'],
			'author_url' => htmlentities( $commentdata['comment_author_url'] ),
		);

		$comments = get_comments( $args );
		// check result
		if ( ! empty( $comments ) ) {
			$comment = $comments[0];
			$commentdata['comment_ID'] = $comment->comment_ID;
			$commentdata['comment_approved'] = $comment->comment_approved;

			return $commentdata;
		}

		// check comments sent via salmon are also dupes
		// or anyone else who can't use comment_author_url as the original link,
		// but can use a _crossposting_link meta value.
		// @link https://github.com/pfefferle/wordpress-salmon/blob/master/plugin.php#L192
		$args = array(
			'comment_post_ID' => $commentdata['comment_post_ID'],
			'meta_key' => '_crossposting_link',
			'meta_value' => $commentdata['comment_author_url'],
		);
		$comments = get_comments( $args );

		// check result
		if ( ! empty( $comments ) ) {
			$comment = $comments[0];
			$commentdata['comment_ID'] = $comment->comment_ID;
			$commentdata['comment_approved'] = $comment->comment_approved;
		}

		return $commentdata;
	}

	/**
	 * Extend the "filter by comment type" of in the comments section
	 * of the admin interface with "webmention"
	 *
	 * @param array $types the different comment types
	 *
	 * @return array the filtert comment types
	 */
	public static function comment_types_dropdown( $types ) {
		$types['webmention'] = __( 'Webmentions', 'webmention' );

		return $types;
	}

	/**
	 * Try to make a nice title (username)
	 *
	 * @param array $commentdata the comment-data
	 *
	 * @return array the filtered comment-data
	 */
	public static function default_title_filter( $commentdata ) {
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
			$host = parse_url( $commentdata['comment_author_url'], PHP_URL_HOST );
			$commentdata['comment_author'] = preg_replace( '/^www\./', '', $host );
		}
		// strip leading www, if any
		return $commentdata;
	}

	/**
	 * Marks the post as "no webmentions sent yet"
	 *
	 * @param int $post_id
	 */
	public static function publish_post_hook( $post_id ) {
		// check if pingbacks are enabled
		if ( get_option( 'default_pingback_flag' ) ) {
			add_post_meta( $post_id, '_mentionme', '1', true );
		}
	}

	/**
	 * The Webmention autodicovery meta-tags
	 */
	public static function html_header() {
		// backwards compatibility with v0.1
		echo '<link rel="http://webmention.org/" href="' . get_webmention_endpoint() . '" />' . "\n";
		echo '<link rel="webmention" href="' . get_webmention_endpoint() . '" />' . "\n";
	}

	/**
	 * The Webmention autodicovery http-header
	 */
	public static function http_header() {
		// backwards compatibility with v0.1
		header( 'Link: <' . get_webmention_endpoint() . '>; rel="http://webmention.org/"', false );
		header( 'Link: <' . get_webmention_endpoint() . '>; rel="webmention"', false );
	}

	/**
	 * Generates webfinger/host-meta links
	 */
	public static function jrd_links( $array ) {
		$array['links'][] = array( 'rel' => 'webmention', 'href' => get_webmention_endpoint() );
		$array['links'][] = array( 'rel' => 'http://webmention.org/', 'href' => get_webmention_endpoint() );

		return $array;
	}
}
