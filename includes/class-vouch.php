<?php

namespace Webmention;

use WP_Error;
use Webmention\Receiver;

/**
 * Webmention Vouch Class
 *
 * @author Matthias Pfefferle
 */
class Vouch {
	/**
	 * Initialize, registering WordPress hooks
	 */
	public static function init() {
		// Webmention helper
		add_filter( 'webmention_comment_data', array( static::class, 'verify_vouch' ), 10, 1 );

		self::register_meta();
	}

	/**
	 * This is more to lay out the data structure than anything else.
	 */
	public static function register_meta() {
		// Purpose of this is to store whether something has been vouched for
		$args = array(
			'type'         => 'int',
			'description'  => esc_html__( 'Has this Webmention Been Vouched for', 'webmention' ),
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'webmention_vouched', $args );
	}

	/**
	 * Verifies vouch is valid and either return an error if not verified or return the array with retrieved
	 * data. For right now, it just sets a parameter whether or not it would be vouched
	 *
	 * @param array $data {
	 *     $comment_type
	 *     $comment_author_url
	 *     $comment_author_IP
	 *     $target
	 *     $vouch
	 * }
	 *
	 * @return array|WP_Error $data Return Error Object or array with added fields {
	 *     $remote_source
	 *     $remote_source_original
	 *     $content_type
	 * }
	 *
	 * @uses apply_filters calls "http_headers_useragent" on the user agent
	 */
	public static function verify_vouch( $data ) {
		if ( ! $data || is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			return new WP_Error( 'invalid_data', esc_html__( 'Invalid data passed', 'webmention' ), array( 'status' => 500 ) );
		}

		// The remaining instructions only apply if there is a vouch parameter
		if ( ! isset( $data['vouch'] ) ) {
			return $data;
		}

		$data['comment_meta']['webmention_vouch_url'] = esc_url_raw( $data['vouch'] );

		// Is the person vouching for the relationship using a page on your own site?
		$vouch_id = url_to_postid( $data['vouch'] );
		if ( $vouch_id ) {
			$data['comment_meta']['webmention_vouched'] = '1';
			return $data;
		}

		$wp_version = get_bloginfo( 'version' );

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 153600,
			'redirection'         => 20,
			'user-agent'          => "$user_agent; verifying Vouch from " . $data['comment_author_IP'],
		);

		$response = wp_safe_remote_head( $data['vouch'], $args );

		// check if vouch is accessible if not reject
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'vouch_not_found',
				esc_html__( 'Vouch Not Found', 'webmention' ),
				array(
					'status' => 400,
					'data'   => $data,
				)
			);
		}

		// A valid response code from the other server would not be considered an error.
		$response_code = wp_remote_retrieve_response_code( $response );
		// not an (x)html, sgml, or xml page, so asking the sender to try again
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return new WP_Error(
				'vouch_not_found',
				esc_html__( 'Vouch Not Found', 'webmention' ),
				array(
					'status' => 449,
					'data'   => $data,
				)
			);
		}

		if ( 200 !== $response_code ) {
				return new WP_Error(
					'vouch_error',
					array(
						'status' => 400,
						'data'   => array( $data, $response_code ),
					)
				);
		}

		// If this is not
		if ( ! Receiver::is_source_allowed( $data['vouch'] ) ) {
			$data['comment_meta']['webmention_vouched'] = '0';
			return $data;
		}

		$response = wp_safe_remote_get( $data['vouch'], $args );
		$urls     = wp_extract_urls( wp_remote_retrieve_body( $response ) );

		foreach ( $urls as $url ) {
			if ( wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( $data['source'] ) ) {
				$data['comment_meta']['webmention_vouched'] = '1';
				return $data;
			}
		}

		return new WP_Error(
			'vouch_error',
			esc_html__( 'Vouch Not Found', 'webmention' ),
			array(
				'status' => 400,
				'data'   => $data,
			)
		);
	}
}
