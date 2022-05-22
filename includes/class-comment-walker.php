<?php

namespace Webmention;

use WP_Comment;
use Walker_Comment;

class Comment_Walker extends Walker_Comment {
	/**
	 * Replace the default WordPress Comment Walker with this slightly enhanced one.
	 *
	 * @return void
	 */
	public static function init() {
		// Set New Walker at Priority 5 so it can be overwritten by anything at a higher level.
		add_filter( 'wp_list_comments_args', array( static::class, 'filter_comment_args' ), 5 );
	}

	/**
	 * Filter the comments to add custom comment walker
	 *
	 * @param array $args an array of arguments for displaying comments
	 *
	 * @return array the filtered array
	 */
	public static function filter_comment_args( $args ) {
		$args['walker'] = new Comment_Walker();
		return $args;
	}

	/**
	 * Starts the element output.
	 *
	 *              to match parent class for PHP 8 named parameter support.
	 *
	 * @see Walker::start_el()
	 * @see wp_list_comments()
	 * @global int        $comment_depth
	 * @global WP_Comment $comment       Global comment object.
	 *
	 * @param string     $output            Used to append additional content. Passed by reference.
	 * @param WP_Comment $data_object       Comment data object.
	 * @param int        $depth             Optional. Depth of the current comment in reference to parents. Default 0.
	 * @param array      $args              Optional. An array of arguments. Default empty array.
	 * @param int        $current_object_id Optional. ID of the current comment. Default 0.
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		// Restores the more descriptive, specific name for use within this method.
		$comment = $data_object;

		$depth++;
		$GLOBALS['comment_depth'] = $depth; // phpcs:ignore
		$GLOBALS['comment']       = $comment; // phpcs:ignore

		/* Changes the signature of callbacks. Behaves as previous if string.
		 * Now accepts an array of callbacks by comment type.
		 * If no calback for the type or no general callback drops through to normal handler.
		 */
		if ( ! empty( $args['callback'] ) ) {
			$callback = null;
			if ( is_string( $args['callback'] ) ) {
				$callback = $args['callback'];
			} elseif ( is_array( $args['callback'] ) && ! wp_is_numeric_array( $args['callback'] ) ) {
				if ( array_key_exists( $comment->comment_type, $args['callback'] ) ) {
					$callback = $args['callback'][ $comment->comment_type ];
				} elseif ( array_key_exists( 'all', $args['callback'] ) ) {
					$callback = $args['callback']['all'];
				}
			}
			if ( $callback ) {
				ob_start();
				call_user_func( $callback, $comment, $args, $depth );
				$output .= ob_get_clean();
				return;
			}
		}

		/* Implements an argument that will just output an avatar */
		if ( ! isset( $args['avatar_only'] ) ) {
			$args['avatar_only'] = false;
		}

		/* Implements an argument that optionally overlays an icon on top of the profile image, applies only to the avatar only output  */
		if ( ! isset( $args['overlay'] ) ) {
			$args['overlay'] = true;
		}

		if ( 'comment' === $comment->comment_type ) {
			add_filter( 'comment_text', array( $this, 'filter_comment_text' ), 40, 2 );
		}

		// Maintain the original pingback and trackback output.
		if ( ( 'pingback' === $comment->comment_type || 'trackback' === $comment->comment_type ) && $args['short_ping'] ) {
			ob_start();
			$this->ping( $comment, $depth, $args );
			$output .= ob_get_clean();
		} elseif ( $args['avatar_only'] ) {
			ob_start();
			$this->avatar_only( $comment, $depth, $args );
			$output .= ob_get_clean();

		} elseif ( 'html5' === $args['format'] ) {
			ob_start();
			$this->html5_comment( $comment, $depth, $args );
			$output .= ob_get_clean();
		} else {
			ob_start();
			$this->comment( $comment, $depth, $args );
			$output .= ob_get_clean();
		}

		if ( 'comment' === $comment->comment_type ) {
			remove_filter( 'comment_text', array( $this, 'filter_comment_text' ), 40 );
		}
	}

	/**
	 * Ends the element output, if needed.
	 *
	 * @see Walker::end_el()
	 * @see wp_list_comments()
	 *
	 * @param string     $output      Used to append additional content. Passed by reference.
	 * @param WP_Comment $data_object Comment data object.
	 * @param int        $depth       Optional. Depth of the current comment. Default 0.
	 * @param array      $args        Optional. An array of arguments. Default empty array.
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = array() ) {
		if ( ! empty( $args['end-callback'] ) ) {
			ob_start();
			call_user_func(
				$args['end-callback'],
				$data_object, // The current comment object.
				$args,
				$depth
			);
			$output .= ob_get_clean();
			return;
		}
		if ( 'div' === $args['style'] ) {
			$output .= "</div><!-- #comment-## -->\n";
		} else {
			$output .= "</li><!-- #comment-## -->\n";
		}
	}

	/**
	 * Outputs a comment as just a profile picture.
	 *
	 * @param WP_Comment $comment The comment object.
	 * @param int        $depth   Depth of the current comment.
	 * @param array      $args    An array of arguments.
	*/
	protected function avatar_only( $comment, $depth, $args ) {
		$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';

		$title = get_comment_text( $comment, $args );
		// Optionally overlay an icon.
		$overlay = '';
		?>
		<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( array( 'u-comment', 'h-cite' ), $comment ); ?>>
			<div class="comment-body">
				<span class="p-author h-card">
					<a class="u-url" title="<?php esc_attr( $title ); ?>" href="<?php echo get_comment_author_url( $comment ); ?>">
						<?php echo get_avatar( $comment, $args['avatar_size'] ); ?>
						<?php echo $overlay; ?>
					</a>
					<span class="hide-name p-name"><?php echo get_comment_author( $comment ); ?></span>
				</span>
			</div>
		<?php
	}
}
