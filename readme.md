# WebMention #
**Contributors:** pfefferle  
**Donate link:** http://14101978.de  
**Tags:** webmention, pingback, trackback, linkback  
**Requires at least:** 2.7  
**Tested up to:** 3.8  
**Stable tag:** 2.1.3  

WebMention for WordPress!

## Description ##

Enables [WebMention](http://webmention.org/) support for WordPress.

WebMention is a simple and modern alternative to the Pingback/Trackback protocol.

## Frequently Asked Questions ##

### What are WebMentions? ###

[WebMention](http://webmention.org) is a simple way to automatically notify any URL when you link to it on your site. From the receivers perpective, it's a way to request notification when other sites link to it.

It’s a modern alternative to Pingback and other forms of Linkback.

### How can I send WebMentions ###

You can use the `send_webmention($source, $target)` function and pass a source and a target or you can fire an action like `do_action('send_webmention', $source, $target)`.

## Changelog ##

Project maintined on github at [pfefferle/wordpress-webmention](https://github.com/pfefferle/wordpress-webmention).

### 2.1.3 ###

* fixed some warnings

### 2.1.2 ###

* now ready to use in a bundle

### 2.1.1 ###

* nicer feedback for the WebMention endpoint

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

1. Upload the `webmention`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the *Plugins* menu in WordPress
3. ...and that's it :)

## Upgrade Notice ##

### 2.0.0 ###

This plugin doesn't support the microformts stuff mentioned in the IndieWebCamp Wiki.
To enable semantik linkbacks you have to use <https://github.com/pfefferle/wordpress-semantic-linkbacks>
