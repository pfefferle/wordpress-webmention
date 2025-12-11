<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Webmention
 */

define( 'WEBMENTION_TESTS_DIR', __DIR__ );

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! is_dir( $_tests_dir ) ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/classicpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Load PHPUnit Polyfills.
require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/webmention.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Disable HTTP requests to prevent live requests during testing.
tests_add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		// Return false by default to allow mocked requests.
		return $preempt;
	},
	1,
	3
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
