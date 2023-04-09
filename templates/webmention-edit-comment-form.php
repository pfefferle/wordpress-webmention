<?php global $comment; ?>
<label><?php esc_html_e( 'Target', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_target_url', true ); ?>" />
<br />

<label><?php esc_html_e( 'Source', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_source_url', true ); ?>" />
<br />

<label><?php esc_html_e( 'Creation Time', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_created_at', true ); ?>" />
<br />

<label><?php esc_html_e( 'Response Type', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo $comment->comment_type; ?>" />
<br />
