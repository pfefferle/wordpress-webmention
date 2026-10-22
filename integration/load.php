<?php
/**
 * Load the Webmention integrations.
 *
 * @package Webmention
 */

namespace Webmention\Integration;

use Webmention\Autoloader;

Autoloader::register_path( __NAMESPACE__, __DIR__ );

/**
 * Initialize the Webmention integrations.
 *
 * @return void
 */
function plugin_init() {
	/**
	 * Adds ActivityPub support.
	 *
	 * This class hands Webmention reactions over to the reactions block
	 * of the ActivityPub plugin.
	 *
	 * @see https://wordpress.org/plugins/activitypub/
	 */
	if ( \defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ) {
		Activitypub::init();
	}
}
\add_action( 'plugins_loaded', __NAMESPACE__ . '\plugin_init' );
