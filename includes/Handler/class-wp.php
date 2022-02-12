<?php

namespace Webmention\Handler;

use DOMXPath;
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
		$links = $this->link_parse( wp_remote_retrieve_header( $request->get_response(), 'link' ) );
		$rels  = wp_list_pluck( $links, 'rel' );

		$api = array_search( 'https://api.w.org/', $rels, true );

		if ( ! $api ) {
			return false;
		}

		$alternate = array_search( 'alternate', $rels, true );
		if ( ! isset( $links[ $alternate ]['type'] ) && 'application/json' !== $links[ $alternate ]['type'] ) {
			return false;
		}

		$request = new Request( $api );
		$return  = $request->fetch();
		if ( is_wp_error( $return ) ) {
			return $return;
		}

		// Decode the site json to get the site name, description, base URL, and timezone string.
		$site_json = json_decode( $request->get_body(), true );
		$site_json = wp_array_slice_assoc( $site_json, array( 'name', 'description', 'url', 'timezone_string', 'gmt_offset' ) );
		$timezone  = new DateTimeZone( $site_json['timezone_string'] );
		$this->webmention_item->set__site_name( $site_json['name'] );

		$request = new Request( $alternate );
		$return  = $request->fetch();
		if ( is_wp_error( $return ) ) {
			return $return;
		}

		$page_json = json_decode( $request->get_body(), true );
		$this->webmention_item->set_content( $page_json['content']['rendered'] );
		$this->webmention_item->set_summary( $page_json['excerpt']['rendered'] );
		$this->webmention_item->set_url( $page_json['link'] );
		$this->webmention_item->set_name( $page_json['title']['rendered'] );

		$this->webmention_item->set_published( new DateTimeImmutable( $page_json['date'], $timezone ) );
		$this->webmention_item->set_updated( new DateTimeImmutable( $page_json['modified'], $timezone ) );

		$author = $page_json['_links']['author'][0]['href'];

		$request = new Request( $author );
		$return  = $request->fetch();
		if ( is_wp_error( $return ) ) {
			return $return;
		}

		$author_json = json_decode( $request->get_body(), true );

		$this->webmention_item->set_author(
			array_filter(
				array(
					'name'  => $author_json['name'],
					'url'   => $author_json['link'],
					'photo' => $author_json['avatar_urls']['96'],
				)
			)
		);

		$this->webmention_item->set_raw( $page_json );
		return true;

	}

	/**
	 * Parses the Link Header
	 */
	public function link_parse( $links ) {
		$links  = explode( ',', $links );
		$return = array();
		if ( is_array( $links ) && 1 <= count( $links ) ) {
			foreach ( $links as $link ) {
				$pieces = explode( '; ', $link );
				$uri    = trim( array_shift( $pieces ), '<> ' );
				foreach ( $pieces as $p ) {
					$elements                       = explode( '=', $p );
					$return[ $uri ][ $elements[0] ] = trim( $elements[1], '"' );
				}
			}
			ksort( $return );
		}
		return $return;
	}
}
