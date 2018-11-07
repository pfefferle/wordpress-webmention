<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 3.8.6
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: webmention
 * Domain Path: /languages
 */

defined( 'WEBMENTION_COMMENT_APPROVE' ) || define( 'WEBMENTION_COMMENT_APPROVE', 0 );
defined( 'WEBMENTION_COMMENT_TYPE' ) || define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );

define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

defined( 'WEBMENTION_PROCESS_TYPE' ) || define( 'WEBMENTION_PROCESS_TYPE', WEBMENTION_PROCESS_TYPE_SYNC );

defined( 'WEBMENTION_VOUCH' ) || define( 'WEBMENTION_VOUCH', false );

add_action( 'plugins_loaded', array( 'Webmention_Plugin', 'init' ) );

// initialize admin settings
require_once dirname( __FILE__ ) . '/includes/class-webmention-admin.php';

/**
 * Webmention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class Webmention_Plugin {

	/**
	 * Initialize Webmention Plugin
	 */
	public static function init() {
		// Add a new feature type to posts for webmentions
		add_post_type_support( 'post', 'webmentions' );
		if ( 1 === (int) get_option( 'webmention_support_pages' ) ) {
			add_post_type_support( 'page', 'webmentions' );
		}
		if ( WP_DEBUG ) {
			require_once dirname( __FILE__ ) . '/includes/debug.php';
		}

		// list of various public helper functions
		require_once dirname( __FILE__ ) . '/includes/functions.php';

		// load HTTP 410 support
		require_once dirname( __FILE__ ) . '/includes/class-webmention-410.php';
		add_action( 'init', array( 'Webmention_410', 'init' ) );

		// initialize Webmention Sender
		require_once dirname( __FILE__ ) . '/includes/class-webmention-sender.php';
		add_action( 'init', array( 'Webmention_Sender', 'init' ) );

		// initialize Webmention Receiver
		require_once dirname( __FILE__ ) . '/includes/class-webmention-receiver.php';
		add_action( 'init', array( 'Webmention_Receiver', 'init' ) );

		// initialize Webmention Vouch
		if ( WEBMENTION_VOUCH ) {
			require_once dirname( __FILE__ ) . '/includes/class-webmention-vouch.php';
			add_action( 'init', array( 'Webmention_Vouch', 'init' ) );
		}

		// Default Comment Status
		add_filter( 'get_default_comment_status', array( 'Webmention_Plugin', 'get_default_comment_status' ), 11, 3 );
		add_filter( 'pings_open', array( 'Webmention_Plugin', 'pings_open' ), 10, 2 );

		// Load language files
		self::plugin_textdomain();

		add_action( 'comment_form_after', array( 'Webmention_Plugin', 'comment_form' ), 11 );

		// remove old Webmention code
		remove_action( 'init', array( 'WebMentionFormPlugin', 'init' ) );
		remove_action( 'init', array( 'WebMentionForCommentsPlugin', 'init' ) );
	}

	public static function get_default_comment_status( $status, $post_type, $comment_type ) {
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
	 * render the comment form
	 */
	public static function comment_form() {
		$template = apply_filters( 'webmention_comment_form', plugin_dir_path( __FILE__ ) . 'templates/webmention-comment-form.php' );

		if ( 1 === (int) get_option( 'webmention_show_comment_form', 1 ) ) {
			load_template( $template );
		}
	}

	/**
	 * Return true if page is enabled for Homepage Webmentions
	 *
	 * @param bool $open    Whether the current post is open for pings.
	 * @param int  $post_id The post ID.
	 *
	 * @return boolean if pings are open
	 */
	public static function pings_open( $open, $post_id ) {
		if ( get_option( 'webmention_home_mentions' ) === $post_id ) {
			return true;
		}

		return $open;
	}

	/**
	 * Load language files
	 */
	public static function plugin_textdomain() {
		load_plugin_textdomain( 'webmention', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}
