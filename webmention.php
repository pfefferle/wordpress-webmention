<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 5.2.6
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


defined( 'WEBMENTION_LOCAL_AVATAR_STORE' ) || define( 'WEBMENTION_LOCAL_AVATAR_STORE', false );
defined( 'WEBMENTION_AVATAR_QUALITY' ) || define( 'WEBMENTION_AVATAR_QUALITY', null );
defined( 'WEBMENTION_AVATAR_SIZE' ) || define( 'WEBMENTION_AVATAR_SIZE', 256 );

define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

defined( 'WEBMENTION_PROCESS_TYPE' ) || define( 'WEBMENTION_PROCESS_TYPE', WEBMENTION_PROCESS_TYPE_SYNC );

defined( 'WEBMENTION_VOUCH' ) || define( 'WEBMENTION_VOUCH', false );

// Mentions with content less than this length will be rendered in full.
defined( 'MAX_INLINE_MENTION_LENGTH' ) || define( 'MAX_INLINE_MENTION_LENGTH', 300 );

\define( 'WEBMENTION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
\define( 'WEBMENTION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
\define( 'WEBMENTION_PLUGIN_FILE', plugin_dir_path( __FILE__ ) . '/' . basename( __FILE__ ) );
\define( 'WEBMENTION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// initialize admin settings.
require_once __DIR__ . '/includes/class-admin.php';
add_action( 'admin_init', array( __NAMESPACE__ . '\Admin', 'admin_init' ) );
add_action( 'admin_menu', array( __NAMESPACE__ . '\Admin', 'admin_menu' ) );

/**
 * Plugin Version Number used for caching.
 */
function version() {
	$meta = get_plugin_meta( array( 'Version' => 'Version' ) );

	return $meta['Version'];
}

/**
 * Initialize Webmention Plugin
 */
function init() {
	// Add support for Webmentions to custom post types.
	$post_types = get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) ? get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) : array();

	foreach ( $post_types as $post_type ) {
		add_post_type_support( $post_type, 'webmentions' );
	}
	if ( WP_DEBUG ) {
		require_once __DIR__ . '/includes/debug.php';
	}

	require_once __DIR__ . '/includes/class-tools.php';
	add_action( 'init', array( __NAMESPACE__ . '\Tools', 'init' ) );

	// Request Handler.
	require_once __DIR__ . '/includes/class-request.php';
	require_once __DIR__ . '/includes/class-response.php';

	// Comment Handler Classes.
	require_once __DIR__ . '/includes/class-comment-type.php';
	require_once __DIR__ . '/includes/class-comment.php';
	add_action( 'init', array( __NAMESPACE__ . '\Comment', 'init' ) );

	require_once __DIR__ . '/includes/class-comment-walker.php';
	add_action( 'init', array( __NAMESPACE__ . '\Comment_Walker', 'init' ) );

	// Handler Control Class.
	require_once __DIR__ . '/includes/class-handler.php';
	require_once __DIR__ . '/includes/Handler/class-base.php';

	// Webmention Item Class
	require_once __DIR__ . '/includes/Entity/class-item.php';

	// list of various public helper functions.
	require_once __DIR__ . '/includes/functions.php';

	// load local avatar support.
	require_once __DIR__ . '/includes/class-avatar.php';
	add_action( 'init', array( __NAMESPACE__ . '\Avatar', 'init' ) );

	// load HTTP 410 support.
	require_once __DIR__ . '/includes/class-http-gone.php';
	add_action( 'init', array( __NAMESPACE__ . '\HTTP_Gone', 'init' ) );

	// initialize Webmention Sender.
	require_once __DIR__ . '/includes/class-sender.php';
	add_action( 'init', array( __NAMESPACE__ . '\Sender', 'init' ) );

	// initialize Webmention Receiver.
	require_once __DIR__ . '/includes/class-receiver.php';
	add_action( 'init', array( __NAMESPACE__ . '\Receiver', 'init' ) );

	// initialize Webmention Discovery.
	require_once __DIR__ . '/includes/class-discovery.php';
	add_action( 'init', array( __NAMESPACE__ . '\Discovery', 'init' ) );

	// load local avatar store.
	if ( WEBMENTION_LOCAL_AVATAR_STORE ) {
		require_once __DIR__ . '/includes/class-avatar-store.php';
		add_action( 'init', array( __NAMESPACE__ . '\Avatar_Store', 'init' ) );
	}

	// initialize Webmention Vouch
	if ( WEBMENTION_VOUCH ) {
		require_once __DIR__ . '/includes/class-vouch.php';
		add_action( 'init', array( __NAMESPACE__ . '\Vouch', 'init' ) );
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

	// remove old Semantic Linkbacks code
	remove_action( 'plugins_loaded', array( 'Semantic_Linkbacks_Plugin', 'init' ), 11 );
	remove_action( 'admin_init', array( 'Semantic_Linkbacks_Plugin', 'admin_init' ) );

	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Activation Hook
 *
 * Migrate DB if needed
 */
function activation() {
	require_once __DIR__ . '/includes/class-db.php';
	\Webmention\DB::update_database();

	\Webmention\remove_semantic_linkbacks();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activation' );

/**
 * Add CSS and JavaScript
 */
function enqueue_scripts() {
	if ( \is_singular() && \comments_open() ) {
		wp_enqueue_style( 'webmention', plugin_dir_url( __FILE__ ) . 'assets/css/webmention.css', array(), version() );
	}
}

/**
 * `get_plugin_data` wrapper
 *
 * @return array the plugin metadata array
 */
function get_plugin_meta( $default_headers = array() ) {
	if ( ! $default_headers ) {
		$default_headers = array(
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
			'UpdateURI'   => 'Update URI',
		);
	}

	return \get_file_data( __FILE__, $default_headers, 'plugin' );
}

// Check for CLI env, to add the CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-cli.php';
	WP_CLI::add_command( 'webmention', __NAMESPACE__ . '\Cli' );
}

/**
 * Remove the Semantic Linkbacks plugin
 *
 * @since 5.0.0
 *
 * @return void
 */
function remove_semantic_linkbacks() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$plugin_slug       = 'semantic-linkbacks/semantic-linkbacks.php';
	$installed_plugins = get_plugins();

	if ( array_key_exists( $plugin_slug, $installed_plugins ) ) {
		\deactivate_plugins( array( $plugin_slug ), true );
		\delete_plugins( array( $plugin_slug ) );
	}
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function reply_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', __NAMESPACE__ . '\reply_block_init' );

/**
 * Enqueue Editor assets.
 */
function enqueue_editor_assets() {
	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

	wp_enqueue_script(
		'reply-block-editor',
		plugins_url( 'build/index.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version']
	);
}
//add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets' );
