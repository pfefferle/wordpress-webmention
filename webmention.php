<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 4.0.3
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: webmention
 * Domain Path: /languages
 */


defined( 'WEBMENTION_ALWAYS_SHOW_HEADERS' ) || define( 'WEBMENTION_ALWAYS_SHOW_HEADERS', 0 );
defined( 'WEBMENTION_COMMENT_APPROVE' ) || define( 'WEBMENTION_COMMENT_APPROVE', 0 );
defined( 'WEBMENTION_COMMENT_TYPE' ) || define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );
defined( 'WEBMENTION_GRAVATAR_CACHE_TIME' ) || define( 'WEBMENTION_GRAVATAR_CACHE_TIME', WEEK_IN_SECONDS );

define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

defined( 'WEBMENTION_PROCESS_TYPE' ) || define( 'WEBMENTION_PROCESS_TYPE', WEBMENTION_PROCESS_TYPE_SYNC );

defined( 'WEBMENTION_VOUCH' ) || define( 'WEBMENTION_VOUCH', false );

// initialize admin settings.
require_once dirname( __FILE__ ) . '/includes/class-webmention-admin.php';
add_action( 'admin_init', array( 'Webmention_Admin', 'init' ) );
add_action( 'admin_menu', array( 'Webmention_Admin', 'admin_menu' ) );

/**
 * Initialize Webmention Plugin
 */
function webmention_init() {
	// Add support for webmentions to custom post types.
	$post_types = get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) ? get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) : array();

	foreach ( $post_types as $post_type ) {
		add_post_type_support( $post_type, 'webmentions' );
	}
	if ( WP_DEBUG ) {
		require_once dirname( __FILE__ ) . '/includes/debug.php';
	}

	// Request Handler.
	require_once dirname( __FILE__ ) . '/includes/class-webmention-request.php';

	// Comment Type Class
	require_once dirname( __FILE__ ) . '/includes/class-webmention-comment-type.php';

	// list of various public helper functions.
	require_once dirname( __FILE__ ) . '/includes/functions.php';

	// load local avatar support.
	require_once dirname( __FILE__ ) . '/includes/class-webmention-avatar-handler.php';
	add_action( 'init', array( 'Webmention_Avatar_Handler', 'init' ) );

	// load HTTP 410 support.
	require_once dirname( __FILE__ ) . '/includes/class-webmention-410.php';
	add_action( 'init', array( 'Webmention_410', 'init' ) );

	// initialize Webmention Sender.
	require_once dirname( __FILE__ ) . '/includes/class-webmention-sender.php';
	add_action( 'init', array( 'Webmention_Sender', 'init' ) );

	// initialize Webmention Receiver.
	require_once dirname( __FILE__ ) . '/includes/class-webmention-receiver.php';
	add_action( 'init', array( 'Webmention_Receiver', 'init' ) );

	// initialize Webmention Notifications
	require_once dirname( __FILE__ ) . '/includes/class-webmention-notifications.php';
	add_action( 'init', array( 'Webmention_Notifications', 'init' ) );

	// initialize Webmention Vouch
	if ( WEBMENTION_VOUCH ) {
		require_once dirname( __FILE__ ) . '/includes/class-webmention-vouch.php';
		add_action( 'init', array( 'Webmention_Vouch', 'init' ) );
	}

	// Default Comment Status.
	add_filter( 'get_default_comment_status', 'webmention_get_default_comment_status', 11, 3 );
	add_filter( 'pings_open', 'webmention_pings_open', 10, 2 );

	// Load language files.
	webmention_plugin_textdomain();

	add_action( 'comment_form_after', 'webmention_comment_form', 11 );
	add_action( 'comment_form_comments_closed', 'webmention_comment_form' );

	add_filter( 'nodeinfo_data', 'webmention_nodeinfo', 10, 2 );
	add_filter( 'nodeinfo2_data', 'webmention_nodeinfo2', 10 );

	// remove old Webmention code.
	remove_action( 'init', array( 'WebMentionFormPlugin', 'init' ) );
	remove_action( 'init', array( 'WebMentionForCommentsPlugin', 'init' ) );

}
add_action( 'plugins_loaded', 'webmention_init' );

/**
 * Retrieve the default comment status for a given post type.
 *
 * @since 3.8.9
 *
 * @param string $status       Default status for the given post type,
 *                             either 'open' or 'closed'.
 * @param string $post_type    Post type to check.
 * @param string $comment_type Type of comment. Default is `comment`.
 *
 * @return string
 */
function webmention_get_default_comment_status( $status, $post_type, $comment_type ) {
	if ( 'webmention' === $comment_type ) {
		return post_type_supports( $post_type, 'webmentions' ) ? 'open' : 'closed';
	}
	// Since support for the pingback comment type is used to keep pings open...
	if ( ( 'pingback' === $comment_type ) ) {
		return ( post_type_supports( $post_type, 'webmentions' ) ? 'open' : $status );
	}

	return $status;
}

/**
 * Render the webmention comment form.
 *
 * Can be filtered to load a custom template of your choosing.
 *
 * @since 3.8.9
 */
function webmention_comment_form() {
	$template = apply_filters( 'webmention_comment_form', plugin_dir_path( __FILE__ ) . 'templates/webmention-comment-form.php' );

	if ( ( 1 === (int) get_option( 'webmention_show_comment_form', 1 ) ) && pings_open() ) {
		load_template( $template );
	}
}

/**
 * Return enabled status of Homepage Webmentions.
 *
 * @since 3.8.9
 *
 * @param bool $open    Whether the current post is open for pings.
 * @param int  $post_id The post ID.
 * @return boolean if pings are open
 */
function webmention_pings_open( $open, $post_id ) {
	if ( get_option( 'webmention_home_mentions' ) === $post_id ) {
		return true;
	}

	return $open;
}

/**
 * Load language files.
 *
 * @since 3.8.9
 */
function webmention_plugin_textdomain() {
	load_plugin_textdomain( 'webmention', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Extend NodeInfo data.
 *
 * @since 3.8.9
 *
 * @param array $nodeinfo NodeInfo data.
 * @param array $version  Updated data.
 * @return array
 */
function webmention_nodeinfo( $nodeinfo, $version ) {
	if ( '2.0' === $version ) {
		$nodeinfo['protocols'][] = 'webmention';
	} else {
		$nodeinfo['protocols']['inbound'][]  = 'webmention';
		$nodeinfo['protocols']['outbound'][] = 'webmention';
	}

	return $nodeinfo;
}

/**
 * Extend NodeInfo2 data.
 *
 * @since 3.8.9
 *
 * @param array $nodeinfo NodeInfo2 data.
 * @return array
 */
function webmention_nodeinfo2( $nodeinfo ) {
	$nodeinfo['protocols'][] = 'webmention';

	return $nodeinfo;
}
