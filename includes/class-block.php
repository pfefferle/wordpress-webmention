<?php

namespace Webmention;

class Block {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Add editor plugin.
		\add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );

		// Add RSVP styles inside editor iframe.
		\add_action( 'enqueue_block_assets', array( self::class, 'enqueue_block_assets' ) );
	}

	/**
	 * Enqueue the block editor assets.
	 */
	public static function enqueue_editor_assets() {
		// Check for our supported post types.
		$current_screen = \get_current_screen();
		$ap_post_types  = \get_post_types_by_support( 'webmentions' );
		if ( ! $current_screen || ! in_array( $current_screen->post_type, $ap_post_types, true ) ) {
			return;
		}

		// Enqueue the main editor plugin.
		$asset_data = include WEBMENTION_PLUGIN_DIR . 'build/editor-plugin/plugin.asset.php';
		$plugin_url = plugins_url( 'build/editor-plugin/plugin.js', WEBMENTION_PLUGIN_FILE );
		wp_enqueue_script( 'webmention-block-editor', $plugin_url, $asset_data['dependencies'], $asset_data['version'], true );

		// Enqueue the reaction links extension.
		self::enqueue_reaction_links_assets();

		// Enqueue the RSVP format.
		self::enqueue_rsvp_assets();
	}

	/**
	 * Enqueue the reaction links extension assets.
	 */
	public static function enqueue_reaction_links_assets() {
		$asset_file = WEBMENTION_PLUGIN_DIR . 'build/reaction-links/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset_data = include $asset_file;

		wp_enqueue_script(
			'webmention-reaction-links',
			plugins_url( 'build/reaction-links/index.js', WEBMENTION_PLUGIN_FILE ),
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		wp_enqueue_style(
			'webmention-reaction-links',
			plugins_url( 'build/reaction-links/index.css', WEBMENTION_PLUGIN_FILE ),
			array(),
			$asset_data['version']
		);
	}

	/**
	 * Enqueue the RSVP format assets.
	 */
	public static function enqueue_rsvp_assets() {
		$asset_file = WEBMENTION_PLUGIN_DIR . 'build/rsvp/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset_data = include $asset_file;

		wp_enqueue_script(
			'webmention-rsvp',
			plugins_url( 'build/rsvp/index.js', WEBMENTION_PLUGIN_FILE ),
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		wp_enqueue_style(
			'webmention-rsvp',
			plugins_url( 'build/rsvp/index.css', WEBMENTION_PLUGIN_FILE ),
			array(),
			$asset_data['version']
		);
	}

	/**
	 * Enqueue the front end stylesheet inside the block editor iframe.
	 *
	 * `data.p-rsvp` markup lives in the post content, so it is styled by the
	 * front end stylesheet rather than by an editor specific one. Loading that
	 * same stylesheet inside the iframe keeps the editor in sync with the
	 * published output and keeps the front end down to a single, dequeueable
	 * `webmention` stylesheet.
	 */
	public static function enqueue_block_assets() {
		// The front end enqueues this stylesheet itself, see Webmention::enqueue_scripts().
		if ( ! is_admin() ) {
			return;
		}

		$current_screen = \get_current_screen();
		$post_types     = \get_post_types_by_support( 'webmentions' );

		if ( ! $current_screen || ! in_array( $current_screen->post_type, $post_types, true ) ) {
			return;
		}

		wp_enqueue_style(
			'webmention',
			WEBMENTION_PLUGIN_URL . 'assets/css/webmention.css',
			array(),
			version()
		);
	}
}
