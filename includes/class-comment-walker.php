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

		// Remove Webmention types from the Comment Template Query
		if ( separate_webmentions_from_comments() ) {
			add_filter( 'comments_template_query_args', array( static::class, 'filter_comments_query_args' ) );

			add_action( 'comment_form_before', array( static::class, 'show_separated_reactions' ) );
			add_action( 'comment_form_comments_closed', array( static::class, 'show_separated_reactions' ) );
		}
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
	 * Filter the comment template query arguments to exclude Webmention comment types
	 *
	 * @param array $args an array of arguments for displaying comments
	 *
	 * @return array the filtered array
	 */
	public static function filter_comments_query_args( $args ) {
		$args['type__not_in'] = get_webmention_comment_type_names();

		return $args;
	}

	/**
	 * Show Facepile section
	 */
	public static function show_separated_reactions() {
		load_template( plugin_dir_path( __DIR__ ) . 'templates/webmention-comments.php' );
	}

	/**
	 * Display
	 */
	public static function get_overlay_default() {
		return apply_filters( 'webmention_reaction_overlay', true );
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

		++$depth;
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

		$defaults = array(
			'avatar_only' => false, // Implements an argument that will just output an avatar
			'overlay'     => self::get_overlay_default(), // Implements an argument that optionally overlays an icon on top of the profile image, applies only to the avatar only output
		);
		$args     = wp_parse_args( $args, $defaults );

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
		if ( $args['overlay'] ) {
			$overlay = '<span class="emoji-overlay">' . get_webmention_comment_type_attr( $comment->comment_type, 'icon' ) . '</span>';
		}
		?>
		<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( array( get_webmention_comment_type_attr( $comment->comment_type, 'class' ), 'h-cite', 'avatar-only' ), $comment ); ?>>
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

	/**
	 * Outputs a comment in the HTML5 format.
	 *
	 * @param WP_Comment $comment Comment to display.
	 * @param int        $depth   Depth of the current comment.
	 * @param array      $args    An array of arguments.
	 */
	protected function html5_comment( $comment, $depth, $args ) {
		// Only call this local version for comments that are webmention based.
		if ( 'webmention' !== get_comment_meta( $comment->comment_ID, 'protocol', true ) ) {
			parent::comment( $comment, $depth, $args );
			return;
		}

		$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';

		$cite = apply_filters( 'webmention_cite', '<small>&nbsp;@&nbsp;<cite><a href="%1s">%2s</a></cite></small>' );
		$url  = get_url_from_webmention( $comment );
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$host = preg_replace( '/^www\./', '', $host );
		$type = get_webmention_comment_type_attr( $comment->comment_type, 'class' );
		if ( 'comment' === $comment->comment_type ) {
			$type = 'p-comment';
		}

		$commenter          = wp_get_current_commenter();
		$show_pending_links = ! empty( $commenter['comment_author'] );

		if ( $commenter['comment_author_email'] ) {
			$moderation_note = __( 'Your comment is awaiting moderation.', 'default' );
		} else {
			$moderation_note = __( 'Your comment is awaiting moderation. This is a preview; your comment will be visible after it has been approved.', 'default' );
		}
		?>
		<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $this->has_children ? 'parent' : '', $comment ); ?>>
			<article id="div-comment-<?php comment_ID(); ?>" class="comment-body h-cite <?php echo $type; ?>">
				<footer class="comment-meta">
					<div class="comment-author vcard h-card u-author">
						<?php
						if ( 0 !== $args['avatar_size'] ) {
							echo get_avatar( $comment, $args['avatar_size'] );
						}
						?>
						<?php
						$comment_author = get_comment_author_link( $comment );

						if ( '0' === $comment->comment_approved && ! $show_pending_links ) {
							$comment_author = get_comment_author( $comment );
						}

						printf(
							/* translators: %s: Comment author link. */
							__( '%s <span class="says">says:</span>', 'default' ),
							sprintf( '<b class="fn">%s</b>', $comment_author )
						);
						if ( ! empty( $cite ) && 'webmention' === get_comment_meta( $comment->comment_ID, 'protocol', true ) ) {
							printf( $cite, $url, $host );
						}

						?>
					</div><!-- .comment-author -->

					<div class="comment-metadata">
						<?php
						// Allow arbitrary additions to comment metadata.
						do_action( 'webmention_comment_metadata', $comment );
						printf(
							'<a class="u-url" href="%s"><time class="dt-published" datetime="%s">%s</time></a>',
							esc_url( get_comment_link( $comment, $args ) ),
							get_comment_time( DATE_W3C ),
							sprintf(
								/* translators: 1: Comment date, 2: Comment time. */
								__( '%1$s at %2$s', 'default' ),
								get_comment_date( '', $comment ),
								get_comment_time()
							)
						);

						edit_comment_link( __( 'Edit', 'default' ), ' <span class="edit-link">', '</span>' );
						?>
					</div><!-- .comment-metadata -->

					<?php if ( '0' === $comment->comment_approved ) : ?>
					<em class="comment-awaiting-moderation"><?php echo $moderation_note; ?></em>
					<?php endif; ?>
				</footer><!-- .comment-meta -->

				<div class="comment-content e-content p-name">
					<?php comment_text(); ?>
				</div><!-- .comment-content -->

				<?php
				if ( '1' == $comment->comment_approved || $show_pending_links ) {
					comment_reply_link(
						array_merge(
							$args,
							array(
								'add_below' => 'div-comment',
								'depth'     => $depth,
								'max_depth' => $args['max_depth'],
								'before'    => '<div class="reply">',
								'after'     => '</div>',
							)
						)
					);
				}
				?>
			</article><!-- .comment-body -->
		<?php
	}
}
