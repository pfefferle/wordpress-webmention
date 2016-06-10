<?php
/**
 * A wrapper for Webmention_Sender::send_webmention
 *
 * @param string $source source url
 * @param string $target target url
 *
 * @return array of results including HTTP headers
 */
function send_webmention( $source, $target ) {
	return Webmention_Sender::send_webmention( $source, $target );
}

/**
 * Return the Number of WebMentions
 *
 * @param int $post_id The post ID (optional)
 *
 * @return int the number of WebMentions for one Post
 */
function get_webmentions_number( $post_id = 0 ) {
	$post = get_post( $post_id );

	// change this if your theme can't handle the WebMentions comment type
	$comment_type = apply_filters( 'webmention_comment_type', WEBMENTION_COMMENT_TYPE );

	$args = array(
		'post_id'	=> $post->ID,
		'type'		=> $comment_type,
		'count'		=> true,
		'status'	=> 'approve',
	);

	$comments_query = new WP_Comment_Query;
	return $comments_query->query( $args );
}

/**
 * Return Webmention Endpoint
 *
 * @return string the Webmention endpoint
 */
function get_webmention_endpoint() {
	return apply_filters( 'webmention_endpoint', site_url( '?webmention=endpoint' ) );
}
