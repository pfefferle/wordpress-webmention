<?php
/*
 Plugin Name: Webmention
 Plugin URI: https://github.com/pfefferle/wordpress-webmention
 Description: Webmention support for WordPress posts/comments
 Author: pfefferle
 Author URI: http://notizblog.org/
 Version: 1.0.0-dev
*/

require_once 'vendor/mf2/Parser.php';
require_once 'vendor/webignition/AbsoluteUrlDeriver/AbsoluteUrlDeriver.php';

require_once 'vendor/webignition/Url/Url.php';
require_once 'vendor/webignition/Url/Parser.php';
require_once 'vendor/webignition/Url/Query/Query.php';
require_once 'vendor/webignition/Url/Query/Parser.php';
require_once 'vendor/webignition/Url/Path/Path.php';
require_once 'vendor/webignition/Url/Host/Host.php';

require_once 'vendor/webignition/NormalisedUrl/NormalisedUrl.php';
require_once 'vendor/webignition/NormalisedUrl/Normaliser.php';
require_once 'vendor/webignition/NormalisedUrl/Query/Normaliser.php';
require_once 'vendor/webignition/NormalisedUrl/Query/Query.php';
require_once 'vendor/webignition/NormalisedUrl/Path/Normaliser.php';
require_once 'vendor/webignition/NormalisedUrl/Path/Path.php';

use mf2\Parser;

function webmention_parse_query($wp_query) {
  if (isset($wp_query->query_vars['webmention'])) {
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

    $contents = wp_remote_retrieve_body( $response );
    
    do_action( 'webmention_ping_client', $contents, $source, $target, $post );
    exit;
  }
}
add_action('parse_query', 'webmention_parse_query');

/**
 * merges the mf2 content into a WordPress comment
 * 
 * @param string $html the html source of the target url
 * @param string $source the source url
 * @param string $target the pinged target
 * @param obj $post the post object
 * @param array|null the commentmeta array
 */
function webmention_to_comment( $html, $source, $target, $post, $commentdata = null ) {
  global $wpdb;
  
  // check commentdata
  if ( $commentdata == null ) {
    $comment_post_ID = (int) $post->ID;
    $commentdata = array('comment_post_ID' => $comment_post_ID, 'comment_author' => '', 'comment_author_url' => '', 'comment_author_email' => '', 'comment_content' => '', 'comment_type' => '', 'comment_ID' => '');
    
    if ( $comments = get_comments( array('meta_key' => 'webmention_source', 'meta_value' => $source) ) ) {
      $comment = $comments[0];
      $commentdata['comment_ID'] = $comment->comment_ID;
    }
  }
  
  // check if there is a parent comment
  if ( $query = parse_url($target, PHP_URL_QUERY) ) {
    parse_str($query);
    if (isset($replytocom) && get_comment($replytocom)) {
      $commentdata['comment_parent'] = $replytocom;
    }
  }
  
  // reset content type
  $commentdata['comment_type'] = '';
  
  // parse source html
  $parser = new Parser( $html );
  $result = $parser->parse(true);
  
  // search for a matchin h-entry
  $hentry = webmention_hentry_walker($result, $target);
  
  if (!$hentry) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array("error"=> "no_link_found"));
    exit;
  }
  
  // try to find some content
  // @link http://indiewebcamp.com/comments-presentation
  if (isset($hentry['summary'])) {
    $commentdata['comment_content'] = $wpdb->escape($hentry['summary'][0]);
  } elseif (isset($hentry['content'])) {
    $commentdata['comment_content'] = $wpdb->escape($hentry['content'][0]);
  } elseif (isset($hentry['name'])) {
    $commentdata['comment_content'] = $wpdb->escape($hentry['name'][0]);
  } else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array("error"=> "no_content_found"));
    exit;
  }
  
  // set the right date
  if (isset($hentry['published'])) {
    $time = strtotime($hentry['published'][0]);
    $commentdata['comment_date'] = date("Y-m-d H:i:s", $time);
  } elseif (isset($hentry['updated'])) {
    $time = strtotime($hentry['updated'][0]);
    $commentdata['comment_date'] = date("Y-m-d H:i:s", $time);
  }

  $author = null;
  
  // check if h-card has an author
  if ( isset($hentry['author']) && isset($hentry['author'][0]['properties']) ) {
    $author = $hentry['author'][0]['properties'];
  }
  
  // else get representative hcard
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
	
  // if author is present use the informations for the comment
  if ($author) {
    if (isset($author['name'])) {
      $commentdata['comment_author'] = $wpdb->escape($author['name'][0]);
    }
    
    if (isset($author['email'])) {
      $commentdata['comment_author_email'] = $wpdb->escape($author['email'][0]);
    }
    
    if (isset($author['url'])) {
      $commentdata['comment_author_url'] = $wpdb->escape($author['url'][0]);
    }
  }
  
  // check if it is a new comment or an update
  if ( $commentdata['comment_ID'] ) {
    wp_update_comment($commentdata);
    $comment_ID = $commentdata['comment_ID'];
  } else {
    $comment_ID = wp_insert_comment($commentdata);
  }
  
  // add source url as comment-meta
  add_comment_meta( $comment_ID, "webmention_source", $source, true );
  
  if (isset($author['photo'])) {
    // add photo url as comment-meta
    add_comment_meta( $comment_ID, "webmention_avatar", $author['photo'][0], true );
  }
}
add_action( 'webmention_ping_client', 'webmention_to_comment', 15, 4 );

/**
 * replaces the classic WordPress pingback parser with 
 * a more usefull mf2 parser
 *
 * @param int $comment_ID the id of the saved comment
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

  $html = wp_remote_retrieve_body( $response );

  webmention_to_comment( $html, $commentdata['comment_author_url'], $target, $post, $commentdata );
}
add_action( 'pingback_post', 'webmention_pingback_fix', 90, 1 );

/**
 * 
 */
function webmention_debug( $html, $source, $target ) {
  wp_mail(get_bloginfo('admin_email'), 'someone mentioned you', print_r($source, true));
}
add_action( 'webmention_ping_client', 'webmention_debug', 10, 3 );

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
function webmention_add_header() {
  echo '<link rel="http://webmention.org/" href="'.site_url("?webmention=endpoint").'" />'."\n";
}
add_action("wp_head", "webmention_add_header");

/**
 * adds the webmention header
 */
function webmention_template_redirect() {
  header('Link: <'.site_url("?webmention=endpoint").'>; rel="http://webmention.org/"', false);
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
 * send webmention
 *
 * @param array $links Links to ping
 * @param array $punk Pinged links
 * @param int $id The post_ID
 */
function webmention_ping( $links, $pung, $post_ID ) {  
  // get source url
  $source = get_permalink($post_ID);
  $source = get_permalink($post_ID);

  // get post
  $post = get_post($post_ID);

  // parse source html
  $parser = new Parser( "<div class='h-dummy'>".$post->post_content."</div>" );
  $mf_array = $parser->parse(true);
  
  // some basic checks
  if ( !is_array( $mf_array ) )
    return false;
  if ( !isset( $mf_array["items"] ) )
    return false;
  if ( count( $mf_array["items"] ) == 0 )
    return false;
  
  // load properties of dummy html
  $dummy = $mf_array["items"][0];
  
  // check post for some supported urls
  foreach ( (array) $dummy['properties'] as $key => $values ) {
    if (in_array($key, webmention_get_supported_url_types())) {
      foreach ($values as $value) {
        // @todo check response
        $data = webmention_send_ping($source, $value);
      }
    }
  }
}
add_action( 'pre_ping', 'webmention_ping', 10, 3 );

/**
 * send webmentions on new comments
 *
 * @param int $id the post id
 * @param obj $comment the comment object
 */
function webmention_comment_post($id) {
  $comment = get_comment($id);
  if ($comment->comment_parent) {
    $target = get_comment_meta($comment->comment_parent, 'webmention_source', true);
    
    if ($target) {
      $source = add_query_arg( 'replytocom', $comment->comment_ID, get_permalink($comment->comment_post_ID) );
      $data = webmention_send_ping($source, $target);
    }
  }
}
add_action('comment_post', 'webmention_comment_post');

/**
 * workaround because WordPress sends no pings on custom post types
 *
 * @param int $id the post id
 * @param obj $post the post object
 */
function webmention_insert_post($id) {
  webmention_ping(array(), array(), $id);
}
add_action('publish_webmention_reply', 'webmention_insert_post');

/**
 * helper to find the correct h-entry node
 * 
 * @param array $mf_array the parsed microformats array
 * @param string $target the target url
 * @return array|false the h-entry node or false
 */
function webmention_hentry_walker( $mf_array, $target ) {
  // some basic checks
  if ( !is_array( $mf_array ) )
    return false;
  if ( !isset( $mf_array["items"] ) )
    return false;
  if ( count( $mf_array["items"] ) == 0 )
    return false;
  
  // iterate array
  foreach ($mf_array["items"] as $mf) {    
    if ( isset( $mf["type"] ) ) {
      // only h-entries are important
      if ( in_array( "h-entry", $mf["type"] ) ) {
        if ( isset( $mf['properties'] ) ) {
          // check properties if target urls was mentioned
          foreach ($mf['properties'] as $key => $values) {
            // check u-* params at first      
            if ( in_array( $key, webmention_get_supported_url_types() )) {
              foreach ($values as $value) {
                if ($value == $target) {
                  return $mf['properties'];
                }
              }
            // check content as fallback
            } elseif ( in_array( $key, array("content", "summary", "name")) && preg_match_all("|<a[^>]+?".preg_quote($target, "|")."[^>]*>([^>]+?)</a>|", $values[0], $context) ) {
              return $mf['properties'];
            }
          }
        }
      // if root is h-feed, than hop into the "children" array to find some
      // h-entries
      } elseif ( in_array( "h-feed", $mf["type"]) && isset($mf['children']) ) {
        $temp = array("items" => $mf['children']);
        return webmention_hentry_walker($temp, $target);
      }
    }
  }
  
  return false;
}

/**
 * replaces the default avatar with the webmention uf2 photo
 *
 * @param string $avatar the avatar-url
 * @param int|string|object $id_or_email A user ID, email address, or comment object
 * @param int $size Size of the avatar image
 * @param string $default URL to a default image to use if no avatar is available
 * @param string $alt Alternative text to use in image tag. Defaults to blank
 * @return string new avatar-url
 */
function webmention_get_avatar($avatar, $id_or_email, $size, $default, $alt = '') {
  if (!is_object($id_or_email) || !isset($id_or_email->comment_type) || !get_comment_meta($id_or_email->comment_ID, 'webmention_avatar', true)) {
    return $avatar;
  }
  
  // check if comment has a webfinger-avatar
  $webfinger_avatar = get_comment_meta($id_or_email->comment_ID, 'webmention_avatar', true);
    
  if (!$webfinger_avatar) {
    return $avatar;
  }
  
  if ( false === $alt )
    $safe_alt = '';
  else
    $safe_alt = esc_attr( $alt );
  
  $avatar = "<img alt='{$safe_alt}' src='{$webfinger_avatar}' class='avatar avatar-{$size} photo avatar-webmention' height='{$size}' width='{$size}' />";
  return $avatar;
}
add_filter('get_avatar', 'webmention_get_avatar', 10, 5);

/**
 * replace comment url with webmention source
 *
 * @param string $link the link url
 * @param obj $comment the comment object
 * @param array $args a list of arguments to generate the final link tag
 * @return string the webmention source or the original comment link
 */
function webmention_get_comment_link($link, $comment, $args) {
  if ( $source = get_comment_meta($comment->comment_ID, 'webmention_source', true) ) {
    return $source;
  }
  
  return $link;
}
//add_filter( 'get_comment_link', 'webmention_get_comment_link', 99, 3 );

/**
 * adds a special template for single comments
 *
 * @param string $template "old" template path
 * @return string "new" template path
 */
function webmention_template_include( $template ) {
  global $wp,$wp_query; 
  $path = apply_filters("webmention_comment_template", dirname(__FILE__)."/templates/comment.php");
    
  if (isset($wp_query->query['replytocom'])) {
    return $path;
  }
  return $template;
}
add_filter( 'template_include', 'webmention_template_include' );

/**
 * all supported url types
 *
 * @return array
 */
function webmention_get_supported_url_types() {
  return apply_filters("webmention_supported_url_types", array("in-reply-to", "like", "mention"));
}

function webfinger_get_parent_source_url($comment) {
  if ($comment->comment_parent) {
    return get_comment_meta($comment->comment_parent, 'webmention_source', true);
  }
  
  return null;
}


function webmention_create_post_types() {
  register_post_type( 'webmention_reply',
	  array(
      'labels' => array(
        'name' => __( 'Replies' ),
        'singular_name' => __( 'Reply' )
			),
		  'public' => true,
		  'has_archive' => true,
      'hierarchical' => true,
      'query_var' => true,
      'rewrite' => array( 'slug' => 'replies', 'with_front' => false, 'feeds' => true, 'ep_mask' => EP_PERMALINK ),
      'supports' => array(
        'title', 'revisions', 'title', 'trackbacks', 'comments', 'post-formats', 'author', 'editor'
      )
	  )
  );
  
  flush_rewrite_rules();
}
add_action( 'init', 'webmention_create_post_types' );

/**
 * Adds custom classes to the array of post classes.
 */
function webmention_post_classes( $classes ) {
  if (get_post_type() == "webmention_reply") {
    $classes[] = 'post';
  }
  
  return $classes;
}
add_filter( 'post_class', 'webmention_post_classes' );

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

  if ( $link = wp_remote_retrieve_header( $response, 'link' ) ) {
    if (preg_match("/<(.+)>;\s+rel\s?=\s?[\"\']?http:\/\/webmention.org\/?[\"\']?/i", $link, $result))
      return $result[1];
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

    // We may find rel="http://webmention.org/" but an incomplete pingback URL
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
