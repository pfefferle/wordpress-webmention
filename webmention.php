<?php
/*
 Plugin Name: WebMention
 Plugin URI: https://github.com/pfefferle/wordpress-webmention
 Description: Webmention support for WordPress posts
 Author: pfefferle
 Author URI: http://notizblog.org/
 Version: 2.0.0-dev
*/

/**
 * WebMention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class WebMentionPlugin {

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
   * Parse the webfinger request and render the document.
   *
   * @param WP $wp WordPress request context
   *
   * @uses apply_filters() Calls 'webfinger' on webfinger data array
   * @uses do_action() Calls 'webfinger_render' to render webfinger data
   */
  public static function parse_query($wp) {
    // check if it is a webfinger request or not
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

    // @todo check if source links target

    do_action( 'webmention_inbox', $contents, $source, $target, $post );
    exit;
  }

  /**
   * Send webmentions
   *
   * @param string $source source url
   * @param string $target target url
   * @return array of results including HTTP headers
   */
  public static function send_ping($source, $target) {
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
   * The webmention autodicovery meta-tags
   */
  public static function html_header() {
    echo '<link rel="http://webmention.org/" href="'.site_url("?webmention=endpoint").'" />'."\n";
    echo '<link rel="webmention" href="'.site_url("?webmention=endpoint").'" />'."\n";
  }

  /**
   * The webmention autodicovery http-header
   */
  public static function http_header() {
    header('Link: <'.site_url("?webmention=endpoint").'>; rel="http://webmention.org/"', false);
    header('Link: <'.site_url("?webmention=endpoint").'>; rel="webmention"', false);
  }

  /**
   * Send webmention
   *
   * @param array $links Links to ping
   * @param array $punk Pinged links
   * @param int $id The post_ID
   */
  public static function pre_ping_hook( $links, $pung, $post_ID ) {
    // get source url
    $source = get_permalink($post_ID);

    // get post
    $post = get_post($post_ID);

    // Find all external links in the source
    if (preg_match_all("/<a[^>]+href=.(https?:\/\/[^'\"]+)/i", $post->post_content, $matches)) {
      $links = apply_filters('webmention_links', array_unique($matches[1]));

      foreach ($links as $target) {
        // @todo check response
        $data = self::send_ping($source, $target);
      }
    }
  }

  /**
   * Finds a webmention server URI based on the given URL.
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

    //Do not search for a webmention server on our own uploads
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
          if (preg_match("/<(https?:\/\/[^>]+)>;\s+rel\s?=\s?[\"\']?http:\/\/webmention.org\/?[\"\']?/i", $link, $result))
            return $result[1];

          if (preg_match("/<(https?:\/\/[^>]+)>;\s+rel\s?=\s?[\"\']?webmention?[\"\']?/i", $link, $result))
            return $result[1];
        }
      } else {
        if (preg_match("/<(https?:\/\/[^>]+)>;\s+rel\s?=\s?[\"\']?http:\/\/webmention.org\/?[\"\']?/i", $links, $result))
          return $result[1];

        if (preg_match("/<(https?:\/\/[^>]+)>;\s+rel\s?=\s?[\"\']?webmention?[\"\']?/i", $link, $result))
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

    // check html meta-links
    if (preg_match('/<link\s+href=[\"\']([^"\']+)[\"\']\s+rel=[\"\']webmention[\"\']\s*\/?>/i', $contents, $result)
        || preg_match('/<link\s+rel=[\"\']webmention[\"\']\s+href=[\"\']([^\"\']+)[\"\']\s*\/?>/i', $contents, $result)) {
      return $result[1];
    } elseif(preg_match('/<link\s+href=[\"\']([^\"\']+)[\"\']\s+rel=[\"\']http:\/\/webmention\.org\/?[\"\']\s*\/?>/i', $contents, $result)
        || preg_match('/<link\s+rel=[\"\']http:\/\/webmention\.org\/?[\"\']\s+href=[\"\']([^\"\']+)[\"\']\s*\/?>/i', $contents, $result)) {
      return $result[1];
    }

    return false;
  }
}

add_filter('query_vars', array('WebMentionPlugin', 'query_var'));
add_action('parse_query', array('WebMentionPlugin', 'parse_query'));

add_action('wp_head', array('WebMentionPlugin', 'html_header'), 99);
add_action('send_headers', array('WebMentionPlugin', 'http_header'));

add_action('pre_ping', array('WebMentionPlugin', 'pre_ping_hook'), 10, 3);