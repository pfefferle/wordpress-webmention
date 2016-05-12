=== Webmention ===
Contributors: pfefferle, dshanske
Donate link: http://14101978.de
Tags: webmention, pingback, trackback, linkback
Requires at least: 4.4
Tested up to: 4.5
Stable tag: 2.5.0
License: MIT
License URI: http://opensource.org/licenses/MIT

Webmention for WordPress!

== Description ==

[Webmention](http://webmention.net/) is a simple way to notify any URL when you link to it on your site. From the perspective of the receiver, it is a way to request notifications when other sites link to it. 

To further enhance the display of webmentions, we recommend you install [Semantic Linkbacks](http://wordpress.org/plugins/semantic-linkbacks). This optional plugin parses the retrieved content for 
microformats to give better context to webmentions as well as trackbacks and pingbacks.

== Frequently Asked Questions ==

= What are Webmentions? =

[Webmention](http://webmention.net) is a simple way to automatically notify any URL when you link to it on your site. From the receivers perpective, it's a way to request notification when other sites link to it. 

= That Sounds Like a Pingback or a Trackback = 

Webmention is an update/replacement for Pingback or Trackback. Unlike the older protocols, the specification has a working draft with the W3C as well as an active community of individuals using it on their sites.

= How do Webmentions Look on My Site? =

By default, a webmention will display as 'This was mentioned on Site Title'. We recommend you install [Semantic Linkbacks](http://wordpress.org/plugins/semantic-linkbacks) which has the ability to 
parse the remote content to try and retrieve the author name, author avatar, and improved context, even allowing properly marked up content text to be displayed as a comment to your post. 

= How can I send Webmentions =

Activate sending Webmentions by checking the "Attempt to notify any blogs linked to from the article" option on the Settings --> Discussion page in WordPress.

You can use the `send_webmention($source, $target)` function and pass a source and a target or you can fire an action like `do_action('send_webmention', $source, $target)`.

= How can I handle Homepage-Webmentions =

Webmentions should be allowed on all URLs of a blog. The plugin currently supports only Webmentions on
posts or pages, but it offers a way to add support for other types like homepages or archive pages.
You can create a default post/page to show collect all mentions that are not comments on a post or a page. 
The plugin provides a simple filter for that:

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
`webmention_request` action and the `synchronous_request_handler`.

== Changelog ==

Project maintined on github at [pfefferle/wordpress-webmention](https://github.com/pfefferle/wordpress-webmention).

= 3.0.0 =

* Major Rewrite to Specification
* Verify Target Before Source
* Use Comment_Query to Check for Dupes
* Pass Source to Commentdata array to match pingbacks
* Add separate action for update vs post
* Add Filter for Source Verification to Allow Different Verification Criteria by Content Type
* Update README
* Protection against Amplification Attacks
* Pass Optional Additional Parameters to Hander
* Verify Target Points to Site
* Verify URL is valid
* Add Last Modified date metadata to updated webmentions
* Add User Agent for verification requests
* Split receiver and sender into individual files
* New action hook for generating log messages

= 2.5.0 =

* add salmon/crossposting-extension support (props @singpolyma)
* disable self-pings via settings
* do not unapprove already-approved webmention (props @singpolyma)
* some code improvements

= 2.4.0 =

* switched to WordPress Coding Standard

= 2.3.4 =

* some fixes and improvements

= 2.3.3 =

* added filter for webmention endpoint (to add/require additional paramaters: <https://github.com/pfefferle/wordpress-webmention/issues/39> or <https://github.com/pfefferle/wordpress-webmention/pull/41>)

= 2.3.2 =

* added more params to `webmention_post_send` (props to @snarfed)
* removed rescedule of webmentions (props to @snarfed)

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
