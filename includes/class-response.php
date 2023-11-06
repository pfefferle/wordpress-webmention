<?php

namespace Webmention;

use WP_Error;
use DOMDocument;
use DOMXPath;

/**
 * Class Response
 *
 * This class encapsulates all Webmention HTTP responses. It provides methods for parsing and handling responses,
 * as well as for validating the response status and content.
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

	/**
	 * Constructor.
	 *
	 * Initializes the Response object with the provided URL and response data.
	 *
	 * @param string|null $url      The URL of the response.
	 * @param array       $response The HTTP response data.
	 */
	public function __construct( $url = null, $response = array() ) {
		$this->url           = $url;
		$this->response      = $response;
		$this->body          = wp_remote_retrieve_body( $response );
		$this->header        = wp_remote_retrieve_headers( $response );
		$this->response_code = wp_remote_retrieve_response_code( $response );
		$this->content_type  = wp_remote_retrieve_header( $response, 'content-type' );
	}

	/**
	 * Magic method for getter/setter.
	 *
	 * Provides a way to get or set properties of the Response object.
	 *
	 * @param string $method The name of the method being called.
	 * @param array  $params The parameters passed to the method.
	 *
	 * @return mixed If a getter is called, returns the value of the property. If a setter is called, sets the value of the property.
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
	 * Check if response is a supported content type
	 *
	 * This function checks if the provided content type is supported.
	 *
	 * @return WP_Error|true Returns true if the content type is supported; otherwise, returns a WP_Error object.
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
	 * Get content type of response.
	 *
	 * @return string|false Return either false or the stripped string
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
	 * Takes the body and generates a DOMDocument.
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
			return array();
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
	 * Parses HTML links
	 *
	 * @return array An array of parsed HTML links.
	 */
	public function get_html_links() {
		$dom   = $this->get_dom_document();
		$xpath = new DOMXPath( $dom );
		$items = array();

		// check <link> and <a> elements
		foreach ( $xpath->query( '(//link|//a)[@rel and @href]' ) as $link ) {
			$rels = explode( ' ', $link->getAttribute( 'rel' ) );
			foreach ( $rels as $rel ) {
				$item         = array();
				$item['rel']  = trim( $rel );
				$item['uri']  = trim( $link->getAttribute( 'href' ) );
				$item['type'] = trim( $link->getAttribute( 'type' ) );
				$items[]      = $item;
			}
		}

		return $items;
	}

	/**
	 * Get link headers by a filter
	 *
	 * @param array $filter Filter link headers
	 *                      for example `array( 'rel' => 'alternate', 'type' => 'application/json' )`
	 *
	 * @return array
	 */
	public function get_header_links_by( $filter ) {
		$links = $this->get_header_links();

		if ( is_wp_error( $links ) ) {
			return array();
		}

		$items = array();

		foreach ( $links as $link ) {
			if ( array_intersect( $filter, $link ) === $filter ) {
				$items[] = $link;
			}
		}

		return $items;
	}


	/**
	 * Get html link headers by a filter
	 *
	 * @param array $filter Filter link headers
	 *                      for example `array( 'rel' => 'alternate', 'type' => 'application/json' )`
	 *
	 * @return array Array of links
	 */
	public function get_html_links_by( $filter ) {
		$links = $this->get_html_links();

		if ( is_wp_error( $links ) ) {
			return array();
		}

		$items = array();

		foreach ( $links as $link ) {
			if ( array_intersect( $filter, $link ) === $filter ) {
				$items[] = $link;
			}
		}

		return $items;
	}

	/**
	 * Get head and html links by a filter
	 *
	 * @param array $filter Filter links
	 *                      for example `array( 'rel' => 'alternate', 'type' => 'application/json' )`
	 *
	 * @return array Array of links
	 */
	public function get_links_by( $filter ) {
		$links = array_merge( $this->get_header_links(), $this->get_html_links() );

		if ( ! $links ) {
			return $links;
		}

		$items = array();

		foreach ( $links as $link ) {
			if ( array_intersect( $filter, $link ) === $filter ) {
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
	 * @return WP_Error Returns a WP_Error object if the request fails; otherwise, returns false.
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
