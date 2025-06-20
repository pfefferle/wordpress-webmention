<?php

namespace Webmention;

class Block {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Add editor plugin.
		\add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		\add_action( 'init', array( self::class, 'register_postmeta' ), 11 );
	}

	/**
	 * Register post meta
	 */
	public static function register_postmeta() {
		$post_types = \get_post_types_by_support( 'webmentions' );
		foreach ( $post_types as $post_type ) {
			\register_post_meta(
				$post_type,
				'webmentions_disabled',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'boolean',
				)
			);
			\register_post_meta(
				$post_type,
				'webmentions_send_disabled',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'boolean',
				)
			);
		}
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
		$asset_data = include WEBMENTION_PLUGIN_DIR . 'build/editor-plugin/plugin.asset.php';
		$plugin_url = plugins_url( 'build/editor-plugin/plugin.js', WEBMENTION_PLUGIN_FILE );
		wp_enqueue_script( 'webmention-block-editor', $plugin_url, $asset_data['dependencies'], $asset_data['version'], true );
	}
}
