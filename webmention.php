<?php
/*
 Plugin Name: WebMention
 Plugin URI: https://github.com/pfefferle/wordpress-webmention
 Description: Webmention support for WordPress posts
 Author: pfefferle
 Author URI: http://notizblog.org/
 Version: 2.1.4
*/

// check if class already exists
if (!class_exists("WebMentionPlugin")) :

/**
 * a wrapper for WebMentionPlugin::send_webmention
 *
 * @param string $source source url
 * @param string $target target url
 * @return array of results including HTTP headers
 */
function send_webmention($source, $target) {
  return WebMentionPlugin::send_webmention($source, $target);
}

// initialize plugin
add_action('init', array( 'WebMentionPlugin', 'init' ));

/**
 * WebMention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class WebMentionPlugin {

  /**
   * Initialize the plugin, registering WordPress hooks.
   */
  public static function init() {
    // a pseudo hook so you can run a do_action('send_webmention')
    // instead of calling WebMentionPlugin::send_webmention
    add_action('send_webmention', array('WebMentionPlugin', 'send_webmention'), 10, 2);

    add_filter('query_vars', array('WebMentionPlugin', 'query_var'));
    add_action('parse_query', array('WebMentionPlugin', 'parse_query'));

    add_action('wp_head', array('WebMentionPlugin', 'html_header'), 99);
    add_action('send_headers', array('WebMentionPlugin', 'http_header'));

    add_action('publish_post', array('WebMentionPlugin', 'publish_post_hook'));

    add_filter('webmention_title', array('WebMentionPlugin', 'default_title_filter'), 10, 4);
    add_filter('webmention_content', array('WebMentionPlugin', 'default_content_filter'), 10, 4);
  }

  /**
   * Adds some query vars
   *
   * @param array $vars
   * @return array
   */
  public static function query_var($vars) {
    $vars[] = 'webmention';

    return $vars;
  }

  /**
   * Parse the WebMention request and render the document.
   *
   * @param WP $wp WordPress request context
   * @uses do_action() Calls 'webmention'
   */
  public static function parse_query($wp) {
    // check if it is a webmention request or not
    if (!array_key_exists('webmention', $wp->query_vars)) {
      return;
    }

    $content = file_get_contents('php://input');
    parse_str($content);

    header('Content-Type: text/plain; charset=' . get_option('blog_charset'));

    // check if source url is transmitted
    if (!isset($source)) {
      status_header(400);
      echo "'source' is missing";
      exit;
    }

    // check if target url is transmitted
    if (!isset($target)) {
      status_header(400);
      echo "'target' is missing";
      exit;
    }

    $post_ID = url_to_postid($target);

    // check if post id exists
    if ( !$post_ID ) {
      status_header(404);
      echo "Specified target URL not found.";
      exit;
    }

    // check if pings are allowed
    if ( !pings_open($post_ID) ) {
      status_header(500);
      echo "Pings are disabled for this post";
      exit;
    }

    $post_ID = (int) $post_ID;
    $post = get_post($post_ID);

    // check if post exists
    if ( !$post ) {
      status_header(404);
      echo "Specified target URL not found.";
      exit;
    }

    $response = wp_remote_get( $source, array('timeout' => 100) );

    // check if source is accessible
    if ( is_wp_error( $response ) ) {
      status_header(400);
      echo "Source URL not found.";
      exit;
    }

    $contents = wp_remote_retrieve_body( $response );

    // check if source really links to target
    if (!strpos($contents, $target)) {
      status_header(400);
      echo "Can't find target link.";
      exit;
    }

    status_header(200);

    // filter title or content of the comment
    $title = apply_filters( "webmention_title", "", $contents, $target, $source );
    $content = apply_filters( "webmention_content", "", $contents, $target, $source );

    // generate comment
    $comment_post_ID = (int) $post->ID;
    $comment_author = wp_slash($title);
    $comment_author_email = '';
    $comment_author_url = esc_url_raw($source);
    $comment_content = wp_slash($content);
    // change this if your theme can't handle the webmention comment type
    $comment_type = apply_filters('webmention_comment_type', 'webmention');
    $comment_parent = null;

    $commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_content', 'comment_type', 'comment_parent');

    // check dupes
    global $wpdb;
  	$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_author_url = %s", $comment_post_ID, $comment_author_url) );

    // check result
    if (!empty($comments)) {
      $comment = $comments[0];
    } else {
      $comment = null;
    }

    // update or save webmention
    if ($comment) {
      $commentdata['comment_ID'] = $comment->comment_ID;
      // save comment
      wp_update_comment($commentdata);
      $comment_ID = $comment->comment_ID;
    } else {
      // save comment
      $comment_ID = wp_new_comment($commentdata);
    }

    echo "WebMention received... Thanks :)";

    do_action( 'webmention_post', $comment_ID );
    exit;
  }

  /**
   * try to make a nice comment
   *
   * @param string $context the comment-content
   * @param string $contents the HTML of the source
   * @param string $target the target URL
   * @param string $source the source URL
   *
   * @return string the filtered content
   */
  public static function default_content_filter( $content, $contents, $target, $source ) {
    // get post format
    $post_ID = url_to_postid($target);
    $post_format = get_post_format($post_ID);

    // replace "standard" with "Article"
    if (!$post_format || $post_format == "standard") {
      $post_format = "Article";
    } else {
      $post_formatstrings = get_post_format_strings();
      // get the "nice" name
      $post_format = $post_formatstrings[$post_format];
    }

    $host = parse_url($source, PHP_URL_HOST);

    // strip leading www, if any
    $host = preg_replace("/^www\./", "", $host);

    // generate default text
    $content = sprintf(__('This %s was mentioned on <a href="%s">%s</a>', 'webmention'), $post_format, esc_url($source), $host);

    return $content;
  }

  /**
   * try to make a nice title (username)
   *
   * @param string $$title the comment-title (username)
   * @param string $contents the HTML of the source
   * @param string $target the target URL
   * @param string $source the source URL
   *
   * @return string the filtered title
   */
  public static function default_title_filter( $title, $contents, $target, $source ) {
    $meta_tags = @get_meta_tags($source);

    // use meta-author
    if ($meta_tags && is_array($meta_tags) && array_key_exists('author', $meta_tags)) {
      $title = $meta_tags['author'];
    // use title
    } elseif (preg_match("/<title>(.+)<\/title>/i", $contents, $match)) {
      $title = trim($match[1]);
    // or host
    } else {
      $host = parse_url($source, PHP_URL_HOST);

      // strip leading www, if any
      $title = preg_replace("/^www\./", "", $host);
    }

    return $title;
  }

  /**
   * Send WebMentions
   *
   * @param string $source source url
   * @param string $target target url
   * @return array of results including HTTP headers
   */
  public static function send_webmention($source, $target) {
    $webmention_server_url = self::discover_endpoint( $target );

    $args = array(
              'body' => 'source='.urlencode($source).'&target='.urlencode($target)
            );

    if ($webmention_server_url) {
      return wp_remote_post( $webmention_server_url, $args );
    }

    return false;
  }

  /**
   * The WebMention autodicovery meta-tags
   */
  public static function html_header() {
    // backwards compatibility with v0.1
    echo '<link rel="http://webmention.org/" href="'.site_url("?webmention=endpoint").'" />'."\n";
    echo '<link rel="webmention" href="'.site_url("?webmention=endpoint").'" />'."\n";
  }

  /**
   * The WebMention autodicovery http-header
   */
  public static function http_header() {
    // backwards compatibility with v0.1
    header('Link: <'.site_url("?webmention=endpoint").'>; rel="http://webmention.org/"', false);
    header('Link: <'.site_url("?webmention=endpoint").'>; rel="webmention"', false);
  }

  /**
   * Send WebMentions if new Post was saved
   *
   * @param array $links Links to ping
   * @param array $punk Pinged links
   * @param int $id The post_ID
   */
  public static function publish_post_hook( $post_ID ) {
    // get source url
    $source = get_permalink($post_ID);

    // get post
    $post = get_post($post_ID);

    // initialize links array
    $links = array();

    // Find all external links in the source
    if (preg_match_all("/<a[^>]+href=.(https?:\/\/[^'\"]+)/i", $post->post_content, $matches)) {
      $links = $matches[1];
    }

    // filter links
    $targets = apply_filters('webmention_links', $links, $post_ID);
    $targets = array_unique($targets);

    foreach ($targets as $target) {
      // @todo check response
      $data = self::send_webmention($source, $target);
    }
  }

  /**
   * Finds a WebMention server URI based on the given URL.
   *
   * Checks the HTML for the rel="http://webmention.org/" link and http://webmention.org/ headers. It does
   * a check for the http://webmention.org/ headers first and returns that, if available. The
   * check for the rel="http://webmention.org/" has more overhead than just the header.
   *
   * @param string $url URL to ping.
   * @param int $deprecated Not Used.
   * @return bool|string False on failure, string containing URI on success.
   */
  public static function discover_endpoint( $url ) {
    /** @todo Should use Filter Extension or custom preg_match instead. */
    $parsed_url = parse_url($url);

    if ( ! isset( $parsed_url['host'] ) ) // Not an URL. This should never happen.
      return false;

    //Do not search for a WebMention server on our own uploads
    $uploads_dir = wp_upload_dir();
    if ( 0 === strpos($url, $uploads_dir['baseurl']) )
      return false;

    $response = wp_remote_head( $url, array( 'timeout' => 100, 'httpversion' => '1.0' ) );

    if ( is_wp_error( $response ) )
      return false;

    // check link header
    if ( $links = wp_remote_retrieve_header( $response, 'link' ) ) {
      if ( is_array($links) ) {
        foreach ($links as $link) {
          if (preg_match("/<(https?:\/\/[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(.org)?\/?[\"\']?/i", $link, $result))
            return $result[1];
        }
      } else {
        if (preg_match("/<(https?:\/\/[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(.org)?\/?[\"\']?/i", $links, $result))
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

    // boost performance and use alreade the header
    $header = substr( $contents, 0, stripos( $contents, '</head>' ) );

    // check html meta-links
    if (preg_match('/<link\s+rel=[\"\'](http:\/\/)?webmention(.org)?[\"\']\s+href=[\"\']([^\"\']+)[\"\']\s*\/?>/i', $header, $result)) {
      return $result[3];
    }

    if (preg_match('/<link\s+href=[\"\']([^"\']+)[\"\']\s+rel=[\"\'](http:\/\/)?webmention(.org)?[\"\']\s*\/?>/i', $header, $result)) {
      return $result[1];
    }

    return false;
  }
}

// end check if class already exists
endif;