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

    // check if source really links to target
    if (!strpos($contents, $target)) {
      status_header(400);
      echo "Can't find target link.";
      exit;
    }

    status_header(200);

    do_action( 'webmention', $contents, $source, $target, $post );
    exit;
  }

  /**
   * Save the webmention as comment
   *
   * @param string $contents the HTML of the source
   * @param string $source the source URL
   * @param string $target the target URL
   * @param WP_Post $post the WordPress post object
   */
  public static function save_comment( $contents, $source, $target, $post ) {
    $title = "John Doe";
    $text = "";

    if (preg_match("/<title>(.+)<\/title>/i", $contents, $match))
      $title = trim($match[1]);

    //Original source by driedfruit: https://github.com/driedfruit/php-pingback/
    $pos = strpos($contents, $target);

    $left = substr($contents, 0, $pos);
    $right = substr($contents, $pos + $target);
    $gl = strrpos($left, '>', -512) + 1;/* attempt to land */
    $gr = strpos($right, '<', 512);  /* on tag boundaries */
    $nleft = substr($left, $gl);
    $nright = substr($right, 0, $gr);

    /* Glue them and strip_tags (and remove excessive whitepsace) */
    $nstr = $nleft.$nright;
    $nstr = strip_tags($nstr);
    $nstr = str_replace(array("\n","\t")," ", $nstr);

    /* Take 120 chars from the CENTER of our current string */
    $fat = strlen($nstr) - 120;
    if ($fat > 0) {
      $lfat = $fat / 2;
      $rfat = $fat - $lfat;
      $nstr = substr($nstr, $lfat);
      $nstr = substr($nstr, 0, -$rfat);
    }

    /* Trim a little more and add [...] on the sides */
    $nstr = trim($nstr);
    if ($nstr) $context = preg_replace('#^.+?(\s)|(\s)\S+?$#', '\\2[&#8230;]\\1', $nstr);

    // generate comment
    $source = wp_slash( $source );

    $comment_post_ID = (int) $post->ID;
    $comment_author = wp_slash($title);
    $comment_author_email = '';
    $comment_author_url = $source;
    $comment_content = wp_slash($context);
    $comment_type = 'webmention';

    $commentdata = compact('comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_content', 'comment_type');

    $comment_ID = wp_new_comment($commentdata);
    do_action('webmention_post', $comment_ID);
  }

  /**
   * Send webmentions
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
  public static function publish_post_hook( $post_ID ) {
    // get source url
    $source = get_permalink($post_ID);

    // get post
    $post = get_post($post_ID);

    // Find all external links in the source
    if (preg_match_all("/<a[^>]+href=.(https?:\/\/[^'\"]+)/i", $post->post_content, $matches)) {
      $links = apply_filters('webmention_links', array_unique($matches[1]));

      foreach ($links as $target) {
        // @todo check response
        $data = self::send_webmention($source, $target);
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

// a pseudo hook so you can run a do_action('send_webmention') instead of calling WebMentionPlugin::send_webmention
add_action('send_webmention', array('WebMentionPlugin', 'send_webmention'));

add_filter('query_vars', array('WebMentionPlugin', 'query_var'));
add_action('parse_query', array('WebMentionPlugin', 'parse_query'));

add_action('wp_head', array('WebMentionPlugin', 'html_header'), 99);
add_action('send_headers', array('WebMentionPlugin', 'http_header'));

add_action('publish_post', array('WebMentionPlugin', 'publish_post_hook'));

add_action('webmention', array('WebMentionPlugin', 'save_comment'), 10, 4);