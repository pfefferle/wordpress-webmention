<?php
	$comment            = $args['comment'];
	$title              = $args['title'];
	$tag                = $args['tag'];
	$overlay            = $args['overlay'];
	$args               = $args['args'];
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