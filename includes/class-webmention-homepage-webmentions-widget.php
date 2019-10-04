<?php

class Homepage_Webmentions_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'Webmention_Homepage_Webmentions_Widget',
			esc_html__( 'Homepage Webmentions Widget', 'webmention' ),
			[
				'classname'   => 'webmention_homepage_webmentions_widget',
				'description' => esc_html__( 'A widget that allows you to display your homepage webmentions.', 'webmention' ),
			]
		);
	} // end constructor

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		// phpcs:ignore
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title']; // phpcs:ignore
		}

		echo webmention_get_home_webmentions();

		// phpcs:ignore
		if ( isset( $args['after_widget'] ) ) {
			echo $args['after_widget']; // phpcs:ignore
		}
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		array_walk_recursive( $new_instance, 'sanitize_text_field' );

		return $new_instance;
	}


	/**
	 * Create the form for the Widget admin
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		?>
		<p><label for="title"><?php esc_html_e( 'Title: ', 'webmention' ); ?></label>
			<input type="text" size="30" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?> id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
			value="<?php echo esc_html( ifset( $instance['title'] ) ); ?>" /></p>
		<p>
		<?php
	}
}
