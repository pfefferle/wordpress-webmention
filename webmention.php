<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: pfefferle
 * Author URI: http://notiz.blog/
 * Version: 3.0.0
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: webmention
 * Domain Path: /languages
 */

defined( 'WEBMENTION_COMMENT_APPROVE' ) || define( 'WEBMENTION_COMMENT_APPROVE', 0 );
defined( 'WEBMENTION_COMMENT_TYPE' ) || define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );
defined( 'WEBMENTION_PROCESS_TYPE' ) || define( 'WEBMENTION_PROCESS_TYPE', 'async' );

define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

add_action( 'plugins_loaded', array( 'Webmention_Plugin', 'init' ) );

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
		if ( WP_DEBUG ) {
			require_once( dirname( __FILE__ ) . '/includes/debug.php' );
		}

		// will be removed in one of the following major releases
		require_once( dirname( __FILE__ ) . '/includes/deprecations.php' );

		// list of various public helper functions
		require_once( dirname( __FILE__ ) . '/includes/functions.php' );

		// initialize Webmention Sender
		require_once( dirname( __FILE__ ) . '/includes/class-webmention-sender.php' );
		add_action( 'init', array( 'Webmention_Sender', 'init' ) );

		// initialize Webmention Receiver
		require_once( dirname( __FILE__ ) . '/includes/class-webmention-receiver.php' );
		add_action( 'init', array( 'Webmention_Receiver', 'init' ) );

		// initialize admin settings
		add_action( 'admin_init', array( 'Webmention_Plugin', 'admin_register_settings' ) );

		add_action( 'admin_comment_types_dropdown', array( 'Webmention_Plugin', 'comment_types_dropdown' ) );
		add_action( 'comment_form_after', array( 'Webmention_Plugin', 'comment_form' ), 11 );
	}

	/**
	 * Register Webmention admin settings.
	 */
	public static function admin_register_settings() {
		register_setting( 'discussion', 'webmention_disable_selfpings_same_url', array(
			'type' => 'boolean',
			'description' => __( 'Disable Self Webmentions on the Same URL', 'webmention' ),
			'show_in_rest' => true,
			'default' => 1,
		) );
		register_setting( 'discussion', 'webmention_disable_selfpings_same_domain', array(
			'type' => 'boolean',
			'description' => __( 'Disable Self Webmentions on the Same Domain', 'webmention' ),
			'show_in_rest' => true,
			'default' => 0,
		) );
		register_setting( 'discussion', 'webmention_show_comment_form', array(
			'type' => 'boolean',
			'description' => __( 'Show Webmention Comment Form', 'webmention' ),
			'show_in_rest' => true,
			'default' => 1,
		) );

		add_settings_field( 'webmention_discussion_settings', __( 'Webmention Settings', 'webmention' ), array( 'Webmention_Plugin', 'discussion_settings' ), 'discussion', 'default' );
	}

	/**
	 * Add Webmention options to the WordPress discussion settings page.
	 */
	public static function discussion_settings() {
		load_template( plugin_dir_path( __FILE__ ) . 'templates/webmention-discussion-settings.php' );
	}

	/**
	 * render the comment form
	 */
	public static function comment_form() {
		if ( get_option( 'webmention_show_comment_form' ) === '1' ) {
			load_template( plugin_dir_path( __FILE__ ) . 'templates/webmention-comment-form.php' );
		}
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
}
