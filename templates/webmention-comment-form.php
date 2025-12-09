<?php
/**
 * Hook to add custom content before the Webmention form added to the comment form.
 */
do_action( 'webmention_comment_form_template_before' );
?>
<form id="webmention-form" action="<?php echo get_webmention_endpoint(); ?>" method="post">
	<p id="webmention-source-description">
		<?php echo get_webmention_form_text( get_the_ID() ); ?>
	</p>
	<p>
		<label for="webmention-source"><?php esc_attr_e( 'URL/Permalink of your article', 'webmention' ); ?></label>
		<input id="webmention-source" type="url" autocomplete="url" required pattern="^https?:\/\/(.*)" name="source" aria-describedby="webmention-source-description" />
	</p>
	<p>
		<input id="webmention-submit" type="submit" name="submit" value="<?php echo esc_attr( apply_filters( 'webmention_form_submit_text', __( 'Ping me!', 'webmention' ) ) ); ?>" />
	</p>
	<input id="webmention-format" type="hidden" name="format" value="html" />
	<input id="webmention-target" type="hidden" name="target" value="<?php the_permalink(); ?>" />
</form>
<?php
/**
 * Hook to add custom content after the Webmention form added to the comment form.
 */
do_action( 'webmention_comment_form_template_after' );
?>
