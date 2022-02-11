<?php

namespace Webmention;

class Discovery {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function int() {
		// endpoint discovery
		add_action( 'wp_head', array( static::class, 'html_header' ), 99 );
		add_action( 'template_redirect', array( static::class, 'http_header' ) );
		add_filter( 'host_meta', array( static::class, 'jrd_links' ) );
		add_filter( 'webfinger_user_data', array( static::class, 'jrd_links' ) );
		add_filter( 'webfinger_post_data', array( static::class, 'jrd_links' ) );

		add_filter( 'nodeinfo_data', array( static::class, 'nodeinfo' ), 10, 2 );
		add_filter( 'nodeinfo2_data', array( static::class, 'nodeinfo2' ), 10 );
	}

	/**
	 * The Webmention autodicovery meta-tags
	 */
	public static function html_header() {
		if ( ! self::should_show_headers() ) {
			return;
		}

		printf( '<link rel="webmention" href="%s" />' . PHP_EOL, get_webmention_endpoint() );
		// backwards compatibility with v0.1
		printf( '<link rel="http://webmention.org/" href="%s" />' . PHP_EOL, get_webmention_endpoint() );
	}

	/**
	 * The Webmention autodicovery http-header
	 */
	public static function http_header() {
		if ( ! self::should_show_headers() ) {
			return;
		}

		header( sprintf( 'Link: <%s>; rel="webmention"', get_webmention_endpoint() ), false );
		// backwards compatibility with v0.1
		header( sprintf( 'Link: <%s>; rel="http://webmention.org/"', get_webmention_endpoint() ), false );
	}

	/**
	 * Generates webfinger/host-meta links
	 */
	public static function jrd_links( $array ) {
		$array['links'][] = array(
			'rel'  => 'webmention',
			'href' => get_webmention_endpoint(),
		);
		// backwards compatibility with v0.1
		$array['links'][] = array(
			'rel'  => 'http://webmention.org/',
			'href' => get_webmention_endpoint(),
		);

		return $array;
	}

	/**
	 * Extend NodeInfo data.
	 *
	 * @since 3.8.9
	 *
	 * @param array $nodeinfo NodeInfo data.
	 * @param array $version  Updated data.
	 *
	 * @return array
	 */
	public static function nodeinfo( $nodeinfo, $version ) {
		if ( '2.0' === $version ) {
			$nodeinfo['protocols'][] = 'webmention';
		} else {
			$nodeinfo['protocols']['inbound'][]  = 'webmention';
			$nodeinfo['protocols']['outbound'][] = 'webmention';
		}

		return $nodeinfo;
	}

	/**
	 * Extend NodeInfo2 data.
	 *
	 * @since 3.8.9
	 *
	 * @param array $nodeinfo NodeInfo2 data.
	 *
	 * @return array
	 */
	public static function nodeinfo2( $nodeinfo ) {
		$nodeinfo['protocols'][] = 'webmention';

		return $nodeinfo;
	}

	/**
	 * Webmention headers to be added
	 *
	 * @return boolean
	 */
	public static function should_show_headers() {
		if ( WEBMENTION_ALWAYS_SHOW_HEADERS ) {
			return true;
		}

		return self::should_receive_mentions();
	}

	/**
	 *
	 *
	 * @return boolean
	 */
	public static function should_receive_mentions() {
		if ( is_singular() ) {
			return pings_open();
		} else {
			$post_id = webmention_url_to_postid( get_self_link() );
		}

		if ( ! $post_id ) {
			return false;
		}

		// If the post type does not support webmentions do not even check if pings_open is set
		if ( ! post_type_supports( get_post_type( $post_id ), 'webmentions' ) ) {
			return false;
		}

		if ( pings_open( $post_id ) ) {
			return true;
		}

		return false;
	}
}