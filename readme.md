# Webmention #
**Contributors:** [pfefferle](https://profiles.wordpress.org/pfefferle/), [dshanske](https://profiles.wordpress.org/dshanske/)  
**Donate link:** https://notiz.blog/donate/  
**Tags:** webmention, pingback, trackback, linkback, indieweb, comment, response  
**Requires at least:** 4.9  
**Tested up to:** 6.4  
**Stable tag:** 5.1.9  
**Requires PHP:** 5.6  
**License:** MIT  
**License URI:** https://opensource.org/licenses/MIT  

Enable conversation across the web. When you link to a website you can send it a Webmention to notify it and then that website
may display your post as a comment, like, or other response, and presto, you’re having a conversation from one site to another!

## Description ##

A [Webmention](https://www.w3.org/TR/webmention/) is a notification that one URL links to another. Sending a Webmention is not limited to blog posts, and can be used for additional kinds of content and responses as well.

For example, a response can be an RSVP to an event, an indication that someone "likes" another post, a "bookmark" of another post, and many others. Webmention enables these interactions to happen across different websites, enabling a distributed social web.

The Webmention plugin supports the Webmention protocol, giving you support for sending and receiving Webmentions. It offers a simple built in presentation.

## Frequently Asked Questions ##

### What are Webmentions? ###

[Webmention](https://www.w3.org/TR/webmention/) is a simple way to automatically notify any URL when you link to it on your site. From the receivers perpective, it's a way to request notification when other sites link to it.

### That Sounds Like a Pingback or a Trackback ###

Webmention is an update/replacement for Pingback or Trackback. Unlike the older protocols, the specification is recommended by the W3C as well as an active community of individuals using it on their sites.

### How can I send and receive Webmentions? ###

On the Settings --> Discussion Page in WordPress:

* On the Webmention Settings page, decide which post types you want to enable Webmentions for. By default, posts and pages.
* Set a page to redirect homepage mentions to. This will automatically enable Webmentions for that page.
* If you want to enable a Webmention form in the comment section, check the box.

You can use the `send_webmention($source, $target)` function and pass a source and a target or you can fire an action like `do_action('send_webmention', $source, $target)`.

### How do I support Webmentions for my custom post type? ###

When declaring your custom post type, add post type support for Webmentions by either including it in your `register_post_type` entry. This can also be added in the Webmention settings.

### How do I send/receive Webmentions for attachments? ###

You can enable receiving Webmentions for attachments in Webmention settings. You can enable sending Webmentions for media links in the settings. Please note that most receivers of Webmentions do not support receiving them to image, audio, and video files. In order to support receiving them on WordPress, Webmention endpoint headers would have to be added at the webserver level.

### How can I handle Webmentions to my Homepage or Archive Pages? ###

Webmentions should be allowed on all URLs of a blog, however WordPress does not support this as only posts can have comments attached to them. The plugin currently handles only Webmentions on posts and allows you to set a page to receive homepage mentions.

Even though it is not done automatically, it is very simple to add support for archives and URLs on your site by providing a post/page to show collect mentions. The plugin provides a simple filter for that.

In the below example, if there is no page returned it will send mentions to a catch-all post. You can also have unique posts per URL.

    function handle_other_webmentions($id, $target) {
      // do nothing if id is set
      if ($id) {
        return $id;
      }

      // return "default" id if plugin can't find a post/page
      return 9247;
    }
    add_filter("webmention_post_id", "handle_other_webmentions", 10, 2);

### Will a caching plugin affect my ability to use this? ###

The URL for the Webmention endpoint, which you can view in the source of your pages, should be excluded from any server or plugin caching.

As Webmention uses the REST API endpoint system, most up to date caching plugins should exclude it by default.

### Why does this plugin have settings about avatars? ###

Webmentions have the ability to act as rich comments. This includes showing avatars. If there is an avatar discovered, the URL for it will be stored. This can either be reflect something from the media library or a URL of a file. If the file is broken, it will store a local
copy of the default gravatar image.

### There are no Webmention headers on some pages of my site ###

Webmention headers are only shown if Webmentions are available for that particular URL. If you want to show it regardless, you can add below to your wp-config.php file.

    define( 'WEBMENTION_ALWAYS_SHOW_HEADERS', 1 );

### How do I customize the display of my webmentions? ###

This plugin includes several enhancements to the built-in WordPress commenting system to allow for enhancement, while allowing existing methods to offer customization. It customizes the classic defaults for WordPress to account for webmentions by using a custom comment walker that minimally changes to defaults.
By default, many themes provide a custom callback to the `wp_list_comments` function. This plugin adds several enhancements to that. For one, the custom callbacks argument is usually a string with the function name. We enhance it to behave as normal in that case, but if an array is passed, to allow specific callbacks per the key of the array, or the 'all' key as a default. This means each comment type, which would be each webmention type or otherwise, can have its own custom callback.

It introduces a new version of the default function for html5 comments, adding correct microformats2 markup, and for webmentions, a proper site citation, e.g. Bob @ Example.Com as well as a hook, `webmention_comment_metadata` which offers a comment object as the sole argument, to add arbitrary metadata. This would be overridden by any custom comment rendering done by themes.

There is an option within the plugin to show webmentions not determined to be replies or comments inline, or to display them separately as avatar only lists. The `wp_list_comments` function is overridden to allow for the `avatar_only` option, which will render this, with a second option of `overlay` to overlay an icon reflecting the reaction type. Reactions are webmention types such as like, which there is no textual component to it. If you opt to display them as comments, the text will read that the author `likes this post`.

While not all display options can be settings, we are looking to provide some simple options which could be customized in a theme if needed.

## Changelog ##

Project and support maintained on github at [pfefferle/wordpress-webmention](https://github.com/pfefferle/wordpress-webmention).

### 5.1.9 ###

* Replace `comment_link` only for Webmentions and only in the frontend

### 5.1.8 ###

* Replace `comment_link` only for Webmentions that have a source

### 5.1.7 ###

* Fix fatal error in WP parser

### 5.1.6 ###

* Allow variable to be null.

### 5.1.5 ###

* Bring back overflow option for facepile this time using the details tag
* Add html link discovery for finding WordPress REST API
* Load author page to find name/photo when only the URL is provided
* Fix timezone issue where times were not properly converted into website time
* Introduce webmentions_open function which determines if webmentions are open for a post. Currently a wrapper around pings_open
* Misc minor fixes

### 5.1.4 ###

* Fixed: avoid enqueuing Webmention's CSS stylesheet when it is not needed.
* Fixed: threaded comments support.
* Added: client URL validation.

### 5.1.3 ###

* Fix timezone issue causes exception

### 5.1.2 ###

* Remove built-in WordPress filtering in favor of plugin filtering of incoming webmentions.
* If content is long and type is a mention, try to use the summary or name insteasd of full content as the display.
* Fix issue where meta was overriding mf2
* Improve JSON-LD handler

### 5.1.1 ###

* Several Parser/Handler fixes
* Remove unnecessary loading of `comments.php`
* Some Tool updates

### 5.1.0 ###

* Add mf2 author migration
* Include spam and trash statuses for dupe check
* Update tests and make u-url attrib optional for post-type discovery
* Update dupe check
* Set created at time only for new comments
* Allow refreshing webmentions from the Bulk Actions menu
* Remove Gravatar Cache
* A lot of small improvements and fixes

### 5.0.0 ###

* Complete rewrite of the codebase
* Introduce PHP namespaces
* New parser which will fallback on the WordPress REST API, JSON-LD, or HTML meta tags if Microformats are not sufficient to render a comment.
* New debugger/test tool for Webmention Parsing under Tools
* Webmentions are no longer stored as comment type mention, but as custom comment types
* New simplified presentation code, providing for optional custom templating in future

### 4.0.9 ###

* Fix XSS issue

### 4.0.8 ###

* Add `onerror` handling also for `srcset` ( props @florianbrinkmann for testing )

### 4.0.7 ###

* Re-add `onerror` handling for broken images ( props @snarfed )

### 4.0.6 ###

* Updated requirements

### 4.0.5 ###

* Remov `Webmention_Notification` class until proper tested/used

### 4.0.4 ###

* Update dependencies
* Fix WordPress warnings

### 4.0.3 ###

* Move comment approve list and auto approve to the `wp_allow_comment` function called by the `wp_new_comment` function.
* Minor fix to avatar function to account for the fact comments have an empty comment type

### 4.0.2 ###

* Cache in cases where stored avatar is a gravatar

### 4.0.1 ###

* Show Webmention form only if `pings_open`
* Show Webmention form also if comments are disabled

### 4.0.0 ###

* Add settings for enabling Webmention support by public post type
* Add setting for disabling sending media links...URLs attached to image, video, or audio tags
* Switch from sending Webmentions to all URLs in post content to only ones with proper HTML markup
* Support handling avatars if stored in meta
* Support serving a local anonymous avatar if no email and cache whether there is a gravatar for a definable period of time
* Store a Webmention protocol property in comment meta
* Do not show Webmention headers if URL does not support Webmentions
* Update Webmention meta template to use separate file which is shown on the edit comment screen
* Minimum PHP version bumped to 5.4. WordPress currently has a minimum of 5.6 but we support back to version 4.9
* For compatibility reasons, load a version of `is_avatar_comment_type` (introduced 5.1) and `get_self_link` (introduced 5.3) for use in this plugin
* Improve all settings and template forms ( props @tw2113 )

### 3.8.11 ###

* Minor bug fix

### 3.8.10 ###

* Always enable Webmentions on basis that using plugin means you want Webmentions instead of using default pingback setting
* Fix auto approve based on domain

### 3.8.9 ###

* Small HTML template changes

### 3.8.8 ###

* Added NodeInfo(2) support

### 3.8.7 ###

* Fixed default value of `webmention_avatars` on the settings page

### 3.8.6 ###

* Fixed default value of `webmention_avatars`

### 3.8.5 ###

* Set correct default value for the "Show comment form" setting

### 3.8.4 ###

* Store vouch property
* Preliminary vouch support disabled by default. As Vouch is experimental can only be enabled by adding define( 'WEBMENTION_VOUCH', true )

### 3.8.3 ###

* Changed setting for avatar to consider null to be the same as yes

### 3.8.2 ###

* Fixed PHP issue

### 3.8.1 ###

* Updated GDPR text suggestion
* Fixed old settings links
* Made Webmention comment-form text customizable (#175)
* Better handling of `wp_add_privacy_policy_content` call

Thanks Sebastian Greger

### 3.8.0 ###

* Added GDPR recommendation text
* Implemented help tab
* Form Improvements
* Domain allowlist
* Add avatar settings control
* Text improvements

Thanks Sebastian Greger, David Shanske and Chris Aldrich

### 3.7.0 ###

* Added "threaded comments" support

### 3.6.0 ###

* Send delete Webmentions
* Receive delete Webmentions

### 3.5.0 ###

* Added nicer HTML views for non API calls
* Added german translations (thanks to @deponeWD)
* Be sure to disable the old `webmention-for-comments` plugin

### 3.4.1 ###

* Add filter to allow setting of Webmention form text
* Move register settings to init due new default options not being set if admin only
* Add `edit_webmention` hook due comment array filtering
* Display Webmention Meta on Edit Comment page

### 3.4.0 ###

* Added settings link
* Added link to Homepage Webmention page
* Enable pings for Homepage Webmentions

### 3.3.0 ###

* Add setting for homepage mentions (thanks @dshanske)
* Remove deprecated functions due 4.8 release

### 3.2.1 ###

* moved endpoint discovery to functions.php
* added missing i18n strings
* removed polyfill

### 3.2.0 ###

* Enable option for page support
* Allow custom post types to declare support for Webmentions as a feature which will enable pings.
* Remove new meta properties from being added during preprocessing as these are added after Semantic Linkbacks Enhancement.
* Move new meta properties to being built into Webmention code
* Store Webmention source in comment meta but fall back to checking `comment_author_url` if not set.
* Store Webmention creation time in comment meta as comment time is overridden by Semantic Linkbacks allowing to determine if a comment has been modified.

### 3.1.1 ###

* URLEncode/Decode source and target
* Webmention Comment Type now declares support for avatars
* Meta keys are now registered for `webmention_target_url` and `webmention_target_fragment`
* Target URL is stored instead of derived from the permalink to ensure permanance
* Target fragment is stored to support fragmentions. Can also suport comments when reply is to a comment.

### 3.1.0 ###

* added page support (server and client)
* moved `webmention_post_id` filter to a global function (thanks @dshanske)
* fixed https://wordpress.org/support/topic/form-for-entering-manual-pings-stays-on/
* fixed some typos

### 3.0.1 ###

* Show endpoint discovery on every page again, to prevent several problems.

### 3.0.0 ###

* Plugin refactored to use API infrastructure.
* Visiting the endpoint in a web browser now returns a Webmention form.
* Plugin now compliant with draft specification although remains synchronous.
* Deprecation of webmention_title and webmention_content filters in favor of a single targeted Webmention comment data filter.
* webmention_post_send action now fires on all attempts to send a Webmention instead of only successful ones. Allows for logging functions to be added.
* Supports adding additional parameters when sending Webmentions
* Fix incompatibility with Ultimate Category Excluder plugin.

### 2.6.0 ###

* removed duplicate request for HTML via get_meta_tags
* refactoring
* limits to same domain

### 2.5.0 ###

* add salmon/crossposting-extension support (props @singpolyma)
* disable self-pings via settings
* do not unapprove already-approved Webmention (props @singpolyma)
* some code improvements

### 2.4.0 ###

* switched to WordPress Coding Standard

### 2.3.4 ###

* some fixes and improvements

### 2.3.3 ###

* added filter for Webmention endpoint (to add/require additional paramaters: <https://github.com/pfefferle/wordpress-webmention/issues/39> or <https://github.com/pfefferle/wordpress-webmention/pull/41>)

### 2.3.2 ###

* added more params to `webmention_post_send` (props to @snarfed)
* removed rescedule of Webmentions (props to @snarfed)

### 2.3.1 ###

* use error-code 403 instead of 500 if Pingbacks/Webmentions are disabled for a post (thanks @snarfed)
* added `webmention_comment_parent` filter

### 2.3.0 ###

* nicer `title` and `content` discovery
* added post-id to `webmention_links` filter
* improved `publish_post_hook` function
* disabled flood control
* nicer response value
* some more filters/actions
* added a default request "action" to be more flexible and to handle more than mentions on posts and pages
* a lot of small fixes

### 2.2.0 ###

* prevent selfpings
* added support for https and http
* optimized some methods

### 2.1.4 ###

* fixed pseudo hook

### 2.1.3 ###

* fixed some warnings

### 2.1.2 ###

* now ready to use in a bundle

### 2.1.1 ###

* nicer feedback for the Webmention endpoint

### 2.1.0 ###

* nicer `title` and `content` discovery
* added post-id to `webmention_links` filter
* improved `publish_post_hook` function

### 2.0.1 ###

* small fixes
* nicer excerpt extractor

### 2.0.0 ###

initial release

## Installation ##

Follow the normal instructions for [installing WordPress plugins](https://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

### Automatic Plugin Installation ###

To add a WordPress Plugin using the [built-in plugin installer](https://codex.wordpress.org/Administration_Screens#Add_New_Plugins):

1. Go to [Plugins](https://codex.wordpress.org/Administration_Screens#Plugins) > [Add New](https://codex.wordpress.org/Plugins_Add_New_Screen).
1. Type "`webmention`" into the **Search Plugins** box.
1. Find the WordPress Plugin you wish to install.
    1. Click **Details** for more information about the Plugin and instructions you may wish to print or save to help setup the Plugin.
    1. Click **Install Now** to install the WordPress Plugin.
1. The resulting installation screen will list the installation as successful or note any problems during the install.
1. If successful, click **Activate Plugin** to activate it, or **Return to Plugin Installer** for further actions.

### Manual Plugin Installation ###

There are a few cases when manually installing a WordPress Plugin is appropriate.

* If you wish to control the placement and the process of installing a WordPress Plugin.
* If your server does not permit automatic installation of a WordPress Plugin.
* If you want to try the [latest development version](https://github.com/pfefferle/wordpress-webmention).

Installation of a WordPress Plugin manually requires FTP familiarity and the awareness that you may put your site at risk if you install a WordPress Plugin incompatible with the current version or from an unreliable source.

Backup your site completely before proceeding.

To install a WordPress Plugin manually:

* Download your WordPress Plugin to your desktop.
    * Download from [the WordPress directory](https://wordpress.org/plugins/webmention/)
    * Download from [GitHub](https://github.com/pfefferle/wordpress-webmention/releases)
* If downloaded as a zip archive, extract the Plugin folder to your desktop.
* With your FTP program, upload the Plugin folder to the `wp-content/plugins` folder in your WordPress directory online.
* Go to [Plugins screen](https://codex.wordpress.org/Administration_Screens#Plugins) and find the newly uploaded Plugin in the list.
* Click **Activate** to activate it.

## Upgrade Notice ##

### 5.0.0 ###

This version is a complete rewrite of the code and a merge of the Semantic Linkbacks plugin. You should uninstall Semantic Linkbacks for this upgrade. Please file upgrade issues via Github.

Warning: Please backup your database before upgrading. This version changes the storage method of Webmentions.

### 3.0.0 ###

This update brings the plugin into compliance with the draft standard. As a result, some filters and
actions have changed. Please check any dependent code before updating.

### 2.0.0 ###

This plugin doesn't support the microformts stuff mentioned in the IndieWebCamp Wiki.
To enable semantic linkbacks you have to use <https://github.com/pfefferle/wordpress-semantic-linkbacks>
