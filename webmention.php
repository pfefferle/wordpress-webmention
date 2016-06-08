<?php
/**
 * Plugin Name: WebMention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: WebMention support for WordPress posts
 * Author: pfefferle
 * Author URI: http://notizblog.org/
 * Version: 2.6.0
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: webmention
 */

require_once( 'includes/class-webmention-sender.php' );
require_once( 'includes/class-webmention-receiver.php' );

// initialize admin settings
add_action( 'admin_init', array( 'WebMentionPlugin', 'admin_register_settings' ) );

/**
 * WebMention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class WebMentionPlugin {
	/**
	 * Register WebMention admin settings.
	 */
	public static function admin_register_settings() {
		register_setting( 'discussion', 'webmention_disable_selfpings_same_url' );
		register_setting( 'discussion', 'webmention_disable_selfpings_same_domain' );

		add_settings_field( 'webmention_disucssion_settings', __( 'WebMention Settings', 'webmention' ), array( 'WebMentionPlugin', 'discussion_settings' ), 'discussion', 'default' );
	}

	/**
	 * Add WebMention options to the WordPress discussion settings page.
	 */
	public static function discussion_settings () {
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
}
