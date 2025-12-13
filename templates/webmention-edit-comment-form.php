<?php global $comment; ?>
<label><?php esc_html_e( 'Comment Type', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_webmention_comment_type_string( $comment ); ?>" />
<br />

<label for="webmention_avatar"><?php esc_html_e( 'Avatar', 'webmention' ); ?></label>
<div style="display: flex; gap: 5px;">
	<input type="url" name="webmention_avatar" id="webmention_avatar" class="widefat" value="<?php echo get_comment_meta( $comment->comment_ID, 'avatar', true ); ?>" />
	<button type="button" class="button" id="webmention_avatar_upload"><?php esc_html_e( 'Upload', 'webmention' ); ?></button>
</div>
<br />

<?php if ( 'webmention' === get_comment_meta( $comment->comment_ID, 'protocol', true ) ) { ?>
	<label><?php esc_html_e( 'Target', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_target_url', true ); ?>" />
	<br />

	<label><?php esc_html_e( 'Source', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_source_url', true ); ?>" />
	<br />

	<label><?php esc_html_e( 'Canonical URL', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'url', true ); ?>" />
	<br />

	<label><?php esc_html_e( 'Creation Time', 'webmention' ); ?></label>
	<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $comment->comment_ID, 'webmention_created_at', true ); ?>" />
	<br />
<?php } ?>

<script>
	jQuery(document).ready(function($){
		var frame;
		$('#webmention_avatar_upload').on('click', function(e) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: '<?php esc_html_e( 'Select Avatar', 'webmention' ); ?>',
				button: {
					text: '<?php esc_html_e( 'Use this image', 'webmention' ); ?>'
				},
				multiple: false
			});

			frame.on( 'select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#webmention_avatar').val(attachment.url);
			});

			frame.open();
		});
	});
</script>