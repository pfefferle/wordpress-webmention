<?php
if (
	has_filter( 'webmention_title' ) ||
	has_filter( 'webmention_content' ) ||
	has_filter( 'webmention_comment_type' ) ||
	has_filter( 'webmention_comment_approve' ) ||
	has_filter( 'webmention_comment_parent' )
	) {

	/**
	 * Show a warnging if someone still uses one of the old filers
	 *
	 * @return void
	 */
	function webmention_show_deprecated_filter_warning() {
	?>
<div class="error notice">
	<p><?php _e( 'One of your Plugins is using a deprecated <strong>Webmention</strong>-hook,
	please try to fix it. The deprecated filters are: <code>webmention_title</code>,
	<code>webmention_content</code>, <code>webmention_comment_type</code>,
	<code>webmention_comment_approve</code>, <code>webmention_comment_parent</code>.
	Please try to fix it until WordPress 4.8 is out.', 'webmention' ); ?></p>
</div>
	<?php
	}
	add_action( 'admin_notices', 'webmention_show_deprecated_filter_warning' );
}

/**
 * Re-Implement deprecated hooks for a smooth transition
 *
 * @param  array $commentdata the unfiltert comment array
 * @return array              the filtert comment array
 */
function webmention_deprecated_filters( $commentdata ) {
	if ( ! $commentdata || is_wp_error( $commentdata ) ) {
		return $commentdata;
	}

	$contents = $commentdata['comment_content'];
	$target = $commentdata['target'];
	$source = $commentdata['comment_author_url'];

	$commentdata['comment_author'] = apply_filters( 'webmention_title', $commentdata['comment_author'], $contents, $target, $source );
	$commentdata['comment_content'] = apply_filters( 'webmention_content', $commentdata['comment_content'], $contents, $target, $source );
	$commentdata['comment_type'] = apply_filters( 'webmention_comment_type', $commentdata['comment_type'] );
	$commentdata['comment_approved'] = apply_filters( 'webmention_comment_approve', $commentdata['comment_approved'] );
	$commentdata['comment_parent'] = apply_filters( 'webmention_comment_parent', $commentdata['comment_parent'], $target );

	return $commentdata;
}
add_filter( 'webmention_comment_data', 'webmention_deprecated_filters', 31 );
