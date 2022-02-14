<?php

namespace Webmention\Handler;

use DOMXPath;
use WP_Error;
use Webmention\Request;
use Webmention\Handler\Base;
use DateTimeZone;
use DateTimeImmutable;

/**
 * Class for webmention parsing using the WordPress API.
 */
class WP extends Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'wp';

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention\Request $request Request Object.
	 * @param string $target_url The target URL
	 *
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( Request $request, $target_url ) {
		$root_api_links = $request->get_link_header_by( array( 'rel' => 'https://api.w.org/' ) );
		$post_api_links = $request->get_link_header_by(
			array(
				'rel'  => 'alternate',
				'type' => 'application/json',
			)
		);

		if ( ! $root_api_links && ! is_array( $root_api_links ) ) {
			return new WP_Error( 'no_api_link', __( 'No API link found in the source code', 'webmention' ) );
		}

		// check if link is API link and skip JSON-Feed links for example
		foreach ( $post_api_links as $post_api_link ) {
			if ( false !== strstr( $post_api_link['uri'], $root_api_links[0]['uri'] ) ) {
				$api_link = $post_api_link['uri'];
			}
		}

		$request = new Request( $links['api'] );
		$return  = $request->fetch();
		if ( is_wp_error( $return ) ) {
			return $return;
		}

		// Decode the site json to get the site name, description, base URL, and timezone string.
		$site_json = $this->parse_site( $request );
		$this->webmention_item->set__site_name( $site_json['name'] );

		$request = new Request( $links['url'] );
		$return  = $request->fetch();
		if ( is_wp_error( $return ) ) {
			return $return;
		}

		$results = $this->parse_page( $request, $site_json['timezone'] );

		$request = new Request( $results['author'] );
		$return  = $request->fetch();
		if ( is_wp_error( $return ) ) {
			return $return;
		}

		$results = array_merge( $results, $this->parse_author( $request ) );

		$raw = array();

		foreach ( $results as $key => $value ) {
			if ( '_' !== substr( $key, 0, 1 ) ) {
				$this->webmention_item->set( $key, $value );
			} else {
				$raw[ $key ] = $value;
			}
		}

		$raw['_sitedata'] = $site_json;
		$this->webmention_item->set_raw( $raw );
		return true;

	}

	public function parse_page( $request, $timezone = null ) {
		if ( ! $timezone ) {
			$timezone = wp_timezone();
		}
		$page_json = json_decode( $request->get_body(), true );
		return array_filter(
			array(
				'name'      => $page_json['title']['rendered'],
				'summary'   => $page_json['excerpt']['rendered'],
				'content'   => $page_json['content']['rendered'],
				'url'       => $page_json['link'],
				'published' => new DateTimeImmutable( $page_json['date'], $timezone ),
				'updated'   => new DateTimeImmutable( $page_json['modified'], $timezone ),
				'author'    => $page_json['_links']['author'][0]['href'],
				'_pagedata' => $page_json,
			)
		);
	}

	public function parse_author( $request ) {
		$author_json = json_decode( $request->get_body(), true );
		return array(
			'author'      => array(
				'name'  => $author_json['name'],
				'url'   => $author_json['link'],
				'photo' => $author_json['avatar_urls']['96'],
			),
			'_authordata' => $author_json,
		);
	}

	public function parse_site( $request ) {
		// Decode the site json to get the site name, description, base URL, and timezone string.
		$site_json = json_decode( $request->get_body(), true );
		unset( $site_json['namespaces'] );
		unset( $site_json['authentication'] );
		unset( $site_json['routes'] );
		$site_json['timezone'] = new DateTimeZone( $site_json['timezone_string'] );
		return $site_json;
	}
}
