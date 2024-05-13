<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */
?>
<p <?php echo get_block_wrapper_attributes(); ?>>
	<?php printf( __( 'This is a reply to: <a href="%1$s" class="u-in-reply-to">%1$s</a>' ), trim( $content ) ); ?>
</p>
