<?php global $comment; ?>
<label><?php _e( 'Webmention Target', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_target_url', true ); ?>" />
<br />

<label><?php _e( 'Webmention Target Fragment', 'webmention' ); ?></label>
<input type="text" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_target_fragment', true ); ?>" />
<br />

<label><?php _e( 'Webmention Source', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_source_url', true ); ?>" />
<br />

<label><?php _e( 'Webmention Creation Time', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_created_at', true ); ?>" />
<br />
<?php
