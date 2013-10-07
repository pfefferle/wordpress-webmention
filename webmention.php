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
  if (!isset($wp_query->query_vars['webmention'])) {
    return;
  }
  
  $content = file_get_contents('php://input');
  parse_str($content);
    
  header('Content-Type: application/json; charset=' . get_option('blog_charset'));
    
  // check if source url is transmitted
  if (!isset($source)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array("error"=> "source_not_found"));
    exit;
  }
    
  // check if target url is transmitted
  if (!isset($target)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array("error"=> "target_empty"));
    exit;
  }
    
  $post_ID = webmention_url_to_postid($target);
  
  // check if post id exists
  if ( !$post_ID ) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array("error"=> "target_not_found"));
    exit;
  }
    
  $post_ID = (int) $post_ID;
  $post = get_post($post_ID);
  
  // check if post exists
  if ( !$post ) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array("error"=> "target_not_found"));
    exit;
  }
  
  $response = wp_remote_get( $source, array('timeout' => 100) );
    
  // check if source is accessible
  if ( is_wp_error( $response ) ) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array("error"=> "source_not_accessible"));
    exit;
  }
    
  // check 
    
  do_action('webmention_post', $wpdb->insert_id);
}
add_action('parse_query', 'webmention_parse_query');

// adds some query vars
function webmention_query_var($vars) {
  $vars[] = 'webmention';
  $vars[] = 'replytocom';

  return $vars;
}
add_filter('query_vars', 'webmention_query_var');

/**
 * adds the "http://webmention.org/" meta-tag
 */
function webmention_head() {
  echo '<link rel="http://webmention.org/" href="'.site_url("?webmention=endpoint").'" />'."\n";
  echo '<link rel="webmention" href="'.site_url("?webmention=endpoint").'" />'."\n";
}
add_action("wp_head", "webmention_head");

/**
 * adds the webmention header
 */
function webmention_template_redirect() {
  header('Link: <'.site_url("?webmention=endpoint").'>; rel="http://webmention.org/"', false);
  header('Link: <'.site_url("?webmention=endpoint").'>; rel="webmention"', false);
}
add_action('template_redirect', 'webmention_template_redirect');

/**
 * send webmentions
 *
 * @param string $source source url
 * @param string $target target url
 * @return array of results including HTTP headers
 */
function webmention_send_ping($source, $target) {
  $webmention_server_url = discover_webmention_server_uri( $target );
  
  $args = array(
            'body' => 'source='.urlencode($source).'&target='.urlencode($target)
          );

  return wp_remote_post( $webmention_server_url, $args );
}

/**
 * webmention pre_ping hook
 *
 * @param array $links Links to ping
 * @param array $punk Pinged links
 * @param int $id The post_ID
 */
function webmention_pre_ping( $links, $pung, $post_ID ) {  
  // get source url
  $source = get_permalink($post_ID);

  // get post
  $post = get_post($post_ID);

  foreach ( (array) $post_links as $target ) {
    $data = webmention_send_ping($source, $target);
  }
}
add_action( 'pre_ping', 'webmention_pre_ping', 10, 3 );

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

  $response = wp_remote_head( $url, array( 'timeout' => 100, 'httpversion' => '1.0' ) );

  if ( is_wp_error( $response ) )
    return false;

  if ( $links = wp_remote_retrieve_header( $response, 'link' ) ) {
    if ( is_array($links) ) {
      foreach ($links as $link) {
        if (preg_match("/<(.+)>;\s+rel\s?=\s?[\"\']?http:\/\/webmention.org\/?[\"\']?/i", $link, $result))
          return $result[1];
      }
    } else {
      if (preg_match("/<(.+)>;\s+rel\s?=\s?[\"\']?http:\/\/webmention.org\/?[\"\']?/i", $links, $result))
        return $result[1];
    }
  }

  // Not an (x)html, sgml, or xml page, no use going further.
  if ( preg_match('#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' )) )
    return false;

  // Now do a GET since we're going to look in the html headers (and we're sure its not a binary file)
  $response = wp_remote_get( $url, array( 'timeout' => 100, 'httpversion' => '1.0' ) );

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

    // We may find rel="http://webmention.org/" but an incomplete Webmention URL
    if ( $webmention_server_url_len > 0 ) { // We got it!
      return $webmention_server_url;
    } 
  }

  return false;
}

function webmention_url_to_postid( $url ) {
  // Try the core function
  $post_id = url_to_postid( $url );
  if ( $post_id == 0 ) {
    $url = preg_replace('/\?.*/', '', $url);
    // Try custom post types
    $cpts = get_post_types( array(
      'public'   => true,
      '_builtin' => false
    ), 'objects', 'and' );
    // Get path from URL
    $url_parts = explode( '/', trim( $url, '/' ) );
    $url_parts = array_splice( $url_parts, 3 );
    $path = implode( '/', $url_parts );
    // Test against each CPT's rewrite slug
    foreach ( $cpts as $cpt_name => $cpt ) {
      $cpt_slug = $cpt->rewrite['slug'];
      if ( strlen( $path ) > strlen( $cpt_slug ) && substr( $path, 0, strlen( $cpt_slug ) ) == $cpt_slug ) {
        $slug = substr( $path, strlen( $cpt_slug ) );
        $query = new WP_Query( array(
          'post_type'         => $cpt_name,
          'name'              => $slug,
          'posts_per_page'    => 1
        ));
        if ( is_object( $query->post ) )
          $post_id = $query->post->ID;
      }
    }
  }
  return $post_id;
}