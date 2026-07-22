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
	}

	/**
	 * Enqueue block assets for the RSVP format.
	 *
	 * The RSVP styles need to load both inside the editor iframe (to style the
	 * `data.p-rsvp` elements while editing) and on the front end (to style the
	 * same elements in published content). On the front end they are always
	 * enqueued; in the admin editor they are limited to post types that support
	 * Webmentions, matching the gate applied to the RSVP script.
	 */
	public static function enqueue_block_assets() {
		if ( is_admin() ) {
			$current_screen = \get_current_screen();
			$post_types     = \get_post_types_by_support( 'webmentions' );

			if ( ! $current_screen || ! in_array( $current_screen->post_type, $post_types, true ) ) {
				return;
			}
		}

		$asset_file = WEBMENTION_PLUGIN_DIR . 'build/rsvp/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset_data = include $asset_file;

		wp_enqueue_style(
			'webmention-rsvp',
			plugins_url( 'build/rsvp/index.css', WEBMENTION_PLUGIN_FILE ),
			array(),
			$asset_data['version']
		);
	}
}
