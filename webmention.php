<?php
/*
 Plugin Name: Webmention
 Plugin URI: https://github.com/pfefferle/wordpress-webmention
 Description: Webmention support for WordPress posts/comments
 Author: pfefferle
 Author URI: http://notizblog.org/
 Version: 1.0.0-dev
*/

function webmention_parse_query($wp_query) {
  if (isset($wp_query->query_vars['webmention']) &&
      $wp_query->query_vars['webmention'] == "endpoint") {
    
    $content = file_get_contents('php://input');
    wp_mail(get_bloginfo('admin_email'), 'someone mentioned you', strip_tags($content));
    
    exit;
  }
}
add_action('parse_query', 'webmention_parse_query');

// adds some query vars
function webmention_query_var($vars) {
  $vars[] = 'webmention';

  return $vars;
}
add_filter('query_vars', 'webmention_query_var');

/**
 * adds the "http://webmention.org/" meta-tag
 */
function webmention_add_header() {
  echo '<link rel="http://webmention.org/" href="'.site_url("?webmention=endpoint").'" />'."\n";
}
add_action("wp_head", "webmention_add_header");

/**
 * adds the webmention header
 */
function webmention_template_redirect() {
  header('Link: <'.site_url("?webmention=endpoint").'>; rel=http://webmention.org/', false);
}
add_action('template_redirect', 'webmention_template_redirect');

/**
 * send webmention
 *
 * @param array $links Links to ping
 * @param array $punk Pinged links
 * @param int $id The post_ID
 */
function webmention_ping( $links, $pung, $post_ID ) {
	foreach ( (array) $links as $pagelinkedto ) {
		$webmention_server_url = discover_webmention_server_uri( $pagelinkedto );
    
    $pagelinkedfrom = get_permalink($post_ID);
    $args = array( 'body' => array( 'source' => $pagelinkedfrom, 'target' => $pagelinkedto ) );
    
    wp_remote_post( $webmention_server_url, $args );
  }
}
add_action( 'pre_ping', 'webmention_ping', 10, 3 );

/**
 * Finds a webmention server URI based on the given URL.
 *
 * Checks the HTML for the rel="http://webmention.org/" link and http://webmention.org/ headers. It does
 * a check for the http://webmention.org/ headers first and returns that, if available. The
 * check for the rel="http://webmention.org/" has more overhead than just the header.
 *
 * @since 1.0.0
 *
 * @param string $url URL to ping.
 * @param int $deprecated Not Used.
 * @return bool|string False on failure, string containing URI on success.
 */
function discover_webmention_server_uri( $url ) {
	$webmention_str_dquote = 'rel="http://webmention.org/"';
	$webmention_str_squote = 'rel=\'http:\/\/webmention.org\/\'';

	/** @todo Should use Filter Extension or custom preg_match instead. */
	$parsed_url = parse_url($url);

	if ( ! isset( $parsed_url['host'] ) ) // Not an URL. This should never happen.
		return false;

	//Do not search for a webmention server on our own uploads
	$uploads_dir = wp_upload_dir();
	if ( 0 === strpos($url, $uploads_dir['baseurl']) )
		return false;

	$response = wp_remote_head( $url, array( 'timeout' => 2, 'httpversion' => '1.0' ) );

	if ( is_wp_error( $response ) )
		return false;

	if ( wp_remote_retrieve_header( $response, 'http://webmention.org/' ) )
		return wp_remote_retrieve_header( $response, 'http://webmention.org/' );

	// Not an (x)html, sgml, or xml page, no use going further.
	if ( preg_match('#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' )) )
		return false;

	// Now do a GET since we're going to look in the html headers (and we're sure its not a binary file)
	$response = wp_remote_get( $url, array( 'timeout' => 2, 'httpversion' => '1.0' ) );

	if ( is_wp_error( $response ) )
		return false;

	$contents = wp_remote_retrieve_body( $response );

	$webmention_link_offset_dquote = strpos($contents, $webmention_str_dquote);
	$webmention_link_offset_squote = strpos($contents, $webmention_str_squote);
	if ( $webmention_link_offset_dquote || $webmention_link_offset_squote ) {
		$quote = ($webmention_link_offset_dquote) ? '"' : '\'';
		$webmention_link_offset = ($quote=='"') ? $webmention_link_offset_dquote : $webmention_link_offset_squote;
		$webmention_href_pos = @strpos($contents, 'href=', $webmention_link_offset);
		$webmention_href_start = $webmention_href_pos+6;
		$webmention_href_end = @strpos($contents, $quote, $webmention_href_start);
		$webmention_server_url_len = $webmention_href_end - $webmention_href_start;
		$webmention_server_url = substr($contents, $webmention_href_start, $webmention_server_url_len);

		// We may find rel="pingback" but an incomplete pingback URL
		if ( $webmention_server_url_len > 0 ) { // We got it!
			return $webmention_server_url;
		}
	}

	return false;
}