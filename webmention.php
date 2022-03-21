<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 5.X
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: webmention
 * Domain Path: /languages
 */

namespace Webmention;

use WP_CLI;

defined( 'WEBMENTION_ALWAYS_SHOW_HEADERS' ) || define( 'WEBMENTION_ALWAYS_SHOW_HEADERS', 0 );
defined( 'WEBMENTION_COMMENT_APPROVE' ) || define( 'WEBMENTION_COMMENT_APPROVE', 0 );
defined( 'WEBMENTION_COMMENT_TYPE' ) || define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );
defined( 'WEBMENTION_GRAVATAR_CACHE_TIME' ) || define( 'WEBMENTION_GRAVATAR_CACHE_TIME', WEEK_IN_SECONDS );

define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

defined( 'WEBMENTION_PROCESS_TYPE' ) || define( 'WEBMENTION_PROCESS_TYPE', WEBMENTION_PROCESS_TYPE_SYNC );

defined( 'WEBMENTION_VOUCH' ) || define( 'WEBMENTION_VOUCH', false );

// Mentions with content less than this length will be rendered in full.
defined( 'MAX_INLINE_MENTION_LENGTH' ) || define( 'MAX_INLINE_MENTION_LENGTH', 300 );

// initialize admin settings.
require_once dirname( __FILE__ ) . '/includes/class-admin.php';
add_action( 'admin_init', array( '\Webmention\Admin', 'admin_init' ) );
add_action( 'admin_menu', array( '\Webmention\Admin', 'admin_menu' ) );

/**
 * Initialize Webmention Plugin
 */
function init() {
	// Add support for webmentions to custom post types.
	$post_types = get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) ? get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) : array();

	foreach ( $post_types as $post_type ) {
		add_post_type_support( $post_type, 'webmentions' );
	}
	if ( WP_DEBUG ) {
		require_once dirname( __FILE__ ) . '/includes/debug.php';
	}

	require_once dirname( __FILE__ ) . '/includes/class-tools.php';
	add_action( 'admin_menu', array( '\Webmention\Tools', 'admin_menu' ) );
	add_action( 'init', array( '\Webmention\Tools', 'init' ) );

	// Request Handler.
	require_once dirname( __FILE__ ) . '/includes/class-request.php';
	require_once dirname( __FILE__ ) . '/includes/class-response.php';

	// Comment Handler Classes.
	require_once dirname( __FILE__ ) . '/includes/class-comment-type.php';
	require_once dirname( __FILE__ ) . '/includes/class-comment.php';
	add_action( 'init', array( '\Webmention\Comment', 'init' ) );

	// Handler Control Class.
	require_once dirname( __FILE__ ) . '/includes/class-handler.php';
	require_once dirname( __FILE__ ) . '/includes/Handler/class-base.php';

	// Webmention Item Class
	require_once dirname( __FILE__ ) . '/includes/Entity/class-item.php';

	// list of various public helper functions.
	require_once dirname( __FILE__ ) . '/includes/functions.php';

	// load local avatar support.
	require_once dirname( __FILE__ ) . '/includes/class-avatar.php';
	add_action( 'init', array( '\Webmention\Avatar', 'init' ) );

	// load HTTP 410 support.
	require_once dirname( __FILE__ ) . '/includes/class-http-gone.php';
	add_action( 'init', array( '\Webmention\HTTP_Gone', 'init' ) );

	// initialize Webmention Sender.
	require_once dirname( __FILE__ ) . '/includes/class-sender.php';
	add_action( 'init', array( '\Webmention\Sender', 'init' ) );

	// initialize Webmention Receiver.
	require_once dirname( __FILE__ ) . '/includes/class-receiver.php';
	add_action( 'init', array( '\Webmention\Receiver', 'init' ) );

	// initialize Webmention Discovery.
	require_once dirname( __FILE__ ) . '/includes/class-discovery.php';
	add_action( 'init', array( '\Webmention\Discovery', 'init' ) );

	// initialize Webmention Vouch
	if ( WEBMENTION_VOUCH ) {
		require_once dirname( __FILE__ ) . '/includes/class-vouch.php';
		add_action( 'init', array( '\Webmention\Vouch', 'init' ) );
	}

	// Default Comment Status.
	add_filter( 'get_default_comment_status', 'webmention_get_default_comment_status', 11, 3 );
	add_filter( 'pings_open', 'webmention_pings_open', 10, 2 );

	// Load language files.
	load_plugin_textdomain( 'webmention', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	add_action( 'comment_form_after', 'webmention_comment_form', 11 );
	add_action( 'comment_form_comments_closed', 'webmention_comment_form' );

	// remove old Webmention code.
	remove_action( 'init', array( '\WebMentionFormPlugin', 'init' ) );
	remove_action( 'init', array( '\WebMentionForCommentsPlugin', 'init' ) );

}
add_action( 'plugins_loaded', '\Webmention\init' );

/**
 * `get_plugin_data` wrapper
 *
 * @return array the plugin metadata array
 */
function get_plugin_meta( $markup = true, $translate = true ) {
	return get_plugin_data( __FILE__, $markup, $translate );
}

// Check for CLI env, to add the CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/includes/class-cli.php';
	WP_CLI::add_command( 'webmention', '\Webmention\Cli' );
}
