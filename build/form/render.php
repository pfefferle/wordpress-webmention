<?php
/**
 * Server side rendering of the `webmention/form` block.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

echo get_webmention_form( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	array(
		'post'               => isset( $block->context['postId'] ) ? $block->context['postId'] : null,
		'wrapper_attributes' => get_block_wrapper_attributes( array( 'class' => 'webmention-form' ) ),
	)
);
