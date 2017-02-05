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
 * Return the Number of Webmentions
 *
 * @param int $post_id The post ID (optional)
 *
 * @return int the number of Webmentions for one Post
 */
function get_webmentions_number( $post_id = 0 ) {
	$post = get_post( $post_id );

	// change this if your theme can't handle the Webmentions comment type
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
 * @see https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
 *
 * @return string the Webmention endpoint
 */
function get_webmention_endpoint() {
	return apply_filters( 'webmention_endpoint', get_rest_url( null, '/webmention/1.0/endpoint' ) );
}

/**
 * Return Webmention process type
 *
 * @see https://www.w3.org/TR/webmention/#receiving-webmentions
 *
 * @return string the Webmention process type
 */
function get_webmention_process_type() {
	return apply_filters( 'webmention_process_type', WEBMENTION_PROCESS_TYPE );
}

/**
 * Return the post_id for a URL filtered for webmentions.
 * Allows redirecting to another id to add linkbacks to the home page or archive
 * page or taxonomy page.
 *
 * @param string $url URL
 * @param int Return 0 if no post ID found or a post ID
 *
 * @uses apply_filters calls "webmention_post_id" on the post_ID
 */
function webmention_url_to_postid( $url ) {
	return apply_filters( 'webmention_post_id', url_to_postid( $url ), $url );
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

if ( ! function_exists( 'wp_extract_urls' ) ) :
	/**
	 * Use RegEx to extract URLs from arbitrary content.
	 *
	 * @since 3.7.0
	 *
	 * @param string $content Content to extract URLs from.
	 * @return array URLs found in passed string.
	 */
	function wp_extract_urls( $content ) {
		preg_match_all(
			'#(["\']?)('
				. '(?:([\w-]+:)?//?)'
				. '[^\s()<>]+'
				. '[.]'
				. '(?:'
					. '\([\w\d]+\)|'
					. '(?:'
						. '[^`!()\[\]{};:\'".,<>«»“”‘’\s]|'
						. '(?:[:]\d+)?/?'
					. ')+'
				. ')'
			. ')\\1#',
			$content,
			$post_links
		);
		$post_links = array_unique( array_map( 'html_entity_decode', $post_links[2] ) );
		return array_values( $post_links );
	}
endif;
