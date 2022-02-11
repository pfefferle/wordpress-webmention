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
	 * URL.
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
		$check               = $this->check_response_code();

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$this->content_type = $this->get_content_type();
		$check              = $this->check_content_type();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$this->body = wp_remote_retrieve_body( $response );

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

		$this->response = $response;

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->response_code = wp_remote_retrieve_response_code( $response );
		$check               = $this->check_response_code( $this->response_code, $response );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

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
		if ( preg_match( '#(image|audio|video|model)/#is', $this->content_type ) ) {
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
	 * Check if response is a supported content type
	 *
	 * @param int $code Status Code.
	 * @param array $response Response if Present.
	 *
	 * @return WP_Error|true return an error or that something is supported
	 */
	protected function check_response_code() {
		switch ( $this->response_code ) {
			case 200:
				return true;
			case 404:
				return new WP_Error(
					'resource_not_found',
					__( 'Resource not found', 'webmention' ),
					array(
						'status' => 400,
					)
				);
			case 405:
				return new WP_Error(
					'method_not_allowed',
					__( 'Method not allowed', 'webmention' ),
					array(
						'status' => 400,
						'allow'  => wp_remote_retrieve_header( $this->response, 'allow' ),
					)
				);
			case 410:
				return new WP_Error(
					'resource_deleted',
					__( 'Resource has been deleted', 'webmention' ),
					array(
						'status' => 400,
					)
				);
			case 452:
				return new WP_Error(
					'resource_removed',
					__( 'Resource removed for legal reasons', 'webmention' ),
					array(
						'status' => 400,
					)
				);
			default:
				return new WP_Error(
					'source_error',
					wp_remote_retrieve_response_message( $this->response ),
					array(
						'status' => 400,
					)
				);
		}
	}

	/**
	 * Strip charset off content type for matching purposes
	 * @param array $response HTTP_Response array
	 *
	 * @return string|false return either false or the stripped string
	 *
	 */
	protected function get_content_type() {
		$content_type = wp_remote_retrieve_header( $this->response, 'Content-Type' );
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
		if ( $this->domdocument instanceof DOMDocument ) {
			return $this->domdocument;
		}

		if ( ! in_array( $this->content_type, array( 'text/html', 'text/xml' ), true ) ) {
			return new WP_Error( 'wrong_content_type', __( 'Cannot Generate DOMDocument', 'webmention' ), array( $this->content_type ) );
		}

		$this->domdocument = webmention_load_domdocument( $this->body );

		return $this->domdocument;
	}
}
