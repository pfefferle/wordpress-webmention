<?php
/**
 * ActivityPub integration file.
 *
 * @package Webmention
 */

namespace Webmention\Integration;

use Activitypub\Comment;
use Webmention\Comment_Walker;

use function Activitypub\register_comment_type;

/**
 * Compatibility with the ActivityPub plugin.
 *
 * Likes and reposts share the `like` and `repost` comment types with the
 * ActivityPub plugin, so its reactions block already renders them. This
 * registers the remaining Webmention types (bookmarks, favorites, …) with
 * ActivityPub too, so the block renders all of them, and removes the
 * Webmention facepile wherever the block is used.
 *
 * @see https://wordpress.org/plugins/activitypub/
 */
class Activitypub {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		\add_action( 'init', array( self::class, 'register_comment_types' ), 20 );
		\add_action( 'comment_form_before', array( self::class, 'maybe_remove_facepile' ), 9 );
		\add_action( 'comment_form_comments_closed', array( self::class, 'maybe_remove_facepile' ), 9 );
	}

	/**
	 * Register the Webmention comment types the ActivityPub plugin does not
	 * know about, so its reactions block renders them as well.
	 *
	 * @return void
	 */
	public static function register_comment_types() {
		if ( ! separate_webmentions_from_comments() ) {
			return;
		}

		foreach ( get_webmention_comment_types() as $slug => $type ) {
			// Never replace a type ActivityPub already registered (`like`, `repost`, …).
			if ( Comment::is_registered_comment_type( $slug ) ) {
				continue;
			}

			register_comment_type(
				$slug,
				array(
					'label'          => $type->label,
					'singular'       => $type->singular,
					'description'    => $type->description,
					'icon'           => $type->icon,
					'class'          => $type->class,
					'type'           => $slug,
					'collection'     => $slug,
					'activity_types' => array(),
					'excerpt'        => '',
					'count_single'   => '%d ' . $type->singular,
					'count_plural'   => '%d ' . $type->label,
				)
			);
		}
	}

	/**
	 * Remove the Webmention facepile if the ActivityPub reactions block was
	 * already rendered on the page.
	 *
	 * Covers the block in the post content, in a template and the version
	 * hooked after `core/post-content`, all of which render before the
	 * comment form.
	 *
	 * @return void
	 */
	public static function maybe_remove_facepile() {
		if ( ! \did_filter( 'render_block_activitypub/reactions' ) ) {
			return;
		}

		\remove_action( 'comment_form_before', array( Comment_Walker::class, 'show_separated_reactions' ) );
		\remove_action( 'comment_form_comments_closed', array( Comment_Walker::class, 'show_separated_reactions' ) );
	}
}
