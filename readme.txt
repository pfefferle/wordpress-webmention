=== Webmention ===
Contributors: pfefferle, dshanske
Donate link: http://14101978.de
Tags: webmention, pingback, trackback, linkback, indieweb, comment, response
Requires at least: 4.7
Tested up to: 4.8
Stable tag: 3.4.1
License: MIT
License URI: http://opensource.org/licenses/MIT

Enable conversation across the web. When you link to a website you can send it a webmention to notify it and then that website
may display your post as a comment, like, or other response, and presto, you’re having a conversation from one site to another!


== Description ==

A [Webmention](http://www.w3.org/TR/webmention/) is a notification that one URL links to another. Sending a Webmention is not limited to blog posts, and can be used for additional kinds of content and responses as well.

For example, a response can be an RSVP to an event, an indication that someone "likes" another post, a "bookmark" of another post, and many others. Webmention enables these interactions to happen across different websites, enabling a distributed social web.

The Webmention plugin supports the webmention protocol, giving you support for sending and receiving webmentions. It offers a simple built in presentation. To further enhance the presentation, you can install Semantic Linkbacks.

== Frequently Asked Questions ==

= What are Webmentions? =

[Webmention](http://www.w3.org/TR/webmention/) is a simple way to automatically notify any URL when you link to it on your site. From the receivers perpective, it's a way to request notification when other sites link to it.

= That Sounds Like a Pingback or a Trackback =

Webmention is an update/replacement for Pingback or Trackback. Unlike the older protocols, the specification is recommended by the W3C as well as an active community of individuals using it on their sites.

= How can I send and receive Webmentions? =

On the Settings --> Discussion Page in WordPress:

* Activate sending Webmentions by checking the "Attempt to notify any blogs linked to from the article" option
* Activate receiving Webmentions by checking the "Allow link notifications from other blogs (pingbacks and trackbacks) on new articles" option.
* Set a page to redirect homepage mentions to. This will automatically enable webmentions for that page.
* WordPress disables notification to pages by default. Check the Enable Webmentions for Pages option to enable this.
* If you want to enable a webmention form in the comment section, check the box.

You can use the `send_webmention($source, $target)` function and pass a source and a target or you can fire an action like `do_action('send_webmention', $source, $target)`.

[vimeo https://vimeo.com/85217592]
-- Video by [Andy Sylvester](http://andysylvester.com/2014/01/27/working-with-webmention-video/)

= How do I support Webmentions for my custom post type? =

When declaring your custom post type, add post type support for webmentions by either including it in your register_post_type entry or adding it later using add_post_type_support. This will also add support for receiving pingbacks and trackbacks as WordPress cannot currently distinguish between different linkback types.

= How can I handle Webmentions to my Homepage or Archive Pages? =

Webmentions should be allowed on all URLs of a blog, however WordPress does not support this. The plugin currently supports only
Webmentions on posts by default, but has a setting to enable for pages and allows you to set a page to receive homepage mentions.

It is very simple to add support for archives and other parts of your site that do not support them. You can provide a post/page to show collect mentions. The plugin provides a simple filter for that:

    function handle_other_webmentions($id, $target) {
      // do nothing if id is set
      if ($id) {
        return $id;
      }

      // return "default" id if plugin can't find a post/page
      return 9247;
    }
    add_filter("webmention_post_id", "handle_other_webmentions", 10, 2);

= Will a caching plugin affect my ability to use this? =

The URL for the webmention endpoint, which you can view in the source of your pages, should be excluded from any server or plugin caching.

As Webmention uses the REST API endpoint system, most up to date caching plugins should exclude it by default.

== Changelog ==

Project and support maintained on github at [pfefferle/wordpress-webmention](https://github.com/pfefferle/wordpress-webmention).

= 3.4.1 =

* Add filter to allow setting of webmention form text
* Move register settings to init due new default options not being set if admin only
* Add `edit_webmention` hook due comment array filtering
* Display Webmention Meta on Edit Comment page

= 3.4.0 =

* Added settings link
* Added link to Homepage Webmention page
* Enable pings for Homepage Webmentions

= 3.3.0 =

* Add setting for homepage mentions (thanks @dshanske)
* Remove deprecated functions due 4.8 release

= 3.2.1 =

* moved endpoint discovery to functions.php
* added missing i18n strings
* removed polyfill

= 3.2.0 =

* Enable option for page support
* Allow custom post types to declare support for webmentions as a feature which will enable pings.
* Remove new meta properties from being added during preprocessing as these are added after Semantic Linkbacks Enhancement.
* Move new meta properties to being built into webmention code
* Store webmention source in comment meta but fall back to checking `comment_author_url` if not set.
* Store webmention creation time in comment meta as comment time is overridden by Semantic Linkbacks allowing to determine if a comment has been modified.

= 3.1.1 =

* URLEncode/Decode source and target
* Webmention Comment Type now declares support for avatars
* Meta keys are now registered for `webmention_target_url` and `webmention_target_fragment`
* Target URL is stored instead of derived from the permalink to ensure permanance
* Target fragment is stored to support fragmentions. Can also suport comments when reply is to a comment.

= 3.1.0 =

* added page support (server and client)
* moved `webmention_post_id` filter to a global function (thanks @dshanske)
* fixed https://wordpress.org/support/topic/form-for-entering-manual-pings-stays-on/
* fixed some typos

= 3.0.1 =

* Show endpoint discovery on every page again, to prevent several problems.

= 3.0.0 =

* Plugin refactored to use API infrastructure.
* Visiting the endpoint in a web browser now returns a webmention form.
* Plugin now compliant with draft specification although remains synchronous.
* Deprecation of webmention_title and webmention_content filters in favor of a single targeted webmention comment data filter.
* webmention_post_send action now fires on all attempts to send a webmention instead of only successful ones. Allows for logging functions to be added.
* Supports adding additional parameters when sending webmentions
* Fix incompatibility with Ultimate Category Excluder plugin.

= 2.6.0 =

* removed duplicate request for HTML via get_meta_tags
* refactoring
* limits to same domain

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

* nicer feedback for the Webmention endpoint

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

= 3.0.0 =

This update brings the plugin into compliance with the draft standard. As a result, some filters and
actions have changed. Please check any dependent code before updating.

= 2.0.0 =

This plugin doesn't support the microformts stuff mentioned in the IndieWebCamp Wiki.
To enable semantic linkbacks you have to use <https://github.com/pfefferle/wordpress-semantic-linkbacks>
