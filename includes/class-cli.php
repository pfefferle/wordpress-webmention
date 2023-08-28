<?php

namespace Webmention;

use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;
use Webmention\DB;
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
		$plugin_data = get_plugin_meta();

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
	 * Refresh Webmentions by post or by comment_id
	 *
	 * ## OPTIONS
	 *
	 * [--comment=<comment_id>]
	 * : The comment ID
	 *
	 * [--post=<post>]
	 * : Instead of just a comment ID, support using a post ID or post URL
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp webmention refresh 1
	 *
	 *     $ wp webmention refresh --post=http://example.com/post/1
	 *
	 *     $ wp webmention refresh --post=1
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function refresh( $args, $assoc_args ) {
		if ( $assoc_args ) {
			$comment_id = isset( $assoc_args['comment'] ) ? $assoc_args['comment'] : null;
			if ( $comment_id ) {
				$return = webmention_refresh( $comment_id );
				if ( ! is_wp_error( $return ) ) {
					WP_CLI::success( __( 'Webmention refreshed!', 'webmention' ) );
				} else {
					WP_CLI::error( $return->get_error_message() );
				}
			}
			$post = isset( $assoc_args['post'] ) ? $assoc_args['post'] : null;
			if ( $post ) {
				if ( true === filter_var( $post, FILTER_VALIDATE_URL ) ) {
					$post = url_to_postid( $post );
				}
				$post     = get_post( intval( $post ) );
				$comments = get_comments(
					array(
						'post_id' => $post->ID,
						'fields'  => 'ids',
					)
				);
				foreach ( $comments as $comment_id ) {
					$return = webmention_refresh( $comment_id );
					if ( is_wp_error( $return ) ) {
						WP_CLI::error( __( 'Failure: ', 'webmention' ) . $comment_id . ' ' . $return->get_error_message() );
					} elseif ( $return ) {
						WP_CLI::line( __( 'Success: ', 'webmention' ) . $comment_id );
					} else {
						WP_CLI::error( __( 'Failure: ', 'webmention' ) . $comment_id );
					}
				}
			}
		} else {
			WP_CLI::error( __( 'Please provide a comment ID or a post-id/permalink', 'webmention' ) );
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
	 * : The Plugin Name
	 *
	 * [--target=<target>]
	 * : The Plugin URI
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

	/**
	 * Generates some number of new dummy Webmentions.
	 *
	 * Creates a specified number of new Webmentions with dummy data.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many Webmentions to generate?
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--post_id=<post-id>]
	 * : Assign Webmentions to a specific post.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: progress
	 * options:
	 *   - progress
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate comments for the given post.
	 *     $ wp comment generate --format=ids --count=3 --post_id=123
	 *     138 139 140
	 */
	public function generate( $args, $assoc_args ) {

		$defaults   = array(
			'count'   => 100,
			'post_id' => 0,
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$format = Utils\get_flag_value( $assoc_args, 'format', 'progress' );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = Utils\make_progress_bar( 'Generating comments', $assoc_args['count'] );
		}

		$comment_count = wp_count_comments();
		$total         = (int) $comment_count->total_comments;
		$limit         = $total + $assoc_args['count'];

		for ( $index = $total; $index < $limit; $index++ ) {
			$comment_types = array(
				'comment',
				'like',
				'mention',
				'repost',
			);

			$comment_type = $comment_types[ array_rand( $comment_types ) ];

			$comment_id = wp_insert_comment(
				array(
					'comment_content' => "{$comment_type} {$index}",
					'comment_post_ID' => $assoc_args['post_id'],
					'comment_type'    => $comment_type,
					'comment_meta'    => array(
						'protocol'                 => 'webmention',
						'avatar'                   => "https://i.pravatar.cc/80?u={$index}",
						'url'                      => 'https://example.org/canonical',
						'webmention_last_modified' => current_time( 'mysql', 1 ),
						'webmention_source_url'    => 'https://example.org/source',
					),
				)
			);
			if ( 'progress' === $format ) {
				$notify->tick();
			} elseif ( 'ids' === $format ) {
				echo $comment_id;
				if ( $index < $limit - 1 ) {
					echo ' ';
				}
			}
		}

		if ( 'progress' === $format ) {
			$notify->finish();
		}
	}

	/**
	 * Run the Database Migration script
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function db_migration( $args, $assoc_args ) {
		require_once __DIR__ . '/class-db.php';

		DB::update_database();

		WP_CLI::success( __( 'DB Migration finished', 'webmention' ) );
	}
}
