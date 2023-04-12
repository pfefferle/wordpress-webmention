<?php global $comment; ?>
<label><?php esc_html_e( 'Comment Type', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_webmention_comment_type_string( $comment ); ?>" />
<br />
<?php if ( 'webmention' === get_comment_meta( $comment->comment_ID, 'protocol', true ) ) { ?>
	<label><?php esc_html_e( 'Target', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_target_url', true ); ?>" />
	<br />

	<label><?php esc_html_e( 'Source', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_source_url', true ); ?>" />
	<br />

	<label><?php esc_html_e( 'Avatar', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'avatar', true ); ?>" />
	<br />

	<label><?php esc_html_e( 'Canonical URL', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'url', true ); ?>" />
	<br />

	<label><?php esc_html_e( 'Creation Time', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_created_at', true ); ?>" />
	<br />
<?php } ?>
