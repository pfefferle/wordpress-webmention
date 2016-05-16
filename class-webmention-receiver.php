<?php

// initialize receiver
add_action( 'init', array( 'Webmention_Receiver', 'init' ) );

if ( ! defined( 'WEBMENTION_COMMENT_APPROVE' ) ) {
	define( 'WEBMENTION_COMMENT_APPROVE', 0 );
}

if ( ! defined( 'WEBMENTION_COMMENT_TYPE' ) ) {
	define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );
}

/**
 * Webmention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class Webmention_Receiver {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		add_filter( 'query_vars', array( 'Webmention_Receiver', 'query_var' ) );
		add_action( 'parse_query', array( 'Webmention_Receiver', 'parse_query' ) );

		// admin settings
		add_action( 'admin_comment_types_dropdown', array( 'Webmention_Receiver', 'comment_types_dropdown' ) );

		// Endpoint Discovery
		add_action( 'wp_head', array( 'Webmention_Receiver', 'html_header' ), 99 );
		add_action( 'send_headers', array( 'Webmention_Receiver', 'http_header' ) );
		add_filter( 'host_meta', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_user_data', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_post_data', array( 'Webmention_Receiver', 'jrd_links' ) );

		// default handlers
		add_filter( 'webmention_title', array( 'Webmention_Receiver', 'default_title_filter' ), 10, 4 );
		add_filter( 'webmention_content', array( 'Webmention_Receiver', 'default_content_filter' ), 10, 4 );
		add_filter( 'webmention_check_dupes', array( 'Webmention_Receiver', 'check_dupes' ), 10, 3 );
		add_filter( 'webmention_source_verify', array( 'Webmention_Receiver', 'source_verify' ), 10, 4 );
		add_action( 'webmention_request', array( 'Webmention_Receiver', 'synchronous_request_handler' ), 10, 4 );

		// Save Last Updated Time as Comment Meta
		add_action( 'webmention_update', array( 'Webmention_Receiver', 'last_modified' ), 9, 2 );

	}

	/**
	* Error Function
	*
	* @param
	*/
	public static function error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			status_header( 500 );
			echo 'Unknown Error';
			exit;
		}
		// Allow for other actions such as error logging or debugging
		do_action( 'webmention_error', $error );
		if ( is_numeric( $error->get_error_code() ) ) {
			status_header( $error->get_error_code() );
		} // If the error code isn't numeric, something is wrong
		else {
			status_header( 500 );
		}
		echo $error->get_error_message();
		exit;
	}

	/**
	 * Adds some query vars
	 *
	 * @param array $vars
	 * @return array
	 */
	public static function query_var($vars) {
		$vars[] = 'webmention';

		return $vars;
	}

	/**
	 * Parse the Webmention request and render the document
	 *
	 * @param WP $wp WordPress request context
	 *
	 * @uses do_action() Calls 'webmention_request' on the default request
	 */
	public static function parse_query( $wp ) {
		// check if it is a Webmention request or not
		if ( ! array_key_exists( 'webmention', $wp->query_vars ) ) {
			return;
		}
		// plain text header
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );

		// check if source url is transmitted
		if ( ! isset( $_POST['source'] ) ) {
			self::error( new WP_Error( 400, '"source" is missing' ) );
		}
		if ( ! self::is_valid_url( $_POST['source'] ) ) {
			self::error( new WP_Error( 400, '"source" is not valid URL' ) );
		}

		// check if target url is transmitted
		if ( ! isset( $_POST['target'] ) ) {
			self::error( new WP_Error( 400, '"target" is missing' ) );
		}

		if ( ! self::is_valid_url( $_POST['target'] ) ) {
			self::error( new WP_Error( 400, '"target" is not valid URL', $_POST['target'] ) );
		}

		if ( ! stristr( $_POST['target'], preg_replace( '/^https?:\/\//i', '', get_site_url() ) ) ) {
			self::error( new WP_Error( 400, '"target" does not Point to Site', $_POST['target'] ) );
		}

		// remove url-scheme
		$schemeless_target = preg_replace( '/^https?:\/\//i', '', $_POST['target'] );

		// check post with http only
		$post_ID = url_to_postid( 'http://' . $schemeless_target );

		// if there is no post
		if ( ! $post_ID ) {
			// try https url
			$post_ID = url_to_postid( 'https://' . $schemeless_target );
		}

		// add some kind of a "default" id to add all
		// Webmentions to a specific post/page
		$post_ID = apply_filters( 'webmention_post_id', $post_ID, $_POST['target'] );

		// check if post id exists
		if ( ! $post_ID ) {
			self::error( new WP_Error( 400, 'Specified target URL not found', $_POST['target'] ) );
		}

		// check if pings are allowed
		if ( ! pings_open( $post_ID ) ) {
			self::error( new WP_Error( 400, 'Webmentions are disabled for this resource', $post_ID ) );
		}

		$post_ID = intval( $post_ID );
		$post = get_post( $post_ID );

		// check if post exists
		if ( ! $post ) {
			return;
		}

		$approved = array( 'vouch', 'csrf' );
		$approved = apply_filters( 'webmention_query_var', $approved );
		$var = array();
		foreach ( $approved as $app ) {
			if ( array_key_exists( $app, $_POST ) ) {
				$var[] = $_POST[ $app ];
			}
		}

		// be sure to add an "exit;" to the end of your request handler
		do_action( 'webmention_request', $_POST['source'], $_POST['target'], $post, $var );

		// if no "action" is responsible, return a 500
		self::error( new WP_Error( 500, 'Webmention Handler Failed' ) );
	}

	/**
	* Is This URL Valid
	*
	* Runs a validity check on the URL. Based on built-in WordPress Validations
	*
	* @param $url URL to fetch
	*
	* @return boolean
	**/
	public static function is_valid_url( $url ) {
		$original_url = $url;
		$url = wp_kses_bad_protocol( $url, array( 'http', 'https' ) );
		if ( ! $url || strtolower( $url ) !== strtolower( $original_url ) ) {
			return false; }
		/** @todo Should use Filter Extension or custom preg_match instead. */
		$parsed_url = wp_parse_url( $url );
		if ( ! $parsed_url || empty( $parsed_url['host'] ) ) {
			return false; }
		if ( isset( $parsed_url['user'] ) || isset( $parsed_url['pass'] ) ) {
			return false; }
		$ip = gethostbyname( $parsed_url['host'] );
		if ( ! $ip || $ip == $parsed_url['host'] ) {
				return false;
		}
		return true;
	}

	/**
	* Retrieves the Source or Returns an Error
	*
	* Tries to fetch the URL

	 * @param $url URL to fetch
	 *
	* @return array|WP_Error Return the response or an Error Object
	**/
	public static function get( $url ) {
		global $wp_version;
		$user_agent = apply_filters( 'http_headers_useragent', 'Webmention (WordPress/' . $wp_version . ')' );
		$args = array(
					'timeout' => 10,
					'limit_response_size' => 1048576,
			    'redirection' => 20,
					'user-agent' => $user_agent,
		);
		$response = wp_remote_head( $url, $args );
		// check if source is accessible
		if ( is_wp_error( $response ) ) {
			return( $response );
		}
		// A valid response code from the other server would not be considered an error.
		$response_code = wp_remote_retrieve_response_code( $response );
		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return new WP_Error( 'content-type', 'Content Type is Media' );
		}
		switch ( $response_code ) {
			case 200:
				$response = wp_remote_get( $url, $args );
			break;
			case 410:
			return new WP_Error( 'gone', 'Page is Gone' );
			default:
		  return new WP_Error( $response_code,  wp_remote_retrieve_response_message( $response ) );
		}
		return ( $response );
	}

	/**
	 * Synchronous request handler
	 *
	 * Tries to map a target url to a specific post and generates a simple
	 * "default" comment.
	 *
	 * @param string $source the source url
	 * @param string $target the target url
	 * @param string $post the post associated with the target
	 * @param array $var Approved Variables
	 *
	 * @uses apply_filters calls "webmention_post_id" on the post_ID
	* @uses apply_filters calls "webmention_source_verify" to verify the source links to the targetr
	 * @uses apply_filters calls "webmention_title" on the default comment-title
	 * @uses apply_filters calls "webmention_content" on the default comment-content
	 * @uses apply_filters calls "webmention_comment_type" on the default comment type
	 *	the default is "webmention"
	 * @uses apply_filters calls "webmention_comment_approve" to set the comment
	 *	to auto-approve (for example)
	 * @uses apply_filters calls "webmention_comment_parent" to add a parent comment-id
	 * @uses apply_filters calls "webmention_success_header" on the default response
	 *	header
	 * @uses do_action calls "webmention_post" on the comment_ID and commentdata to match pingback
	 *	and trackback
	 * @uses do_action calls "webmention_update" on the comment_ID and commentdata when the Webmention is being updated
	 */
	public static function synchronous_request_handler( $source, $target, $post, $var ) {
		$response = self::get( $source );
		if ( is_wp_error( $response ) ) {
			status_header( 400 );
			// Error Handler Should End with an Exit. Default handling is below.
			do_action( 'webmention_retrieve_error', $post, $source, $response );
			self::error( new WP_Error( 400, 'Unable to Retrieve Source: ' . $response->get_error_message(), $response ) );

		}
		$remote_source = wp_remote_retrieve_body( $response );
		// Content Type to Be Added to Commentdata to be used by hooks or filters.
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		// check if source really links to the target. Allow for more complex verification using content type
		if ( ! apply_filters( 'webmention_source_verify', false, $remote_source, $target, $content_type ) ) {
			self::error( new WP_Error( 400, 'Source Site Does Not Link to Target', compact( 'remote_source', 'source', 'target', 'content_type' ) ) );
		}
		// if it does, get rid of all evil
		if ( ! function_exists( 'wp_kses_post' ) ) {
			include_once( ABSPATH . 'wp-includes/kses.php' );
		}
		// To Match New Pingback Functionality the filtered and original source will be passed in the commentdata.
		$remote_source_original = $remote_source;
		$remote_source = wp_kses_post( $remote_source );

		// generate comment
		$comment_post_ID = (int) $post->ID;
		$comment_author_email = '';
		$comment_author_url = esc_url_raw( $source );

		// filter title and content of the comment. Title in a linkback is stored in the author field
		$comment_author = wp_slash( apply_filters( 'webmention_title', '', $remote_source, $target, $source ) );
		$comment_content = wp_slash( apply_filters( 'webmention_content', '', $remote_source_original, $target, $source ) );

		// change this if your theme can't handle the Webmentions comment type
		$comment_type = apply_filters( 'webmention_comment_type', WEBMENTION_COMMENT_TYPE );

		// change this if you want to auto approve your Webmentions
		$comment_approved = apply_filters( 'webmention_comment_approve', WEBMENTION_COMMENT_APPROVE );

		// filter the parent id
		$comment_parent = apply_filters( 'webmention_comment_parent', null, $target );
		$commentdata = compact( 'comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_content', 'comment_type', 'comment_parent', 'comment_approved', 'remote_source', 'remote_source_original', 'content_type', 'var' );

		// disable flood control
		remove_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );
		// check dupes first
		$comment = apply_filters( 'webmention_check_dupes', null, $post->ID, $source );

		// update or save Webmention
		if ( $comment ) {
			// Assume that if the original was approved, the update should be as well. This can be overridden
			$commentdata['comment_approved'] = $comment->comment_approved;

			$commentdata['comment_ID'] = $comment->comment_ID;
			// Filter the Comment Data if needed for more intelligent updating
			$commentdata = apply_filters( 'pre_update_webmention', $commentdata, $comment );
			// save comment
			wp_update_comment( $commentdata );
			$comment_ID = $comment->comment_ID;

			do_action( 'webmention_update', $comment_ID, $commentdata );
		} else {
			// save comment
			$comment_ID = wp_new_comment( $commentdata );

			do_action( 'webmention_post', $comment_ID, $commentdata );
		}

		// re-add flood control
		add_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// set header
		status_header( apply_filters( 'webmention_success_header', 200 ) );

		// render a simple and customizable text output
		echo apply_filters( 'webmention_success_message', get_comment_link( $comment_ID ) );

		exit;
	}

	/**
	* Verify Source
	*
	* @param boolean $verified Should be false
	* @param string $remote_source The retrieved source
	* @param string $target The target URL
	* @param string $content-type Content Type returned from the request
	*
	* @return boolean True if the target URL is in the source
	*/
	public static function source_verify($verified, $remote_source, $target, $content_type) {
		$remote_source = htmlspecialchars_decode( $remote_source );
		return strpos( $remote_source, str_replace( array( 'http://www.', 'http://', 'https://www.', 'https://' ), '', untrailingslashit( preg_replace( '/#.*/', '', $target ) ) ) );
	}

	/**
	 * Add Last Updated Meta to Updated Webmentions
	*/
	public static function last_modified( $comment_id, $commentdata ) {
		update_comment_meta( $comment_id, 'comment_modified', current_time( 'mysql' ) );
		update_comment_meta( $comment_id, 'comment_modified_gmt', current_time( 'mysql', 1 ) );
	}

	/**
	 * Try to make a nice comment
	 *
	 * @param string $context the comment-content
	 * @param string $contents the HTML of the source
	 * @param string $target the target URL
	 * @param string $source the source URL
	 *
	 * @return string the filtered content
	 */
	public static function default_content_filter( $content, $contents, $target, $source ) {
		// get post format
		$post_ID = url_to_postid( $target );
		$post_format = get_post_format( $post_ID );

		// replace "standard" with "Article"
		if ( ! $post_format || 'standard' == $post_format ) {
			$post_format = 'Article';
		} else {
			$post_formatstrings = get_post_format_strings();
			// get the "nice" name
			$post_format = $post_formatstrings[ $post_format ];
		}

		$parsed = wp_parse_url( $source );
		$host = $parsed['host'];
		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );

		// generate default text
		$content = sprintf( __( 'This %s was mentioned on <a href="%s">%s</a>', 'webmention' ), $post_format, esc_url( $source ), $host );
		return $content;
	}

	/**
	 * Check if a comment already exists
	 *
	 * @param  array      $comment		the filtered comment
	 * @param  int				$post_ID		the post ID of the post
	 * @param	 string			$source			The Source URL being checked
	 *
	 * @return array|null              the dupe or null
	 */
	public static function check_dupes( $comment, $post_ID, $source ) {
		global $wpdb;
		global $wp_version;
		if ( $wp_version >= 4.4 ) {
			// check if comment is already set
			$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_author_url = %s", $post_ID, htmlentities( $source ) ) );
		} else {
			$args = array(
						'comment_post_ID' => $post_ID,
						'author_url' => htmlentities( $source ),
			);
			$comments = get_comments( $args );
		}
		// check result
		if ( ! empty( $comments ) ) {
			return $comments[0];
		}

		// check comments sent via salmon are also dupes
		// or anyone else who can't use comment_author_url as the original link,
		// but can use a _crossposting_link meta value.
		// @link https://github.com/pfefferle/wordpress-salmon/blob/master/plugin.php#L192
		if ( $wp_version >= 4.4 ) {
			$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments INNER JOIN $wpdb->commentmeta USING (comment_ID) WHERE comment_post_ID = %d AND meta_key = '_crossposting_link' AND meta_value = %s", $post_ID, htmlentities( $source ) ) );
		} else {
			$args = array(
			'comment_post_ID' => $post_ID,
			'author_url' => htmlentities( $source ),
						'meta_key' => '_crossposting_link',
						'meta_value' => $source,
			);
			$comments = get_comments( $args );
		}

		// check result
		if ( ! empty( $comments ) ) {
			return $comments[0];
		}

		return $comment;
	}

	/**
	 * Extend the "filter by comment type" of in the comments section
	 * of the admin interface with "Webmention"
	 *
	 * @param array $types the different comment types
	 *
	 * @return array the filtert comment types
	 */
	public static function comment_types_dropdown( $types ) {
		$types['webmention'] = __( 'Webmentions', 'Webmention' );

		return $types;
	}

	/**
	 * Try to make a nice title (username)
	 *
	 * @param string $title the comment-title (username)
	 * @param string $contents the HTML of the source
	 * @param string $target the target URL
	 * @param string $source the source URL
	 *
	 * @return string the filtered title
	 */
	public static function default_title_filter( $title, $contents, $target, $source ) {
		$meta_tags = self::get_meta_tags( $contents );

		// use meta-author
		if ( $meta_tags && is_array( $meta_tags ) && array_key_exists( 'author', $meta_tags ) ) {
			$title = $meta_tags['author'];
		} elseif ( preg_match( '/<title>(.+)<\/title>/i', $contents, $match ) ) { // use title
			$title = trim( $match[1] );
		} else { // or host
			$host = wp_parse_url( $source );
			$host = $host['host'];

			// strip leading www, if any
			$title = preg_replace( '/^www\./', '', $host );
		}

		return $title;
	}

	public static function get_meta_tags( $source_content ) {
		if ( ! $source_content ) {
			return null;
		}
		$meta = array();
		if ( preg_match_all( '/<meta [^>]+>/', $source_content, $matches ) ) {
			$items = $matches[0];

			foreach ( $items as $value ) {
				if ( preg_match( '/(property|name)="([^"]+)"[^>]+content="([^"]+)"/', $value, $new_matches ) ) {
					$meta_name  = $new_matches[2];
					$meta_value = $new_matches[3];

					// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
					if ( strlen( $meta_name ) > 100 ) {
						continue;
					}
					$meta[ $meta_name ] = $meta_value;
				}
			}
		}
		return $meta;
	}

	/**
	* The Webmention autodicovery meta-tags
	*/
	public static function html_header() {
		$endpoint = apply_filters( 'webmention_endpoint', site_url( '?webmention=endpoint' ) );

		echo '<link rel="webmention" href="' . $endpoint . '" />' . "\n";
	}

	/**
	* The Webmention autodicovery http-header
	*/
	public static function http_header() {
		$endpoint = apply_filters( 'webmention_endpoint', site_url( '?webmention=endpoint' ) );

		header( 'Link: <' . $endpoint . '>; rel="webmention"', false );
	}

	/**
	* Generates webfinger/host-meta links
	*/
	public static function jrd_links( $array ) {
		$endpoint = apply_filters( 'webmention_endpoint', site_url( '?webmention=endpoint' ) );

		$array['links'][] = array( 'rel' => 'webmention', 'href' => $endpoint );

		return $array;
	}

	/**
	* Generates a webmention form
	*/
	public static function webmention_form() {
	?> 
	 <br />
	 <form id="webmention-form" action="<?php echo site_url( '?webmention=endpoint' ); ?>" method="post">
	  <p>
		<label for="webmention-source"><?php _e( 'Respond on your own site:', 'webmention_form' ); ?></label>
		<input id="webmention-source" size="15" type="url" name="source" placeholder="http://example.com/post/100" />

		<input id="webmention-submit" type="submit" name="submit" value="Send" />

	  <input id="webmention-target" type="hidden" name="target" value="<?php the_permalink(); ?>" />
		</p>
	</form>
	<?php
	}


}
