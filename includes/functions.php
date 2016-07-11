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

if ( ! function_exists( 'wp_get_meta_tags' ) ) :
	/**
	 * Parse meta tags from source content
	 * Based on the Press This Meta Parsing Code
	 *
	 * @param string $source_content Source Content
	 *
	 * @return array meta tags
	 */
	function wp_get_meta_tags( $source_content ) {
		$meta_tags = array();

		if ( ! $source_content ) {
			return $meta_tags;
		}

		if ( preg_match_all( '/<meta [^>]+>/', $source_content, $matches ) ) {
			$items = $matches[0];
			foreach ( $items as $value ) {
				if ( preg_match( '/(property|name)="([^"]+)"[^>]+content="([^"]+)"/', $value, $matches ) ) {
					$meta_name  = $matches[2];
					$meta_value = $matches[3];
					// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
					if ( strlen( $meta_name ) > 100 ) {
						continue;
					}
					$meta_tags[ $meta_name ] = $meta_value;
				}
			}
		}
		return $meta_tags;
	}
endif;
