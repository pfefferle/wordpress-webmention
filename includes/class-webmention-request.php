<?php
/**
 * Encapsulates all Webmention HTTP requests
 */
class Webmention_Request {

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
	 * Content Type.
	 *
	 * @var string
	 */
	protected $content_type;

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
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}
	}

	/**
	 *  Retrieve a URL.
	 *
	 * @param string $url The URL to retrieve.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|true WP_Error or true if successful.
	 */
	public function fetch( $url, $safe = true ) {
		$this->url = $url;
		$response  = $this->head( $url, $safe );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = $this->remote_get( $url, $safe );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return true;
	}

	/**
	 *  Retrieve a URL.
	 *
	 * @param string $url The URL to retrieve.
	 * @param boolean $safe Whether to use the safe or unfiltered version of HTTP API.
	 *
	 * @return WP_Error|array Either an error or the complete return object
	 */
	public function remote_get( $url, $safe = true ) {
		$args = $this->get_remote_arguments();
		if ( $safe ) {
			$response = wp_safe_remote_get( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$check = $this->check_response_code( wp_remote_retrieve_response_code( $response ), $response );
		if ( is_wp_error( $check ) ) {
				return $check;
		}

		$this->content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$check              = $this->check_content_type( $this->content_type );
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
	public function head( $url, $safe = true ) {
		$args = $this->get_remote_arguments();
		if ( $safe ) {
			$response = wp_safe_remote_head( $url, $args );
		} else {
			$response = wp_remote_head( $url, $args );
		}
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
	protected function get_remote_arguments() {

		$wp_version = get_bloginfo( 'version' );
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 153600,
			'redirection'         => 20,
			'headers'             => array(
				'Accept' => 'text/html, text/plain',
			),
		);
		return apply_filters( 'webmention_remote_get_args', $args );
	}

	/**
	 * Check if response is a supportted content type
	 *
	 * @param  string $content_type The content type
	 *
	 * @return WP_Error|true return an error or that something is supported
	 */
	protected function check_content_type( $content_type ) {
		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', $content_type ) ) {
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
	 * @param array $response Response if Present
	 *
	 * @return WP_Error|true return an error or that something is supported
	 */
	protected function check_response_code( $code, $response = null ) {
		switch ( $code ) {
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
						'allow'  => wp_remote_retrieve_header( $response, 'allow' ),
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
			case 451:
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
					wp_remote_retrieve_response_message( $response ),
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
	protected function get_content_type( $response ) {
		$content_type = wp_remote_retrieve_header( $response, 'Content-Type' );
		// Strip any character set off the content type
		$ct = explode( ';', $content_type );
		if ( is_array( $ct ) ) {
			$content_type = array_shift( $ct );
		}
		return trim( $content_type );
	}
}
