<?php

namespace Webmention\Handler;

use DOMXPath;
use WP_Error;
use Webmention\Request;
use Webmention\Response;
use Webmention\Handler\Base;

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

		// Embed extra data in the call
		$api_link = add_query_arg( '_embed', 1, $api_link );

		$response = Request::get( $api_link );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$result = array_merge( $this->parse_post_json( $response ), $this->parse_author_json( $response ) );

		$raw = array();

		foreach ( $result as $key => $value ) {
			if ( '_' !== substr( $key, 0, 1 ) ) {
				$this->webmention_item->add( $key, $value );
			} else {
				$raw[ $key ] = $value;
			}
		}

		$this->webmention_item->add_raw( $raw );
		return true;
	}

	/**
	 * Parse post/page JSON
	 *
	 * @param Response $response
	 *
	 * @return array
	 */
	public function parse_post_json( Response $response ) {
		$page_json = json_decode( $response->get_body(), true );

		if ( ! is_array( $page_json ) ) {
			return array();
		}

		return array_filter(
			array(
				'name'      => $page_json['title']['rendered'] ?? null,
				'summary'   => $page_json['excerpt']['rendered'] ?? null,
				'content'   => $page_json['content']['rendered'] ?? null,
				'url'       => $page_json['link'] ?? null,
				'published' => $page_json['date_gmt'] ?? null,
				'updated'   => $page_json['modified_gmt'] ?? null,
				'author'    => $page_json['_links']['author'][0]['href'] ?? null,
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
		if ( ! is_array( $json ) || ! array_key_exists( '_embedded', $json ) ) {
			return array();
		}
		if ( ! isset( $json['_embedded']['author'][0] ) ) {
			return array();
		}

		$author_json = $json['_embedded']['author'][0];

		return array(
			'author'      => array(
				'name'  => $author_json['name'] ?? null,
				'url'   => $author_json['link'] ?? null,
				'photo' => $author_json['avatar_urls']['96'] ?? null,
			),
			'_authordata' => $author_json,
		);
	}
}
