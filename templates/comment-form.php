<?php
/**
 * Webmention form template.
 *
 * Used by the classic comment form and the `webmention/form` block.
 *
 * @var array $args {
 *     @type WP_Post $post               The post the Webmention targets.
 *     @type string  $target             The target URL.
 *     @type string  $wrapper_attributes Extra HTML attributes for the form.
 *     @type string  $id_suffix          Suffix for element IDs, to allow multiple forms per page.
 * }
 */

$args = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'post'               => get_post(),
		'target'             => get_permalink(),
		'wrapper_attributes' => '',
		'id_suffix'          => '',
	)
);

$webmention_id = function ( $name ) use ( $args ) {
	return esc_attr( 'webmention-' . $name . $args['id_suffix'] );
};

/**
 * Hook to add custom content before the Webmention form added to the comment form.
 */
do_action( 'webmention_comment_form_template_before' );
?>
<form id="<?php echo $webmention_id( 'form' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" <?php echo $args['wrapper_attributes'] ? $args['wrapper_attributes'] : 'class="webmention-form"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> action="<?php echo esc_url( get_webmention_endpoint() ); ?>" method="post">
	<p class="webmention-form__source">
		<label for="<?php echo $webmention_id( 'source' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php esc_html_e( 'Posted a response on your own website? Share the link here:', 'webmention' ); ?></label>
		<input id="<?php echo $webmention_id( 'source' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="webmention-source" type="url" autocomplete="url" required pattern="^https?:\/\/(.*)" name="source" placeholder="https://" aria-describedby="<?php echo $webmention_id( 'source-description' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
		<small id="<?php echo $webmention_id( 'source-description' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php esc_html_e( 'Your post must include a link to this post.', 'webmention' ); ?></small>
	</p>
	<div class="webmention-form__submit">
		<input id="<?php echo $webmention_id( 'submit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="webmention-submit wp-element-button wp-block-button__link" type="submit" name="submit" value="<?php echo esc_attr( apply_filters( 'webmention_form_submit_text', __( 'Send Webmention', 'webmention' ) ) ); ?>" />
		<details class="webmention-form__help">
			<summary><?php esc_html_e( 'How does this work?', 'webmention' ); ?></summary>
			<div>
				<?php echo get_webmention_form_text( $args['post']->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</details>
	</div>
	<input id="<?php echo $webmention_id( 'format' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" type="hidden" name="format" value="html" />
	<input id="<?php echo $webmention_id( 'target' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" type="hidden" name="target" value="<?php echo esc_url( $args['target'] ); ?>" />
</form>
<?php
/**
 * Hook to add custom content after the Webmention form added to the comment form.
 */
do_action( 'webmention_comment_form_template_after' );
