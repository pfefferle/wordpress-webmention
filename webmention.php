<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 5.6.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: webmention
 * Domain Path: /languages
 */

namespace Webmention;

\define( 'WEBMENTION_VERSION', '5.6.0' );

\define( 'WEBMENTION_PLUGIN_DIR', \plugin_dir_path( __FILE__ ) );
\define( 'WEBMENTION_PLUGIN_BASENAME', \plugin_basename( __FILE__ ) );
\define( 'WEBMENTION_PLUGIN_FILE', \plugin_dir_path( __FILE__ ) . '/' . \basename( __FILE__ ) );
\define( 'WEBMENTION_PLUGIN_URL', \plugin_dir_url( __FILE__ ) );

require_once WEBMENTION_PLUGIN_DIR . 'includes/class-autoloader.php';
require_once WEBMENTION_PLUGIN_DIR . 'includes/functions.php';

if ( \WP_DEBUG ) {
	require_once WEBMENTION_PLUGIN_DIR . 'includes/debug.php';
}

// Register the autoloader.
Autoloader::register_path( __NAMESPACE__, WEBMENTION_PLUGIN_DIR . 'includes' );

// Initialize the plugin.
$webmention = Webmention::get_instance();
$webmention->init();

/**
 * Plugin Version Number used for caching.
 */
function version() {
	return Webmention::get_instance()->get_version();
}

/**
 * Activation Hook
 *
 * Migrate DB if needed
 */
function activation() {
	\Webmention\Upgrade::maybe_upgrade();
}
register_activation_hook( __FILE__, '\Webmention\activation' );

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
if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
	\WP_CLI::add_command( 'webmention', Cli::class );
}
