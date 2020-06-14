<?php
/**
 * Comment Type Class
 *
 */

/**
 * Class used for interacting with comment types.
 *
 * @see register_webmention_comment_type()
 */
final class Webmention_Comment_Type {
	/**
	 * Comment type key.
	 *
	 * @var string $name
	 */
	public $name;

	/**
	 * Name of the comment type. Usually plural.
	 *
	 * @var string $label
	 */
	public $label;


	/**
	 * Name of the comment type. Usually plural.
	 *
	 * @var string $singular
	 */
	public $singular;

	/**
	 * A short descriptive summary of what the comment type is.
	 *
	 * Default empty.
	 *
	 * @var string $description
	 */
	public $description = '';

	/**
	 * Constructor.
	 *
	 * Will populate object properties from the provided arguments and assign other
	 * default properties based on that information.
	 *
	 *
	 * @see register_webmention_comment_type()
	 *
	 * @param string       $post_type Post type key.
	 * @param array|string $args      Optional. Array or string of arguments for registering a post type.
	 *                                Default empty array.
	 */
	public function __construct( $post_type, $args = array() ) {
		$this->name = $post_type;

		$this->set_props( $args );
	}

	/**
	 * Sets comment type properties.
	 *
	 * @param array|string $args Array or string of arguments for registering a comment type.
	 */
	public function set_props( $args ) {
		$args = wp_parse_args( $args );

		/**
		 * Filters the arguments for registering a comment type.
		 *
		 * @param array  $args      Array of arguments for registering a comment type.
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
}
