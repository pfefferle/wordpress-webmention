<?php

namespace Webmention;

use WP_Error;
use DOMDocument;

/**
 * Encapsulates all Webmention HTTP requests
 */
class Request {

	/**
	 * URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Header.
	 *
	 * @var array
	 */
	protected $header;

	/**
	 * Parsed Link Header.
	 *
	 * @var array
	 */
	protected $link_header;

	/**
	 * Body.
	 *
	 * @var string
	 */
	protected $body;

	/**
	 * DOMDocument.
	 *
	 * @var DOMDocument
	 */
	protected $domdocument;

	/**
	 * Content Type.
	 *
	 * @var string
	 */
	protected $content_type;

	/**
	 * HTTP Response.
	 *
	 * @var HTTP_Response
	 */
	protected $response;

	/**
	 * Response Code.
	 *
	 * @var int
	 */
	protected $response_code;

	/**
	 * Magic function for getter/setter
	 *
	 * @param string $method
	 * @param array  $params
	 * @return void
	 */
	public function __call( $method, $params ) {
		$var = strtolower( substr( $method, 4 ) );

		if ( strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}
	}

	public function __construct( $url ) {
		$this->url = $url;
	}

	/**
	 *  Retrieve a URL.
	 *
	 * @param string  $url  The URL to retrieve.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|true WP_Error or true if successful.
	 */
	public function fetch( $safe = true, $validate_header = false ) {
		if ( $validate_header ) {
			$response = $this->head( $this->url, $safe );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		return $this->get( $safe );
	}

	/**
	 *  Retrieve a URL.
	 *
	 * @param string  $url  The URL to retrieve.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|array Either an error or the complete return object
	 */
	public function get( $safe = true ) {
		$args = $this->get_arguments();

		if ( $safe ) {
			$response = wp_safe_remote_get( $this->url, $args );
		} else {
			$response = wp_remote_get( $this->url, $args );
		}

		$this->response = $response;

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->response_code = wp_remote_retrieve_response_code( $response );

		$this->content_type = $this->get_content_type();
		$check              = $this->check_content_type();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$this->body   = wp_remote_retrieve_body( $response );
		$this->header = wp_remote_retrieve_headers( $response );

		return $response;
	}

	/**
	 * Do a head request to check the suitability of a URL
	 *
	 * @param string $url The URL to check.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|array Return error or HTTP API response array.
	 */
	public function head( $safe = true ) {
		$args = $this->get_arguments();
		if ( $safe ) {
			$response = wp_safe_remote_head( $this->url, $args );
		} else {
			$response = wp_remote_head( $this->url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->response      = $response;
		$this->header        = wp_remote_retrieve_header( $response );
		$this->response_code = wp_remote_retrieve_response_code( $response );

		$check = $this->check_content_type( wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		return $response;
	}

	/**
	 * Return Arguments for Retrieving Webmention URLs
	 *
	 * @return array
	 */
	protected function get_arguments() {
		$wp_version = get_bloginfo( 'version' );
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 1048576,
			'redirection'         => 20,
			'user-agent'          => "$user_agent; Webmention",
		);
		return apply_filters( 'webmention_request_get_args', $args );
	}

	/**
	 * Check if response is a supportted content type
	 *
	 * @param  string $content_type The content type
	 *
	 * @return WP_Error|true return an error or that something is supported
	 */
	protected function check_content_type() {
		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', $this->get_content_type() ) ) {
			return new WP_Error(
				'unsupported_content_type',
				__( 'Content Type is not supported', 'webmention' ),
				array(
					'status' => 400,
				)
			);
		}
		return true;
	}

	/**
	 * Strip charset off content type for matching purposes
	 * @param array $response HTTP_Response array
	 *
	 * @return string|false return either false or the stripped string
	 *
	 */
	protected function get_content_type() {
		if ( $this->content_type ) {
			$content_type = $this->content_type;
		} else {
			$content_type = wp_remote_retrieve_header( $this->response, 'content-type' );
		}

		// Strip any character set off the content type
		$content_type = explode( ';', $content_type );

		if ( is_array( $content_type ) ) {
			$content_type = array_shift( $content_type );
		}

		return trim( $content_type );
	}

	/**
	 *  Takes the body and generates a DOMDocument.
	 *
	 * @return WP_Error|true An Error object or true.
	 */
	public function get_domdocument() {
		// if request is not set yet
		if ( ! $this->body ) {
			$this->get();
		}

		if ( $this->domdocument instanceof DOMDocument ) {
			return clone $this->domdocument;
		}

		if ( ! in_array( $this->get_content_type(), array( 'text/html', 'text/xml' ), true ) ) {
			return new WP_Error( 'wrong_content_type', __( 'Cannot Generate DOMDocument', 'webmention' ), array( $this->get_content_type() ) );
		}

		$body = $this->get_body();

		if ( ! $body ) {
			return new WP_Error( 'empty_body', __( 'Request body has no data', 'webmention' ) );
		}

		libxml_use_internal_errors( true );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$body = mb_convert_encoding( $body, 'HTML-ENTITIES', mb_detect_encoding( $body ) );
		}

		$domdocument = new DOMDocument();
		$domdocument->loadHTML( $body );

		libxml_use_internal_errors( false );

		$this->domdocument = $domdocument;

		return $domdocument;
	}

	/**
	 * Parses the Link Header
	 *
	 * @return array
	 */
	public function get_link_header() {
		// if request is not set yet
		if ( ! $this->response ) {
			$this->get();
		}

		if ( $this->link_header ) {
			return $this->link_header;
		}

		$links = wp_remote_retrieve_header( $this->response, 'link' );

		if ( ! $links ) {
			return new WP_Error( 'no_link_header', __( 'No link header available', 'webmention' ) );
		}

		$links = explode( ',', $links );
		$items = array();

		if ( is_array( $links ) && 1 <= count( $links ) ) {
			foreach ( $links as $link ) {
				$item   = array();
				$pieces = explode( ';', $link );
				$uri    = array_shift( $pieces );
				foreach ( $pieces as $p ) {
					$elements = explode( '=', $p );

					$item[ trim( $elements[0] ) ] = trim( $elements[1], '"\'' );
				}

				$item['uri'] = trim( trim( $uri ), '<>' );

				if ( isset( $item['rel'] ) ) {
					$rels = explode( ' ', $item['rel'] );
					foreach ( $rels as $rel ) {
						$item['rel'] = $rel;
						$items[]     = $item;
					}
				} else {
					$items[] = $item;
				}
			}
		}

		$this->link_header = $items;

		return $items;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function get_link_header_by( $filter ) {
		$links = $this->get_link_header();
		$items = array();

		foreach ( $links as $link ) {
			if ( array_intersect_uassoc( $link, $filter, 'strcasecmp' ) ) {
				$items[] = $link;
			}
		}

		return $items;
	}
}
