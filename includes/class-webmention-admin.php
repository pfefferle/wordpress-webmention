<?php
add_action( 'admin_menu', array( 'Webmention_Admin', 'admin_menu' ) );

/**
 * Webmention Admin Class
 *
 * @author Matthias Pfefferle
 */
class Webmention_Admin {
	/**
	 * Register Webmention admin settings.
	 */
	public static function init() {
		self::register_settings();

		add_settings_field( 'webmention_discussion_settings', __( 'Webmention Settings', 'webmention' ), array( 'Webmention_Admin', 'discussion_settings' ), 'discussion', 'default' );

		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Webmention_Admin', 'add_meta_boxes' ) );

		add_filter( 'plugin_action_links', array( 'Webmention_Admin', 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( 'Webmention_Admin', 'plugin_row_meta' ), 10, 2 );

		add_action( 'admin_comment_types_dropdown', array( 'Webmention_Admin', 'comment_types_dropdown' ) );
		add_filter( 'manage_edit-comments_columns', array( 'Webmention_Admin', 'comment_columns' ) );
		add_filter( 'manage_comments_custom_column', array( 'Webmention_Admin', 'manage_comments_custom_column' ), 10, 2 );

		self::add_privacy_policy_content();
	}

	/**
	 * Add Webmention options to the WordPress discussion settings page.
	 */
	public static function discussion_settings() {
		_e( 'We decided to move the settings to a dedicated page.', 'webmention' );
	}

	public static function meta_boxes( $object, $box ) {
		wp_nonce_field( 'webmention_comment_metabox', 'webmention_comment_nonce' );

		if ( ! $object instanceof WP_Comment ) {
			return;
		}
?>
<label><?php _e( 'Webmention Target', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $object->comment_ID, 'webmention_target_url', true ); ?>" />
<br />

<label><?php _e( 'Webmention Target Fragment', 'webmention' ); ?></label>
<input type="text" class="widefat" disabled value="<?php echo get_comment_meta( $object->comment_ID, 'webmention_target_fragment', true ); ?>" />
<br />

<label><?php _e( 'Webmention Source', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $object->comment_ID, 'webmention_source_url', true ); ?>" />
<br />

<label><?php _e( 'Webmention Creation Time', 'webmention' ); ?></label>
<input type="url" class="widefat" disabled value="<?php echo get_comment_meta( $object->comment_ID, 'webmention_created_at', true ); ?>" />
<br />
<?php
	}

	/**
	 * Add comment-type as column in WP-Admin
	 *
	 * @param array $column the column to implement
	 * @param int $comment_ID the comment id
	 */
	public static function manage_comments_custom_column( $column, $comment_ID ) {
		if ( 'comment_type' == $column ) {
			_e( get_comment_type( $comment_ID ), 'webmention' );
		}
	}

	/**
	 * Add an action link
	 *
	 * @param array $links the settings links
	 * @param string $file the plugin filename
	 *
	 * @return array the filtered array
	 */
	public static function plugin_action_links( $links, $file ) {
		if ( stripos( $file, 'webmention' ) === false || ! function_exists( 'admin_url' ) ) {
			return $links;
		}

		$links[] = sprintf( '<a href="%s">%s</a>', admin_url( 'options-discussion.php#webmention' ), __( 'Settings', 'webmention' ) );

		return $links;
	}

	/**
	 * Add a plugin meta link
	 *
	 * @param array $links the settings links
	 * @param string $file the plugin filename
	 *
	 * @return array the filtered array
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( stripos( $file, 'webmention' ) === false || ! function_exists( 'admin_url' ) ) {
			return $links;
		}

		$home_mentions = get_option( 'webmention_home_mentions' );

		if ( $home_mentions ) {
			$links[] = sprintf( '<a href="%s">%s</a>', get_the_permalink( $home_mentions ), __( 'Homepage Webmentions', 'webmention' ) );
		}

		return $links;
	}

	/**
	 * Create a  meta boxes to be displayed on the comment editor screen.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'webmention-meta',
			esc_html__( 'Webmention Data', 'webmention' ),
			array( 'Webmention_Plugin', 'meta_boxes' ),
			'comment',
			'normal',
			'default'
		);
	}

	/**
	 * Extend the "filter by comment type" of in the comments section
	 * of the admin interface with "webmention"
	 *
	 * @param array $types the different comment types
	 *
	 * @return array the filtert comment types
	 */
	public static function comment_types_dropdown( $types ) {
		$types['webmention'] = __( 'Webmentions', 'webmention' );

		return $types;
	}

	/**
	 * Add comment-type as column in WP-Admin
	 *
	 * @param array $columns the list of column names
	 */
	public static function comment_columns( $columns ) {
		$columns['comment_type'] = __( 'Comment-Type', 'webmention' );

		return $columns;
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		add_options_page(
			'Webmention',
			'Webmention',
			'manage_options',
			'webmention',
			array( 'Webmention_Admin', 'settings_page' )
		);
	}

	/**
	 * Load settings page
	 */
	public static function settings_page() {
		load_template( dirname( __FILE__ ) . '/../templates/webmention-settings.php' );
	}

	public static function register_settings() {
		register_setting(
			'webmention', 'webmention_disable_selfpings_same_url', array(
				'type'         => 'boolean',
				'description'  => __( 'Disable Self Webmentions on the Same URL', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention', 'webmention_disable_selfpings_same_domain', array(
				'type'         => 'boolean',
				'description'  => __( 'Disable Self Webmentions on the Same Domain', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 0,
			)
		);
		register_setting(
			'webmention', 'webmention_support_pages', array(
				'type'         => 'boolean',
				'description'  => __( 'Enable Webmention Support for Pages', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention', 'webmention_show_comment_form', array(
				'type'         => 'boolean',
				'description'  => __( 'Show Webmention Comment Form', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention', 'webmention_home_mentions', array(
				'type'         => 'int',
				'description'  => __( 'Where to Direct Mentions of the Home Page', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 0,
			)
		);
	}

	/**
	 * Add recommended privacy content
	 */
	public static function add_privacy_policy_content() {
		$content =
			'<p>' . __( 'Webmentions are an explicit feature of your content management system: by sending a webmention to the webmention endpoint of this website, you request the server to take notice of that referral and process it. As long as public content is concerned (i.e. you are not sending a private webmention), such use of this website’s webmention endpoint implies that you are aware of it being published.', 'webmention' ) . '</p>' .
			'<p>' . __( 'You can at any time request the removal of one or all webmentions originating from your website.', 'webmention' ) . '</p>';

		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'Webmention', 'webmention' ), $content );
		}
	}
}
