<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-Webmention
 * Description: Webmention support for WordPress posts
 * Author: pfefferle
 * Author URI: http://notizblog.org/
 * Version: 3.0.0
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: Webmention
 */

// Webmention Receiver
require_once plugin_dir_path( __FILE__ ) . 'class-webmention-receiver.php' ;
// Webmention Sender
require_once plugin_dir_path( __FILE__ ) . 'class-webmention-sender.php';


// admin settings
add_action( 'admin_init', array( 'WebmentionPlugin', 'admin_register_settings' ) );
add_filter( 'webmention_disable_selfpings', array( 'WebmentionPlugin', 'selfping_option' ) );


class WebmentionPlugin {


	/**
	* Register Webmention admin settings.
	*/
	public static function admin_register_settings() {
		register_setting( 'discussion', 'webmention_disable_selfpings_same_url' );
		register_setting( 'discussion', 'webmention_disable_selfpings_same_domain' );

		add_settings_field( 'webmention_discussion_settings', __( 'Webmention Settings', 'Webmention' ), array( 'WebmentionPlugin', 'discussion_settings' ), 'discussion', 'default' );
	}

	/**
	* Add Webmention options to the WordPress discussion settings page.
	*/
	public static function discussion_settings () {
	?>
	<fieldset>
	<label for="webmention_disable_selfpings_same_url">
	  <input type="checkbox" name="webmention_disable_selfpings_same_url" id="webmention_disable_selfpings_same_url" value="1" <?php
		echo checked( true, get_option( 'webmention_disable_selfpings_same_url' ) );  ?> />
		<?php _e( 'Disable self-pings on the same URL <small>(for example "http://example.com/?p=123")</small>', 'Webmention' ) ?>
	  </label>

	  <br />

	  <label for="webmention_disable_selfpings_same_domain">
		<input type="checkbox" name="webmention_disable_selfpings_same_domain" id="webmention_disable_selfpings_same_domain" value="1" <?php
		echo checked( true, get_option( 'webmention_disable_selfpings_same_domain' ) );  ?> />
		<?php _e( 'Disable self-pings on the same Domain <small>(for example "example.com")</small>', 'Webmention' ) ?>
	  </label>
	</fieldset>
	<?php
	}

	public static function selfping_option( $disable ) {
		return get_option( 'webmention_disable_selfpings_same_domain', 0 );
	}

};
