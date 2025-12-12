<?php
/**
 * Webmention Uninstall
 *
 * Fired when the plugin is uninstalled to clean up plugin options and cached files.
 *
 * @package Webmention
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all plugin options.
 */
$options = array(
	// Settings registered in class-admin.php.
	'webmention_disable_selfpings_same_url',
	'webmention_disable_selfpings_same_domain',
	'webmention_disable_media_mentions',
	'webmention_support_post_types',
	'webmention_show_comment_form',
	'webmention_comment_form_text',
	'webmention_home_mentions',
	'webmention_approve_domains',
	'webmention_avatars',
	'webmention_avatar_store_enable',
	'webmention_separate_comment',
	'webmention_show_facepile',
	'webmention_facepile_fold_limit',
	// Legacy option for media mentions.
	'webmention_send_media_mentions',
	// Database version tracking.
	'webmention_db_version',
	// Migration lock.
	'webmention_migration_lock',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete cached avatar files using WP_Filesystem.
 */
global $wp_filesystem;

if ( ! function_exists( 'WP_Filesystem' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}

WP_Filesystem();

if ( $wp_filesystem ) {
	$upload_dir     = wp_get_upload_dir();
	$webmention_dir = $upload_dir['basedir'] . '/webmention';

	// Delete the entire webmention directory recursively.
	if ( $wp_filesystem->is_dir( $webmention_dir ) ) {
		$wp_filesystem->delete( $webmention_dir, true );
	}
}
