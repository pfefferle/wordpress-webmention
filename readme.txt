=== WebMention ===
Contributors: pfefferle
Donate link: http://14101978.de
Tags: webmention, pingback, trackback, linkback
Requires at least: 2.7
Tested up to: 3.8
Stable tag: 2.3.1

WebMention for WordPress!

== Description ==

Enables [WebMention](http://webmention.org/) support for WordPress.

WebMention is a simple and modern alternative to the Pingback/Trackback protocol.

From the [spec](http://webmention.org/):

> Webmention is a simple way to automatically notify any URL when you link to it on your site.
> From the receivers perpective, it's a way to request notification when other sites link to it.
> It’s a modern alternative to Pingback and other forms of Linkback.

== Frequently Asked Questions ==

= What are WebMentions? =

[WebMention](http://webmention.org) is a simple way to automatically notify any URL when you link to it on your site. From the receivers perpective, it's a way to request notification when other sites link to it.

It’s a modern alternative to Pingback and other forms of Linkback.

= How can I send WebMentions =

You can use the `send_webmention($source, $target)` function and pass a source and a target or you can fire an action like `do_action('send_webmention', $source, $target)`.

= How can I handle Homepage-WebMentions =

WebMentions should be allowed on all URLs of a blog. The plugin currently supports only WebMentions on
posts or pages, but it is very simple to add support for other types like homepages or archive pages.
The easiest way is to provide some kind of a default post/page to show collect all mentions that are no
comments on a post or a page. The plugin provides a simple filter for that:

    function handle_exotic_webmentions($id, $target) {
      // do nothing if id is set
      if ($id) {
        return $id;
      }

      // return "default" id if plugin can't find a post/page
      return 9247;
    }
    add_filter("webmention_post_id", "handle_exotic_webmentions", 10, 2);

If you want to add a more complex request handler, you should take a look at the
`webmention_request` action and the `default_request_handler`.

== Changelog ==

Project maintined on github at [pfefferle/wordpress-webmention](https://github.com/pfefferle/wordpress-webmention).

= 2.3.1 =

* use error-code 403 instead of 500 if pingbacks/webmentions are disabled for a post (thanks @snarfed)
* added `webmention_comment_parent` filter

= 2.3.0 =

* nicer `title` and `content` discovery
* added post-id to `webmention_links` filter
* improved `publish_post_hook` function
* disabled flood control
* nicer response value
* some more filters/actions
* added a default request "action" to be more flexible and to handle more than mentions on posts and pages
* a lot of small fixes

= 2.2.0 =

* prevent selfpings
* added support for https and http
* optimized some methods

= 2.1.4 =

* fixed pseudo hook

= 2.1.3 =

* fixed some warnings

= 2.1.2 =

* now ready to use in a bundle

= 2.1.1 =

* nicer feedback for the WebMention endpoint

= 2.1.0 =

* nicer `title` and `content` discovery
* added post-id to `webmention_links` filter
* improved `publish_post_hook` function

= 2.0.1 =

* small fixes
* nicer excerpt extractor

= 2.0.0 =

initial release

== Installation ==

1. Upload the `webmention`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the *Plugins* menu in WordPress
3. ...and that's it :)

== Upgrade Notice ==

= 2.0.0 =

This plugin doesn't support the microformts stuff mentioned in the IndieWebCamp Wiki.
To enable semantik linkbacks you have to use <https://github.com/pfefferle/wordpress-semantic-linkbacks>
