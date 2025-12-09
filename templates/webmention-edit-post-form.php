<?php
$post_id = get_the_ID();
?>
<fieldset>
	<ul>
		<li>
			<label>
				<input type="hidden" name="webmentions_disabled" value="0" />
				<input type="checkbox" name="webmentions_disabled" id="webmentions_disabled" value="1" <?php checked( get_post_meta( $post_id, 'webmentions_disabled', true ) ); ?> />
				<?php esc_html_e( 'Disable Incoming', 'webmention' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Do not accept incoming Webmentions for this post.', 'webmention' ); ?></p>
		</li>
		<li>
			<label>
				<input type="hidden" name="webmentions_disabled_pings" value="0" />
				<input type="checkbox" name="webmentions_disabled_pings" id="webmentions_disabled_pings" value="1" <?php checked( get_post_meta( $post_id, 'webmentions_disabled_pings', true ) ); ?> />
				<?php esc_html_e( 'Disable Outgoing', 'webmention' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Do not send outgoing Webmentions for this post.', 'webmention' ); ?></p>
		</li>
	</ul>
</fieldset>
