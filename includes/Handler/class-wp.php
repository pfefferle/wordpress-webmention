<?php

namespace Webmention\Handler;

use DOMXPath;
use WP_Error;
use Webmention\Request;
use Webmention\Response;
use Webmention\Handler\Base;
use DateTimeZone;
use DateTimeImmutable;

/**
 * Class for Webmention parsing using the WordPress API.
 */
class WP extends Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'wp';

	/**
	 * Takes a response object and parses it.
	 *
	 * @param Webmention\Response $response Response Object.
	 * @param string $target_url The target URL
	 *
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( Response $response, $target_url ) {
		$site_api_links = $response->get_links_by( array( 'rel' => 'https://api.w.org/' ) );
		$post_api_links = $response->get_links_by(
			array(
				'rel'  => 'alternate',
				'type' => 'application/json',
			)
		);

		if ( ! $site_api_links || ! $post_api_links ) {
			return new WP_Error( 'no_api_link', __( 'No API link found in the source code', 'webmention' ) );
		}

		$api_link = null;

		// check if link is API link and skip JSON-Feed links for example
		foreach ( $post_api_links as $post_api_link ) {
			if ( false !== strstr( $post_api_link['uri'], $site_api_links[0]['uri'] ) ) {
				$api_link = $post_api_link['uri'];
				break;
			}
		}

		if ( ! $api_link ) {
			return new WP_Error( 'no_api_link', __( 'No valid API link found', 'webmention' ) );
		}

		$response = Request::get( $site_api_links[0]['uri'] );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Decode the site json to get the site name, description, base URL, and timezone string.
		$site_json = $this->parse_site_json( $response );

		// Embed extra data in the call
		$api_link = add_query_arg( '_embed', 1, $api_link );

		$response = Request::get( $api_link );
		if ( is_wp_error( $response ) ) {
			return response;
		}

		$page = $this->parse_post_json( $response, $site_json['timezone'] );

		$result = array_merge( $page, $this->parse_author_json( $response ) );

		$raw = array();

		foreach ( $result as $key => $value ) {
			if ( '_' !== substr( $key, 0, 1 ) ) {
				$this->webmention_item->add( $key, $value );
			} else {
				$raw[ $key ] = $value;
			}
		}

		$raw['_sitedata'] = $site_json;
		$this->webmention_item->add_raw( $raw );
		return true;
	}

	/**
	 * Get the post API link from the response.
	 *
	 * @param Response $response Response object.
	 *
	 * @return string|WP_Error The API link or WP_Error if none found.
	 */
	public function get_post_api_links( Response $response ) {
		$post_api_links = $response->get_links_by(
			array(
				'rel'  => 'alternate',
				'type' => 'application/json',
			)
		);

		if ( ! $post_api_links || ! is_array( $post_api_links ) ) {
			return new WP_Error( 'no_api_link', __( 'No API link found in the source code', 'webmention' ) );
		}

		return $post_api_links;
	}

	/**
	 * Get the site API link from the response.
	 *
	 * @param Response $response Response object.
	 *
	 * @return string|WP_Error The API link or WP_Error if none found.
	 */
	public function get_site_api_links( Response $response ) {
		$site_api_links = $response->get_header_links_by( array( 'rel' => 'https://api.w.org/' ) );

		if ( ! $site_api_links || ! is_array( $site_api_links ) || is_wp_error( $site_api_links ) ) {
			$site_api_links = $response->get_html_links_by( array( 'rel' => 'https://api.w.org/' ) );
		}

		if ( ! $site_api_links || ! is_array( $site_api_links ) || is_wp_error( $site_api_links ) ) {
			return new WP_Error( 'no_api_link', __( 'No API link found in the source code', 'webmention' ) );
		}

		return $site_api_links;
	}

	/**
	 * Parse post/page JSON
	 *
	 * @param Response          $response
	 * @param DateTimeZone|null $timezone
	 *
	 * @return array
	 */
	public function parse_post_json( Response $response, $timezone = null ) {
		if ( ! $timezone ) {
			$timezone = wp_timezone();
		}

		$page_json = json_decode( $response->get_body(), true );

		return array_filter(
			array(
				'name'      => ifset( $page_json['title']['rendered'] ),
				'summary'   => ifset( $page_json['excerpt']['rendered'] ),
				'content'   => ifset( $page_json['content']['rendered'] ),
				'url'       => ifset( $page_json['link'] ),
				'published' => new DateTimeImmutable( $page_json['date'], $timezone ),
				'updated'   => new DateTimeImmutable( $page_json['modified'], $timezone ),
				'author'    => ifset( $page_json['_links']['author'][0]['href'] ),
				'_pagedata' => $page_json,
			)
		);
	}

	/**
	 * Parse author JSON
	 *
	 * @param Response $response
	 *
	 * @return array
	 */
	public function parse_author_json( Response $response ) {
		$json = json_decode( $response->get_body(), true );
		if ( ! array_key_exists( '_embedded', $json ) ) {
			return null;
		}

		$author_json = $json['_embedded']['author'][0];

		return array(
			'author'      => array(
				'name'  => ifset( $author_json['name'] ),
				'url'   => ifset( $author_json['link'] ),
				'photo' => ifset( $author_json['avatar_urls']['96'] ),
			),
			'_authordata' => $author_json,
		);
	}

	/**
	 * Parse site JSON
	 *
	 * @param Response $response
	 *
	 * @return array
	 */
	public function parse_site_json( Response $response ) {
		// Decode the site json to get the site name, description, base URL, and timezone string.
		$site_json = json_decode( $response->get_body(), true );

		unset( $site_json['namespaces'] );
		unset( $site_json['authentication'] );
		unset( $site_json['routes'] );

		if ( ! empty( $site_json['timezone_string'] ) ) {
			$site_json['timezone'] = new DateTimeZone( $site_json['timezone_string'] );
		} else {
			$site_json['timezone'] = null;
		}

		return $site_json;
	}
}
