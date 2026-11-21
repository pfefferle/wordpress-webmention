<?php

namespace Webmention\Entity;

use DateTimeZone;
use DateTimeImmutable;

/**
 * Represents a Remote Webmention.
 *
 * The handlers fill an item one after the other, and the first handler that provides a property wins.
 * There are two kinds of properties:
 *
 * - First class properties are what a comment is made of: the published date, the URL, the response type,
 *   the content or the summary, and the name, the URL and the photo of the author. As soon as all of them
 *   are set, the item is complete and the handlers that are left do not run, see `is_complete()`.
 * - Fallback properties are collected by the handlers, but they are only used when the parsing is done and
 *   a first class property is still missing. The getters apply them:
 *     - the name stands in for the content, see `get_content()`.
 *     - the site name stands in for the name of the author, see `get_author()`.
 *     - the site icon stands in for the photo of the author, see `get_author()`.
 *     - the URL stands in for the URL of the author, see `get_author()`.
 *
 * A handler only stores what it can identify. An image that belongs to the author is the photo of the author,
 * the icon of a page is the site icon, and the image of the post is the photo of the item.
 */
class Item {
	/**
	 * The publish time.
	 *
	 * @var DateTimeImmutable
	 */
	protected $published;

	/**
	 * The updated time.
	 *
	 * @var DateTimeImmutable
	 */
	protected $updated;

	/**
	 * The URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * The author as card with `name`, `url`, `photo` and `email`, as far as the handlers found them.
	 *
	 * @var array
	 */
	protected $author = array();

	/**
	 * The image of the post. This is not the photo of the author.
	 *
	 * @var string|array
	 */
	protected $photo = array();

	/**
	 * The response name.
	 *
	 * @var string
	 */
	protected $name;


	/**
	 * The site name, if available. The fallback for the name of the author.
	 *
	 * @example comment-meta
	 *
	 * @var string
	 */
	protected $site_name;

	/**
	 * The site icon, if available. The fallback for the photo of the author.
	 *
	 * @var string
	 */
	protected $site_icon;

	/**
	 * The response content.
	 *
	 * @var string
	 */
	protected $content;

	/**
	 * The response summary.
	 *
	 * @var string
	 */
	protected $summary;

	/**
	 * The categories.
	 *
	 * @var string|array
	 */
	protected $category;

	/**
	 * The response type.
	 *
	 * @example comment-meta
	 *
	 * @var string
	 */
	protected $response_type;

	/**
	 * The raw document as JSON, HTML, XML, ...
	 *
	 * @example comment-meta
	 *
	 * @var mixed
	 */
	protected $raw;

	/**
	 * Optional Parsed Properties to be Saved in Comment Meta.
	 *
	 * @var array
	 */
	protected $meta;

	/**
	 * Magic function for getter/setter.
	 *
	 * @param string $method
	 * @param array  $params
	 *
	 * @return void
	 */
	public function __call( $method, $params ) {
		if ( ! array_key_exists( 1, $params ) ) {
			$params[1] = false;
		}
		$var = strtolower( substr( $method, 4 ) );

		if ( strncasecmp( $method, 'get', 3 ) === 0 ) {
			return property_exists( $this, $var ) ? $this->$var : '';
		}

		if ( strncasecmp( $method, 'has', 3 ) === 0 ) {
			return property_exists( $this, $var ) && ! empty( $this->$var );
		}

		if ( strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = current( $params );
		}

		if ( strncasecmp( $method, 'add', 3 ) === 0 ) {
			if ( ! property_exists( $this, $var ) || ! $this->$var ) {
				call_user_func( array( $this, 'set_' . $var ), current( $params ) );
				return true;
			}

			return false;
		}
	}

	/**
	 * Generic adder.
	 *
	 * @param string $key   The object property name.
	 * @param mixed  $value The object property value.
	 *
	 * @return boolean
	 */
	public function add( $key, $value ) {
		if ( empty( $this->$key ) ) {
			call_user_func( array( $this, 'set_' . $key ), $value );
			return true;
		}

		return false;
	}

	/**
	 * Generic setter.
	 *
	 * @param string $key   The object property name.
	 * @param mixed  $value The object property value.
	 *
	 * @return void
	 */
	public function set( $key, $value ) {
		call_user_func( array( $this, 'set_' . $key ), $value );
	}

	/**
	 * Setter for "updated".
	 *
	 * @param mixed $updated
	 *
	 * @return void
	 */
	public function set_updated( $updated ) {
		if ( is_null( $updated ) ) {
			return;
		}
		if ( $updated instanceof DateTimeImmutable ) {
			$this->updated = $updated;
		} else {
			try {
				$this->updated = new \DateTimeImmutable( $updated );
			} catch ( \Throwable $e ) {
				$this->updated = new \DateTimeImmutable( 'now' );
			}
		}
	}

	/**
	 * Setter for "content".
	 *
	 * @param string $content
	 *
	 * @return void
	 */
	public function set_content( $content ) {
		$this->content = webmention_sanitize_html( $content );
	}

	/**
	 * Setter for "summary".
	 *
	 * @param mixed $summary
	 *
	 * @return void
	 */
	public function set_summary( $summary ) {
		$this->summary = webmention_sanitize_html( $summary );
	}

	/**
	 * Setter for "name".
	 *
	 * @param string $name
	 *
	 * @return void
	 */
	public function set_name( $name ) {
		$this->name = webmention_sanitize_html( $name );
	}

	/**
	 * Setter for "published".
	 *
	 * @param mixed $published
	 *
	 * @return void
	 */
	public function set_published( $published ) {
		if ( is_null( $published ) ) {
			return;
		}
		if ( $published instanceof DateTimeImmutable ) {
			$this->published = $published;
		} else {
			try {
				$this->published = new \DateTimeImmutable( $published );
			} catch ( \Throwable $e ) {
				$this->published = new \DateTimeImmutable( 'now' );
			}
		}
	}

	/**
	 * Setter for "author".
	 *
	 * @param mixed $author
	 *
	 * @return void
	 */
	public function set_author( $author ) {
		if ( is_string( $author ) ) {
			if ( wp_http_validate_url( $author ) ) {
				$this->author = array(
					'type' => 'card',
					'url'  => $author,
				);
			} else {
				$this->author = array(
					'type' => 'card',
					'name' => $author,
				);
			}
		} else {
			$this->author = $author;
		}
	}

	/**
	 * Getter for "author" with fallback to the site name, the site icon and the URL.
	 *
	 * @param string $property The author property to return
	 *
	 * @return array|string
	 */
	public function get_author( $property = null ) {
		$site_name = $this->get_site_name();
		$author    = array_filter(
			array_merge(
				array(
					'type'  => 'card',
					'name'  => $site_name ? $site_name : 'anonymous',
					'url'   => $this->url,
					'photo' => $this->site_icon,
				),
				array_filter( (array) $this->author )
			)
		);

		if ( ! $property ) {
			return $author;
		}

		return isset( $author[ $property ] ) ? $author[ $property ] : '';
	}

	/**
	 * Getter for "content" with fallback to "summary".
	 *
	 * @return string
	 */
	public function get_content() {
		$text_len = $this->str_length( $this->content );

		if ( ( 0 === $text_len ) ) {
			if ( $this->summary ) {
				return $this->summary;
			} elseif ( $this->name ) {
				return $this->name;
			}
		}

		// If this is set to a comment type or if if the content if less than maximum, always return.
		if ( 'comment' === $this->response_type || $text_len <= MAX_INLINE_MENTION_LENGTH ) {
			return $this->content;
		}

		// If there is a summary that isn't empty, return that, if not the name.
		if ( $this->summary ) {
			return $this->summary;
		} elseif ( $this->name ) {
			return $this->name;
		}

		// Fallback to excerpt template from registered comment type.
		return $this->get_excerpt_fallback();
	}

	/**
	 * Generate fallback content using the excerpt template from the registered comment type.
	 *
	 * @return string The generated excerpt or empty string.
	 */
	protected function get_excerpt_fallback() {
		if ( ! function_exists( 'get_webmention_comment_type_attr' ) || ! $this->response_type ) {
			return '';
		}

		$excerpt = get_webmention_comment_type_attr( $this->response_type, 'excerpt' );
		if ( ! $excerpt ) {
			return '';
		}

		$author_name = $this->get_author( 'name' );
		$url         = $this->url;
		$domain      = $url ? preg_replace( '/^www\./', '', wp_parse_url( $url, PHP_URL_HOST ) ) : '';

		return webmention_sanitize_html(
			sprintf(
				$excerpt,
				esc_html( $author_name ),
				'this',
				esc_url( $url ),
				esc_html( $domain )
			)
		);
	}

	/**
	 * Getter for response type with fallback to "mention".
	 *
	 * @return string
	 */
	public function get_response_type() {
		$response_type = $this->response_type ? $this->response_type : 'mention';
		// Reclassify short mentions as comments
		if ( 'mention' === $response_type ) {
			$text     = $this->content;
			$text_len = $this->str_length( $text );
			if ( ( 0 < $text_len ) && ( $text_len <= MAX_INLINE_MENTION_LENGTH ) ) {
				return 'comment';
			}
		}
		return $response_type;
	}

	/**
	 * String length function
	 *
	 * @return int
	 */
	public function str_length( $text ) {
		if ( ! is_string( $text ) ) {
			return 0;
		}
		return mb_strlen( wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES ) ) );
	}


	/**
	 * Getter for "published".
	 *
	 * @param DateTimeZone|null $timezone Optional Timezone Override.
	 * @return DateTimeImmutable
	 */
	public function get_published( $timezone = null ) {
		if ( ! $this->published && ! $this->published instanceof DateTimeImmutable ) {
			return new DateTimeImmutable();
		}

		if ( $timezone && $timezone instanceof DatetimeZone ) {
			return $this->published->setTimeZone( $timezone );
		}

		return $this->published;
	}

	/**
	 * Getter for "meta".
	 *
	 * @return array
	 */
	public function get_meta() {
		if ( ! is_array( $this->meta ) ) {
			return array();
		}

		return $this->meta;
	}

	/**
	 * Getter for "site_name".
	 *
	 * @return string The site name with host as fallback.
	 */
	public function get_site_name() {
		if ( ! $this->site_name && $this->url ) {
			return preg_replace(
				'/^www\./',
				'',
				wp_parse_url(
					$this->url,
					PHP_URL_HOST
				)
			);
		}

		return $this->site_name;
	}

	/**
	 * Check if all first class properties are set, so that no other handler has to add to the item.
	 *
	 * The fallback properties do not count: an author without a photo is not complete, even if there is
	 * a site icon.
	 *
	 * @return boolean true if nothing is missing.
	 */
	public function is_complete() {
		return $this->published
			&& $this->url
			&& $this->response_type
			&& ( $this->content || $this->summary )
			&& ! empty( $this->author['name'] )
			&& ! empty( $this->author['url'] )
			&& ! empty( $this->author['photo'] );
	}

	/**
	 * Check if all fields for a valid comment are available.
	 *
	 * @return boolean true if item has all required fields.
	 */
	public function verify() {
		// If there is no author information then try something else.
		if ( empty( $this->get_author( 'name' ) ) ) {
			return false;
		}

		// If this is a reply it needs a summary. Summary should be generated from content if no summary.
		if ( 'comment' === $this->get_response_type() && empty( $this->get_content() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the representative entry as array.
	 *
	 * @return array
	 */
	public function to_array() {
		$array = array();
		$vars  = get_object_vars( $this );

		foreach ( $vars as $key => $value ) {
			// if value is empty, try to get it from a getter.
			if ( empty( $value ) ) {
				$value = call_user_func( array( $this, 'get_' . $key ) );
			}

			// if value is still empty, ignore it for the array and continue.
			if ( ! empty( $value ) ) {
				$array[ $key ] = $value;
			}
		}

		return array_filter( $array );
	}

	/**
	 * Returns the representative entry as JF2 data.
	 *
	 * @return string The JF2 JSON
	 */
	public function to_json() {
		return wp_json_encode( $this->to_array() );
	}

	/**
	 * Returns the representative entry as a comment array.
	 *
	 * @return array
	 */
	public function to_commentdata_array() {
		$this->meta['avatar']   = $this->get_author( 'photo' );
		$this->meta['protocol'] = 'webmention'; // Since this is the Webmention plugin it should always be a Webmention.
		$this->meta['url']      = $this->get_url(); // This is the parsed URL, which may or may not be the same as the source URL, which will be added as source_url.
		$comment                = array(
			'comment_author'       => $this->get_author( 'name' ),
			'comment_author_email' => $this->get_author( 'email' ),
			'comment_author_url'   => $this->get_author( 'url' ),
			'comment_content'      => $this->get_content(),
			'comment_date'         => $this->get_published( wp_timezone() )->format( 'Y-m-d H:i:s' ),
			'comment_date_gmt'     => $this->get_published( new DatetimeZone( 'GMT' ) )->format( 'Y-m-d H:i:s' ),
			'comment_type'         => $this->get_response_type(),
			'comment_meta'         => array_filter( $this->get_meta() ),
			'remote_source_raw'    => $this->get_raw(),
		);

		// Define defaults to ensure required keys always exist (fixes PHP warning for empty comment_content)
		$defaults = array(
			'comment_author'       => '',
			'comment_author_email' => '',
			'comment_author_url'   => '',
			'comment_content'      => '',
			'comment_type'         => 'mention',
			'comment_parent'       => 0,
			'user_id'              => 0,
		);

		$comment = wp_parse_args( array_filter( $comment ), $defaults );

		return apply_filters( 'webmention_item_commentdata_array', $comment, $this );
	}

	/**
	 * Returns a property from the raw data in the webmention_item.
	 *
	 * @param string $key Property key.
	 *
	 * @return mixed Return property or false if not found.
	 */
	public function get_raw( $key = null ) {
		if ( ! $key ) {
			return $this->raw;
		}

		$raw = $this->raw;

		if ( array_key_exists( $key, $raw ) ) {
			return $raw[ $key ];
		}

		return false;
	}

	/**
	 * Add raw data to the webmention_item.
	 *
	 * @param array $raw The raw data used to parse the webmention_item.
	 *
	 * @return void
	 */
	public function add_raw( $raw ) {
		if ( ! is_array( $this->raw ) ) {
			$this->raw = $raw;
		}

		$this->raw = array_merge( $this->raw, $raw );
	}
}
