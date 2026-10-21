<?php
/**
 * Comment Type Class
 */

namespace Webmention;

/**
 * Class used for interacting with comment types.
 *
 * @see register_webmention_comment_type()
 */
final class Comment_Type {
	/**
	 * Comment type key.
	 *
	 * @var string $name
	 */
	public $name = '';

	/**
	 * Name of the comment type. Usually plural.
	 *
	 * @var string $label
	 */
	public $label = 'Mentions';

	/**
	 * Name of the comment type. Singular.
	 *
	 * @var string $singular
	 */
	public $singular = 'Mention';

	/**
	 * SVG Icon of the Comment Type. Optional.
	 *
	 * Defaults to the original `comment` SVG from `@wordpress/icons` 18.0.0.
	 *
	 * @var string $icon
	 */
	public $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M18 5.5H6C5.30964 5.5 4.75 6.05964 4.75 6.75V16V18.4096C4.75 18.8725 5.32471 19.0869 5.62784 18.7371L8 16H18C18.6904 16 19.25 15.4404 19.25 14.75V6.75C19.25 6.05964 18.6904 5.5 18 5.5Z" vector-effect="non-scaling-stroke" /></svg>';

	/**
	 * Class to use when displaying the comment. Optional.
	 *
	 * @var string $class
	 */
	public $class = 'p-mention';

	/**
	 * A short descriptive summary of what the comment type is.
	 *
	 * Default empty.
	 *
	 * @var string $description
	 */
	public $description = '';

	/**
	 * An excerpt to show instead of the "real" content.
	 *
	 * @var string $excerpt
	 */
	public $excerpt = '';

	/**
	 * Constructor.
	 *
	 * Will populate object properties from the provided arguments and assign other
	 * default properties based on that information.
	 *
	 * @see register_webmention_comment_type()
	 *
	 * @param string       $post_type Post type key.
	 * @param array|string $args      Optional. Array or string of arguments for registering a post type.
	 *                                Default empty array.
	 */
	public function __construct( $post_type, $args = array() ) {
		$this->name = $post_type;

		$this->set_properties( $args );
	}

	/**
	 * Sets comment type properties.
	 *
	 * @param array|string $args Array or string of arguments for registering a comment type.
	 */
	public function set_properties( $args ) {
		$args = wp_parse_args( $args );

		/**
		 * Filters the arguments for registering a comment type.
		 *
		 * @param array  $args         Array of arguments for registering a comment type.
		 * @param string $comment_type Comment type key.
		 */
		$args = apply_filters( 'register_webmention_comment_type_args', $args, $this->name );

		// Args prefixed with an underscore are reserved for internal use.
		$defaults = array(
			'description' => '',
		);

		$args = array_merge( $defaults, $args );

		$args['name'] = $this->name;

		foreach ( $args as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}
	}

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
			return $this->$var;
		}

		if ( strncasecmp( $method, 'has', 3 ) === 0 ) {
			return ! empty( $this->$var );
		}

		if ( strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = current( $params );
		}
	}

	public function get( $param ) {
		if ( isset( $this->$param ) ) {
			return $this->$param;
		}

		return null;
	}
}
