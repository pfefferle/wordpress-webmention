<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: pfefferle
 * Author URI: http://notizblog.org/
 * Version: 3.0.0
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: webmention
 */

defined( 'WEBMENTION_COMMENT_APPROVE' ) || define( 'WEBMENTION_COMMENT_APPROVE', 0 );
defined( 'WEBMENTION_COMMENT_TYPE' ) || define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );

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
	}

	/**
	 * Register Webmention admin settings.
	 */
	public static function admin_register_settings() {
		register_setting( 'discussion', 'webmention_disable_selfpings_same_url' );
		register_setting( 'discussion', 'webmention_disable_selfpings_same_domain' );

		add_settings_field( 'webmention_disucssion_settings', __( 'Webmention Settings', 'webmention' ), array( 'Webmention_Plugin', 'discussion_settings' ), 'discussion', 'default' );
	}

	/**
	 * Add Webmention options to the WordPress discussion settings page.
	 */
	public static function discussion_settings() {
?>
	<fieldset>
		<label for="webmention_disable_selfpings_same_url">
			<input type="checkbox" name="webmention_disable_selfpings_same_url" id="webmention_disable_selfpings_same_url" value="1" <?php
				echo checked( true, get_option( 'webmention_disable_selfpings_same_url' ) );  ?> />
			<?php _e( 'Disable self-pings on the same URL <small>(for example "http://example.com/?p=123")</small>', 'webmention' ) ?>
		</label>

		<br />

		<label for="webmention_disable_selfpings_same_domain">
			<input type="checkbox" name="webmention_disable_selfpings_same_domain" id="webmention_disable_selfpings_same_domain" value="1" <?php
				echo checked( true, get_option( 'webmention_disable_selfpings_same_domain' ) );  ?> />
			<?php _e( 'Disable self-pings on the same Domain <small>(for example "example.com")</small>', 'webmention' ) ?>
		</label>
	</fieldset>
<?php
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
