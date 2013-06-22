<?php
/*
 Plugin Name: Webmention
 Plugin URI: https://github.com/pfefferle/wordpress-webmention
 Description: Webmention support for WordPress posts/comments
 Author: pfefferle
 Author URI: http://notizblog.org/
 Version: 1.0.0-dev
*/

include_once 'vendor/mf2/Parser.php';
include_once 'vendor/webignition/AbsoluteUrlDeriver/AbsoluteUrlDeriver.php';

use mf2\Parser;

function webmention_parse_query($wp_query) {
  if (isset($wp_query->query_vars['webmention'])) {
    
    $content = file_get_contents('php://input');
    parse_str($content);
    
    header('Content-Type: application/json; charset=' . get_option('blog_charset'));
    
    // check if source url is transmitted
    if (!isset($source)) {
      header("Status: 400 Bad Request");
      echo json_encode(array("error"=> "source_not_found"));
      exit;
    }
    
    // check if target url is transmitted
    if (!isset($target)) {
      header("Status: 404 Not Found");
      echo json_encode(array("error"=> "target_not_found"));
      exit;
    }
    
    $post_ID = url_to_postid($target);
    
    // check if post id exists
    if ( !$post_ID ) {
      header("Status: 404 Not Found");
      echo json_encode(array("error"=> "target_not_found"));
      exit;
    }
    
    $post_ID = (int) $post_ID;
    $post = get_post($post_ID);
    
    // check if post exists
    if ( !$post ) {
      header("Status: 404 Not Found");
      echo json_encode(array("error"=> "target_not_found"));
      exit;
    }
    
    $response = wp_remote_get( $source, array('timeout' => 100) );
    
    // check if source is accessible
    if ( is_wp_error( $response ) ) {
      header("Status: 404 Not Found");
      echo json_encode(array("error"=> "source_not_found"));
      exit;
    }

    $contents = wp_remote_retrieve_body( $response );
    
    do_action( 'webmention_ping', $contents, $source, $target, $post );
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
    $args = array(
              'body' => array( 'source' => $pagelinkedfrom, 'target' => $pagelinkedto ),
              'headers' => array( 'Content-Type' => 'application/x-www-url-form-encoded' )
            );
    
    $response = wp_remote_post( $webmention_server_url, $args );
  }
}
add_action( 'pre_ping', 'webmention_ping', 10, 3 );

/**
 * 
 */
function webmention_mf2_to_comment( $html, $source, $target, $commentdata ) {
  global $wpdb;

  $parser = new Parser( $html );
  $result = $parser->parse();
  
  $hentry = webmention_hentry_walker($result, $target);
  
  if (!$hentry) {
    return false;
  }
  
  if (isset($hentry['summary'])) {
    $commentdata['comment_content'] = $wpdb->escape($hentry['summary'][0]);
  } elseif (isset($hentry['content'])) {
    $commentdata['comment_content'] = $wpdb->escape($hentry['content'][0]);
  } elseif (isset($hentry['name'])) {
    $commentdata['comment_content'] = $wpdb->escape($hentry['name'][0]);
  } else {
    return false;
  }

  $author = null;
  
  // check if h-card has an author
  if ( isset($hentry['author']) && isset($hentry['author'][0]['properties']) ) {
    $author = $hentry['author'][0]['properties'];
  }
  
  // get representative hcard
  if (!$author) {
    foreach ($result["items"] as $mf) {    
      if ( isset( $mf["type"] ) ) {
        if ( in_array( "h-card", $mf["type"] ) ) {
          // check domain
          if (isset($mf['properties']) && isset($mf['properties']['url'])) {
            foreach ($mf['properties']['url'] as $url) {
              if (parse_url($url, PHP_URL_HOST) == parse_url($source, PHP_URL_HOST)) {
                $author = $mf['properties'];
                break;
              }
            }
          }
        }
      }
    }
  }
	
  if ($author) {
    if (isset($author['name'])) {
      $commentdata['comment_author'] = $wpdb->escape($author['name'][0]);
    }
  
    if (isset($author['url'])) {
      $commentdata['comment_author_url'] = $wpdb->escape($source);
    }
  }
  
  $commentdata['comment_type'] = 'pingback';  
  return $commentdata;
}

/**
 *
 */
function webmention_to_comment( $html, $source, $target, $post ) {
  $comment_post_ID = (int) $post->ID;
  $commentdata = webmention_mf2_to_comment( $html, $source, $target, array('comment_post_ID' => $comment_post_ID, 'comment_author' => '', 'comment_author_url' => '', 'comment_author_email' => '', 'comment_content' => '', 'comment_type' => '') );

  if (!$commentdata) {
    header("Status: 404 Not Found");
    echo json_encode(array("error"=> "no_link_found"));
    exit;
  }
  
  $comment_ID = wp_new_comment($commentdata);
}
add_action( 'webmention_ping', 'webmention_to_comment', 15, 4 );

/**
 *
 *
 */
function webmention_pingback_fix($comment_ID) {
  $commentdata = get_comment($comment_ID, ARRAY_A);
  
  if (!$commentdata) {
    return false;
  }
  
  $post = get_post($commentdata['comment_post_ID'], ARRAY_A);
  
  if (!$post) {
    return false;
  }
  
  $target = get_permalink($post['ID']);
  $response = wp_remote_get( $commentdata['comment_author_url'] );
  
  if ( is_wp_error( $response ) ) {
    return false;
  }

  $contents = wp_remote_retrieve_body( $response );
  $commentdata = webmention_mf2_to_comment( $contents, $commentdata['comment_author_url'], $target, $commentdata );
  
  if ($commentdata) {
    wp_update_comment($commentdata);
  }
}
add_action( 'pingback_post', 'webmention_pingback_fix', 90, 1 );

/**
 * 
 */
function webmention_debug( $html, $source, $target ) {
  wp_mail(get_bloginfo('admin_email'), 'someone mentioned you', print_r($source, true));
}
add_action( 'webmention_ping', 'webmention_debug', 10, 3 );

/**
 *
 */
function webmention_hentry_walker( $mf_array, $target ) {
  if ( !is_array( $mf_array ) ) {
    return false;
  }
  
  if ( !isset( $mf_array["items"] ) ) {
    return false;
  }
  
  if ( count( $mf_array["items"] ) == 0 ) {
    return false;
  }
  
  foreach ($mf_array["items"] as $mf) {    
    if ( isset( $mf["type"] ) ) {
      if ( in_array( "h-entry", $mf["type"] ) ) {
        if ( isset( $mf['properties'] ) ) {
          foreach ($mf['properties'] as $key => $values) {            
            if ( in_array( $key, array("in-reply-to", "like", "mention") )) {
              foreach ($values as $value) {
                if ($value == $target) {
                  return $mf['properties'];
                }
              }
            } elseif ( ($key == "content") && preg_match_all("|<a[^>]+?".preg_quote($target, "|")."[^>]*>([^>]+?)</a>|", $values[0], $context) ) {
              return $mf['properties'];
            }
          }
        }        
      } elseif ( in_array( "h-feed", $mf["type"]) ) {
        $temp = array("items" => $mf['children']);
        return webmention_hentry_walker($temp, $target);
      }
    }
  }
  
  return false;
}


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

    // We may find rel="http://webmention.org/" but an incomplete pingback URL
    if ( $webmention_server_url_len > 0 ) { // We got it!
      return $webmention_server_url;
    } 
  }

  return false;
}
