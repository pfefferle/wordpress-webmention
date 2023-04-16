<?php
	$comment            = $args['comment'];
	$depth              = $args['depth'];
	$tag                = $args['tag'];
	$has_children       = $args['has_children'];
	$commenter          = $args['commenter'];
	$show_pending_links = $args['show_pending_links'];
	$moderation_note    = $args['moderation_note'];
	$args               = $args['args'];
?>

<<?php echo $tag; ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $has_children ? 'parent' : '', $comment ); ?>>

<article id="div-comment-<?php comment_ID(); ?>" class="comment-body">
	<footer class="comment-meta">
		<div class="comment-author vcard">
			<?php
			if ( 0 != $args['avatar_size'] ) {
				echo get_avatar( $comment, $args['avatar_size'] );
			}
			?>
			<?php
			$comment_author = get_comment_author_link( $comment );

			if ( '0' == $comment->comment_approved && ! $show_pending_links ) {
				$comment_author = get_comment_author( $comment );
			}

			printf(
				/* translators: %s: Comment author link. */
				__( '%s <span class="says">says:</span>' ),
				sprintf( '<b class="fn">%s</b>', $comment_author )
			);
			?>
		</div><!-- .comment-author -->

		<div class="comment-metadata">
			<?php
			printf(
				'<a href="%s"><time datetime="%s">%s</time></a>',
				esc_url( get_comment_link( $comment, $args ) ),
				get_comment_time( 'c' ),
				sprintf(
					/* translators: 1: Comment date, 2: Comment time. */
					__( '%1$s at %2$s' ),
					get_comment_date( '', $comment ),
					get_comment_time()
				)
			);

			edit_comment_link( __( 'Edit' ), ' <span class="edit-link">', '</span>' );
			?>
		</div><!-- .comment-metadata -->

		<?php if ( '0' == $comment->comment_approved ) : ?>
		<em class="comment-awaiting-moderation"><?php echo $moderation_note; ?></em>
		<?php endif; ?>
	</footer><!-- .comment-meta -->

	<div class="comment-content">
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