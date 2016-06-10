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
		add_action( 'parse_query', array( 'Webmention_Receiver', 'parse_query' ) );

		add_action( 'admin_comment_types_dropdown', array( 'Webmention_Receiver', 'comment_types_dropdown' ) );

		// endpoint discovery
		add_action( 'wp_head', array( 'Webmention_Receiver', 'html_header' ), 99 );
		add_action( 'send_headers', array( 'Webmention_Receiver', 'http_header' ) );
		add_filter( 'host_meta', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_user_data', array( 'Webmention_Receiver', 'jrd_links' ) );
		add_filter( 'webfinger_post_data', array( 'Webmention_Receiver', 'jrd_links' ) );

		// default handlers
		add_filter( 'webmention_title', array( 'Webmention_Receiver', 'default_title_filter' ), 10, 4 );
		add_filter( 'webmention_content', array( 'Webmention_Receiver', 'default_content_filter' ), 10, 4 );
		add_filter( 'webmention_check_dupes', array( 'Webmention_Receiver', 'check_dupes' ), 10, 2 );
		add_action( 'webmention_request', array( 'Webmention_Receiver', 'default_request_handler' ), 10, 3 );
	}

	/**
	 * Adds some query vars
	 *
	 * @param array $vars
	 * @return array
	 */
	public static function query_var( $vars ) {
		$vars[] = 'webmention';
		$vars[] = 'csrf';

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
		global $wp_version;

		// check if it is a webmention request or not
		if ( ! array_key_exists( 'webmention', $wp->query_vars ) ) {
			return;
		}

		// plain text header
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );

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

		if ( ! stristr( $_POST['target'], preg_replace( '/^https?:\/\//i', '', get_site_url() ) ) ) {
			status_header( 400 );
			echo '"target" is not on this site';
		}

		$remote_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 100,
			'limit_response_size' => 1048576,
			'redirection' => 20,
			'user-agent' => "$user_agent; verifying Webmention from $remote_ip",
		);

		$response = wp_remote_get( $_POST['source'], $args );

		// check if source is accessible
		if ( is_wp_error( $response ) ) {
			status_header( 400 );
			echo 'Source URL not found.';
			exit;
		}

		$contents = wp_remote_retrieve_body( $response );

		// check if source really links to target
		if ( ! strpos( htmlspecialchars_decode( $contents ), str_replace( array( 'http://www.', 'http://', 'https://www.', 'https://' ), '', untrailingslashit( preg_replace( '/#.*/', '', $_POST['target'] ) ) ) ) ) {
			status_header( 400 );
			echo "Can't find target link.";
			exit;
		}

		// if it does, get rid of all evil
		if ( ! function_exists( 'wp_kses_post' ) ) {
			include_once( ABSPATH . 'wp-includes/kses.php' );
		}
		$contents = wp_kses_post( $contents );

		// be sure to add an "exit;" to the end of your request handler
		do_action( 'webmention_request', $_POST['source'], $_POST['target'], $contents );

		// if no "action" is responsible, return a 404
		status_header( 404 );
		echo 'Specified target URL not found.';

		exit;
	}

	/**
	 * Default request handler
	 *
	 * Tries to map a target url to a specific post and generates a simple
	 * "default" comment.
	 *
	 * @param string $source the source url
	 * @param string $target the target url
	 * @param string $contents the html code of $source
	 *
	 * @uses apply_filters calls "webmention_post_id" on the post_ID
	 * @uses apply_filters calls "webmention_title" on the default comment-title
	 * @uses apply_filters calls "webmention_content" on the default comment-content
	 * @uses apply_filters calls "webmention_comment_type" on the default comment type
	 *	the default is "webmention"
	 * @uses apply_filters calls "webmention_comment_approve" to set the comment
	 *	to auto-approve (for example)
	 * @uses apply_filters calls "webmention_comment_parent" to add a parent comment-id
	 * @uses apply_filters calls "webmention_success_header" on the default response
	 *	header
	 * @uses do_action calls "webmention_post" on the comment_ID to be pingback
	 *	and trackback compatible
	 */
	public static function default_request_handler( $source, $target, $contents ) {
		// remove url-scheme
		$schemeless_target = preg_replace( '/^https?:\/\//i', '', $target );

		// check post with http only
		$post_ID = url_to_postid( 'http://' . $schemeless_target );

		// if there is no post
		if ( ! $post_ID ) {
			// try https url
			$post_ID = url_to_postid( 'https://' . $schemeless_target );
		}

		// add some kind of a "default" id to add all
		// webmentions to a specific post/page
		$post_ID = apply_filters( 'webmention_post_id', $post_ID, $target );

		// check if post id exists
		if ( ! $post_ID ) {
			return;
		}

		// check if pings are allowed
		if ( ! pings_open( $post_ID ) ) {
			status_header( 403 );
			echo 'Pings are disabled for this post';
			exit;
		}

		$post_ID = intval( $post_ID );
		$post = get_post( $post_ID );

		// check if post exists
		if ( ! $post ) {
			return;
		}

		// filter title or content of the comment
		$title = apply_filters( 'webmention_title', '', $contents, $target, $source );
		$content = apply_filters( 'webmention_content', '', $contents, $target, $source );

		// generate comment
		$comment_post_ID = (int) $post->ID;
		$comment_author = wp_slash( $title );
		$comment_author_email = '';
		$comment_author_url = esc_url_raw( $source );
		$comment_content = wp_slash( $content );

		// change this if your theme can't handle the Webmentions comment type
		$comment_type = apply_filters( 'webmention_comment_type', WEBMENTION_COMMENT_TYPE );

		// change this if you want to auto approve your Webmentions
		$comment_approved = apply_filters( 'webmention_comment_approve', WEBMENTION_COMMENT_APPROVE );

		// filter the parent id
		$comment_parent = apply_filters( 'webmention_comment_parent', null, $target );

		$commentdata = compact( 'comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_content', 'comment_type', 'comment_parent', 'comment_approved' );

		// check dupes
		$comment = apply_filters( 'webmention_check_dupes', null, $commentdata );

		// disable flood control
		remove_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// update or save webmention
		if ( $comment ) {
			$commentdata['comment_ID'] = $comment->comment_ID;
			$commentdata['comment_approved'] = $comment->comment_approved;
			// save comment
			wp_update_comment( $commentdata );
			$comment_ID = $comment->comment_ID;
		} else {
			// save comment
			$comment_ID = wp_new_comment( $commentdata );
		}

		// re-add flood control
		add_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// set header
		status_header( apply_filters( 'webmention_success_header', 200 ) );

		// render a simple and customizable text output
		echo apply_filters( 'webmention_success_message', get_comment_link( $comment_ID ) );

		do_action( 'webmention_post', $comment_ID );
		exit;
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

		$host = parse_url( $source, PHP_URL_HOST );

		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );

		// generate default text
		$content = sprintf( __( 'This %s was mentioned on <a href="%s">%s</a>', 'webmention' ), $post_format, esc_url( $source ), $host );

		return $content;
	}

	/**
	 * Check if a comment already exists
	 *
	 * @param  array      $comment     the filtered comment
	 * @param  array      $commentdata the comment, created for the webmention data
	 *
	 * @return array|null              the dupe or null
	 */
	public static function check_dupes( $comment, $commentdata ) {
		$args = array(
			'comment_post_ID' => $commentdata['comment_post_ID'],
			'author_url' => htmlentities( commentdata['comment_author_url'] ),
		);

		$comments = get_comments( $args );
		// check result
		if ( ! empty( $comments ) ) {
			return $comments[0];
		}

		// check comments sent via salmon are also dupes
		// or anyone else who can't use comment_author_url as the original link,
		// but can use a _crossposting_link meta value.
		// @link https://github.com/pfefferle/wordpress-salmon/blob/master/plugin.php#L192
		$args = array(
			'comment_post_ID' => commentdata['comment_post_ID'],
			'meta_key' => '_crossposting_link',
			'meta_value' => commentdata['comment_author_url'],
		);
		$comments = get_comments( $args );

		// check result
		if ( ! empty( $comments ) ) {
			return $comments[0];
		}

		return null;
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
	 * Parse meta tags from source content
	 * Based on the Press This Meta Parsing Code
	 *
	 * @param string $source_content Source Content
	 *
	 * @return array meta tags
	 */
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
		$meta_tags = self::get_meta_tags( $source );

		// use meta-author
		if ( $meta_tags && is_array( $meta_tags ) && array_key_exists( 'author', $meta_tags ) ) {
			$title = $meta_tags['author'];
		} elseif ( preg_match( '/<title>(.+)<\/title>/i', $contents, $match ) ) { // use title
			$title = trim( $match[1] );
		} else { // or host
			$host = parse_url( $source, PHP_URL_HOST );

			// strip leading www, if any
			$title = preg_replace( '/^www\./', '', $host );
		}

		return $title;
	}

	/**
	 * Marks the post as "no webmentions sent yet"
	 *
	 * @param int $post_ID
	 */
	public static function publish_post_hook( $post_ID ) {
		// check if pingbacks are enabled
		if ( get_option( 'default_pingback_flag' ) ) {
			add_post_meta( $post_ID, '_mentionme', '1', true );
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
