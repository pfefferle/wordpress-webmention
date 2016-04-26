<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-Webmention
 * Description: Webmention support for WordPress posts
 * Author: pfefferle
 * Author URI: http://notizblog.org/
 * Version: 2.5.0
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: Webmention
 */

// Webmention Receiver
require_once plugin_dir_path( __FILE__ ) . 'class-webmention-receiver.php' ;
// Webmention Sender
require_once plugin_dir_path( __FILE__ ) . 'class-webmention-sender.php';

// Temporary Backcompat
class WebmentionPlugin {
};
