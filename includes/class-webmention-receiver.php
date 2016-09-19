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
		add_filter( 'query_vars', array( 'Webmention_Receiver', 'query_vars' ) );
		add_action( 'parse_query', array( 'Webmention_Receiver', 'parse_query' ) );

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
		add_action( 'webmention_request', array( 'Webmention_Receiver', 'default_request_handler' ), 10 );
	}

	/**
	 * Adds some query vars
	 *
	 * @param array $vars
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'webmention';
		$vars[] = 'csrf';

		return $vars;
	}

	/**
	 * Parse the Webmention request and render the document
	 *
	 * @param WP $wp WordPress request context
	 *
	 * @uses do_action calls 'webmention_request' on the default request
	 * @uses apply_filters calls "webmention_post_id" on the post_ID
	 */
	public static function parse_query( $wp ) {
		// check if it is a webmention request or not
		if ( ! array_key_exists( 'webmention', $wp->query_vars ) ) {
			return;
		}

		// plain text header
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
		header( 'Access-Control-Allow-Origin: *' );

		// check if source url is transmitted
		if ( ! isset( $_POST['source'] ) ) {
			status_header( 400 );
			echo '"source" is missing';
			exit;
		}

		// check if target url is transmitted
		if ( ! isset( $_POST['target'] ) ) {
			status_header( 400 );
			echo '"target" is missing';
			exit;
		}

		if ( ! stristr( $_POST['target'], preg_replace( '/^https?:\/\//i', '', home_url() ) ) ) {
			status_header( 400 );
			echo '"target" is not on this site';
			exit;
		}

		// check post with http only
		$comment_post_id = url_to_postid( $_POST['target'] );

		// add some kind of a "default" id to add all
		// webmentions to a specific post/page
		$comment_post_id = apply_filters( 'webmention_post_id', $comment_post_id, $_POST['target'] );
		// check if post id exists
		if ( ! $comment_post_id ) {
			return;
		}
		// check if pings are allowed
		if ( ! pings_open( $comment_post_id ) ) {
			status_header( 400 );
			echo 'Pings are disabled for this post';
			exit;
		}

		$comment_post_id = intval( $comment_post_id );
		$post = get_post( $comment_post_id );
		// check if post exists
		if ( ! $post ) {
			status_header( 400 );
			echo 'Specified target URL not found.';
			exit;
		}

		$comment_author_url = esc_url_raw( $_POST['source'] );
		$target = esc_url_raw( $_POST['target'] );

		$commentdata = compact( 'comment_post_ID', 'comment_author_url', 'target' );

		// be sure to add an "exit;" to the end of your request handler
		do_action( 'webmention_request', $commentdata );

		// if you get to this point the webmention handler failed for unknown reasons
		status_header( 500 );
		echo 'Webmention Handler failed';

		exit;
	}

	/**
	 * Default request handler
	 *
	 * Tries to map a target url to a specific post and generates a simple
	 * "default" comment.
	 *
	 * @param array $data
	 *
	 * @uses apply_filters calls "webmention_comment_data" to filter the comment-data array
	 * @uses apply_filters calls "webmention_success_header" on the default response
	 *	header
	 * @uses do_action calls "webmention_post" on the comment_ID to be pingback
	 *	and trackback compatible
	 */
	public static function default_request_handler( $data ) {
		global $wp_version;

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 100,
			'limit_response_size' => 1048576,
			'redirection' => 20,
			'user-agent' => "$user_agent; verifying Webmention from " . $data['comment_author_IP'],
		);

		$response = wp_remote_get( $data['comment_author_url'], $args );

		// check if source is accessible
		if ( is_wp_error( $response ) ) {
			status_header( 400 );
			echo 'Source URL not found.';
			exit;
		}

		$remote_source_original = wp_remote_retrieve_body( $response );

		// check if source really links to target
		if ( ! strpos( htmlspecialchars_decode( $remote_source_original ), str_replace( array( 'http://www.', 'http://', 'https://www.', 'https://' ), '', untrailingslashit( preg_replace( '/#.*/', '', $data['target'] ) ) ) ) ) {
			status_header( 400 );
			echo "Can't find target link.";
			exit;
		}

		// if it does, get rid of all evil
		if ( ! function_exists( 'wp_kses_post' ) ) {
			include_once( ABSPATH . 'wp-includes/kses.php' );
		}
		$remote_source = wp_kses_post( $remote_source_original );

		//$comment_author_IP = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );

		// change this if your theme can't handle the Webmentions comment type
		$comment_type = WEBMENTION_COMMENT_TYPE;

		// change this if you want to auto approve your Webmentions
		$comment_approved = WEBMENTION_COMMENT_APPROVE;

		// add empty fields
		$comment_parent = $comment_author_email = $comment_author = $comment_content = '';

		$commentdata = compact( 'comment_author', 'comment_author_email', 'comment_content', 'comment_parent', 'remote_source', 'remote_source_original', 'comment_approved', 'comment_type' );
		$commentdata = array_merge( $commentdata, $data );

		$commentdata = apply_filters( 'webmention_comment_data', $commentdata );

		// disable flood control
		remove_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// update or save webmention
		if ( empty( $commentdata['comment_ID'] ) ) {
			// save comment
			$comment_id = wp_new_comment( $commentdata );
		} else {
			// save comment
			wp_update_comment( $commentdata );
			$comment_id = $comment->comment_ID;
		}

		// re-add flood control
		add_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// set header
		status_header( apply_filters( 'webmention_success_header', 200 ) );

		// render a simple and customizable text output
		echo apply_filters( 'webmention_success_message', get_comment_link( $comment_id ) );

		do_action( 'webmention_post', $comment_id );
		exit;
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
		if ( ! $post_format || 'standard' == $post_format ) {
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
