<?php

namespace Webmention;

use WP_Error;
use DOMDocument;

/**
 * Encapsulates all Webmention HTTP requests
 */
class Response {

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
	protected $header_links;

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
	protected $dom_document;

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
	 * Content Type.
	 *
	 * @var string
	 */
	protected $content_type;

	public function __construct( $url = null, $response = array() ) {
		$this->url           = $url;
		$this->response      = $response;
		$this->body          = wp_remote_retrieve_body( $response );
		$this->header        = wp_remote_retrieve_headers( $response );
		$this->response_code = wp_remote_retrieve_response_code( $response );
		$this->content_type  = wp_remote_retrieve_header( $response, 'content-type' );
	}

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
	 *
	 * @return string|false return either false or the stripped string
	 */
	protected function get_content_type() {
		$content_type = $this->content_type;

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
	 * @param bool $validate_content_type Validate content type header
	 *
	 * @return WP_Error|DOMDocument An Error object or the DOMDocument.
	 */
	public function get_dom_document( $validate_content_type = true ) {
		if ( $this->dom_document instanceof DOMDocument ) {
			return $this->dom_document;
		}

		if ( $validate_content_type && ( ! in_array( $this->get_content_type(), array( 'text/html', 'text/xml' ), true ) ) ) {
			return new WP_Error( 'wrong_content_type', __( 'Cannot generate DOMDocument', 'webmention' ), array( $this->get_content_type() ) );
		}

		$body = $this->body;

		if ( ! $body ) {
			return new WP_Error( 'empty_body', __( 'Request body has no data', 'webmention' ) );
		}

		libxml_use_internal_errors( true );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$body = mb_convert_encoding( $body, 'HTML-ENTITIES', mb_detect_encoding( $body ) );
		}

		$dom_document = new DOMDocument();
		$dom_document->loadHTML( $body );

		libxml_use_internal_errors( false );

		$this->dom_document = $dom_document;

		return $dom_document;
	}

	/**
	 * Parses the Link Header
	 *
	 * @return array
	 */
	public function get_header_links() {
		if ( $this->header_links ) {
			return $this->header_links;
		}

		$links = wp_remote_retrieve_header( $this->response, 'link' );

		if ( ! $links ) {
			return new WP_Error( 'no_link_header', __( 'No link header available', 'webmention' ) );
		}

		if ( ! is_array( $links ) ) {
			$links = explode( ',', $links );
		}

		$items = array();

		if ( is_array( $links ) && 1 <= count( $links ) ) {
			foreach ( $links as $link ) {
				$item   = array();
				$pieces = explode( ';', $link );
				$uri    = array_shift( $pieces );

				foreach ( $pieces as $p ) {
					$elements = explode( '=', $p );

					if (
						is_array( $elements ) &&
						! empty( $elements[0] ) &&
						! empty( $elements[1] )
					) {
						$item[ trim( $elements[0] ) ] = trim( $elements[1], "\"' \n\r\t\v\x00" );
					}

					continue;
				}

				$item['uri'] = trim( $uri, "<> \n\r\t\v\x00" );

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

		$this->header_links = $items;

		return $items;
	}

	/**
	 * Get link headers by a filter
	 *
	 * @param array $filter Filter link headers
	 *
	 * @example array( 'rel' => 'alternate', 'type' => 'application/json' )
	 *
	 * @return array
	 */
	public function get_header_links_by( $filter ) {
		$links = $this->get_header_links();
		$items = array();

		foreach ( $links as $link ) {
			if ( array_intersect_uassoc( $link, $filter, 'strcasecmp' ) ) {
				$items[] = $link;
			}
		}

		return $items;
	}

	/**
	 * Check if request returns an HTTP Error Code
	 *
	 * @return boolean
	 */
	public function is_error() {
		$code = $this->get_response_code();
		return $code >= 400 && $code < 600;
	}

	/**
	 * Get the HTTP Error if there is one
	 *
	 * @return WP_Error
	 */
	public function get_error() {
		if ( ! $this->is_error() ) {
			return false;
		}

		return new WP_Error(
			'http_error',
			wp_remote_retrieve_response_message( $this->get_response() ),
			array(
				'status' => $this->get_response_code(),
			)
		);
	}
}
