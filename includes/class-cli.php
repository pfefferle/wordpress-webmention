<?php

namespace Webmention;

use WP_CLI;
use WP_CLI_Command;
use Webmention\Sender;
use function Webmention\get_plugin_meta;

/**
 * The Webmention CLI
 */
class Cli extends WP_CLI_Command {
	/**
	 * See the Plugin Meta-Informations
	 *
	 * ## OPTIONS
	 *
	 * [--Name]
	 * The Plugin Name
	 *
	 * [--PluginURI]
	 * The Plugin URI
	 *
	 * [--Version]
	 * The Plugin Version
	 *
	 * [--Description]
	 * The Plugin Description
	 *
	 * [--Author]
	 * The Plugin Author
	 *
	 * [--AuthorURI]
	 * The Plugin Author URI
	 *
	 * [--TextDomain]
	 * The Plugin Text Domain
	 *
	 * [--DomainPath]
	 * The Plugin Domain Path
	 *
	 * [--Network]
	 * The Plugin Network
	 *
	 * [--RequiresWP]
	 * The Plugin Requires at least
	 *
	 * [--RequiresPHP]
	 * The Plugin Requires PHP
	 *
	 * [--UpdateURI]
	 * The Plugin Update URI
	 *
	 * See: https://developer.wordpress.org/reference/functions/get_plugin_data/#return
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp webmention meta
	 *
	 *     $ wp webmention meta --Version
	 *     Version: 1.0.0
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function meta( $args, $assoc_args ) {
		$plugin_data = get_plugin_meta( false );

		if ( $assoc_args ) {
			$plugin_data = array_intersect_key( $plugin_data, $assoc_args );
		} else {
			WP_CLI::line( __( "Webmention Plugin Meta:\n", 'webmention' ) );
		}

		foreach ( $plugin_data as $key => $value ) {
			WP_CLI::line( $key . ':	' . $value );
		}
	}

	/**
	 * (Re)-Send Webmentions by post or by source/target
	 *
	 * ## OPTIONS
	 *
	 * <post>
	 * The post ID or the permalink
	 *
	 * [--source=<source>]
	 * The Plugin Name
	 *
	 * [--target=<target>]
	 * The Plugin URI
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp webmention send 1
	 *
	 *     $ wp webmention send http://example.com/post/1$
	 *
	 *     $ wp webmention send --source=https://example.com/post/1 --target=https://example.org/post/2
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function send( $args, $assoc_args ) {
		if ( $args ) {
			$post_id = $args[0];

			if ( true === filter_var( $post_id, FILTER_VALIDATE_URL ) ) {
				$post_id = url_to_postid( $post_id );
			}

			$post = get_post( intval( $post_id ) );

			if ( $post ) {
				$pungs = Sender::send_webmentions( $post_id );

				if ( ! $pungs ) {
					WP_CLI::error( __( 'Nothing to ping.', 'webmention' ) );
				}

				WP_CLI::line( __( 'Webmentions sent:', 'webmention' ) );

				foreach ( $pungs as $pung ) {
					WP_CLI::line( $pung );
				}
			} else {
				WP_CLI::error( __( 'Invalid post ID or permalink', 'webmention' ) );
			}
		} elseif ( ! $args && $assoc_args ) {
			$source = isset( $assoc_args['source'] ) ? esc_url_raw( $assoc_args['source'] ) : null;
			$target = isset( $assoc_args['target'] ) ? esc_url_raw( $assoc_args['target'] ) : null;

			if ( ! $source || ! $target ) {
				WP_CLI::error( __( 'Please provide a post-id/permalink or a source and a target', 'webmention' ) );
			}

			$response = Sender::send_webmention( $source, $target );

			if ( ! $response || is_wp_error( $response ) ) {
				WP_CLI::error( __( 'Nothing to ping.', 'webmention' ) );
			} else {
				WP_CLI::success( __( 'Webmention sent!', 'webmention' ) );
			}
		} else {
			WP_CLI::error( __( 'Please provide a post-id/permalink or a source and a target', 'webmention' ) );
		}
	}
}
