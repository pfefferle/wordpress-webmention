<?php $post_id = get_the_ID(); ?>
<fieldset><ul>
	<li>
	<input type="hidden" name="webmentions_disabled" value="0" />
	<input type="checkbox" class="widefat" name="webmentions_disabled" id="webmentions_disabled" value="1" <?php checked( 1, get_post_meta( $post_id, 'webmentions_disabled', 1 ) ); ?> />
	<label><?php esc_html_e( 'Disable Incoming', 'webmention' ); ?></label>
	<legend><sub><?php _e( 'Do Not Accept incoming Webmentions for this post', 'webmention' ); ?></sub></legend><li>
	<li>
	<input type="hidden" name="webmentions_disabled_pings" value="0" />
	<input type="checkbox" class="widefat" name="webmentions_disabled_pings" id="webmentions_disabled_pings" value="1" <?php checked( 1, intval( get_post_meta( $post_id, 'webmentions_disabled_pings', 1 ) ) ); ?> />
	<label><?php esc_html_e( 'Disable Outgoing', 'webmention' ); ?></label>
	<legend><sub><?php _e( 'Do Not send outgoing Webmentions for this post', 'webmention' ); ?></sub></legend></li>
</fieldset>
