<?php
add_action( 'admin_init', array( 'Webmention_Admin', 'init' ) );
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

		add_settings_field( 'discussion_settings', __( 'Webmention Settings', 'webmention' ), array( 'Webmention_Admin', 'discussion_settings' ), 'discussion', 'default' );

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
		if ( class_exists( 'Indieweb_Plugin' ) ) {
			$path = 'admin.php?page=webmention';
		} else {
			$path = 'options-general.php?page=webmention';
		}

		printf( __( 'Based on your feedback and to improve the user experience, we decided to move the settings to a separate <a href="%1$s">settings-page</a>.', 'webmention' ), $path );
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
	 * @param int $comment_id the comment id
	 */
	public static function manage_comments_custom_column( $column, $comment_id ) {
		if ( 'comment_type' !== $column ) {
			return;
		}
		$type = get_comment_type( $comment_id );
		switch ( $type ) {
			case 'trackback':
				_e( 'Trackback', 'webmention' );
				break;
			case 'pingback':
				_e( 'Pingback', 'webmention' );
				break;
			case 'comment':
				_ex( 'Comment', 'noun', 'webmention' );
				break;
			case 'webmention':
				_e( 'Webmention', 'webmention' );
				break;
			default:
				echo $type;
		};
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
		if ( class_exists( 'Indieweb_Plugin' ) ) {
			$path = 'admin.php?page=webmention';
		} else {
			$path = 'options-general.php?page=webmention';
		}
		$links[] = sprintf( '<a href="%s">%s</a>', admin_url( $path ), __( 'Settings', 'webmention' ) );

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
			array( 'Webmention_Admin', 'meta_boxes' ),
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
		$title = __( 'Webmention', 'webmention' );
		// If the IndieWeb Plugin is installed use its menu.
		if ( class_exists( 'IndieWeb_Plugin' ) ) {
			$options_page = add_submenu_page(
				'indieweb',
				$title,
				$title,
				'manage_options',
				'webmention',
				array( 'Webmention_Admin', 'settings_page' )
			);
		} else {
			$options_page = add_options_page(
				$title,
				$title,
				'manage_options',
				'webmention',
				array( 'Webmention_Admin', 'settings_page' )
			);
		}

		add_action( 'load-' . $options_page, array( 'Webmention_Admin', 'add_help_tab' ) );
	}

	/**
	 * Load settings page
	 */
	public static function settings_page() {
		add_thickbox();
		wp_enqueue_script( 'plugin-install' );
		load_template( dirname( __FILE__ ) . '/../templates/webmention-settings.php' );
	}

	public static function add_help_tab() {
		get_current_screen()->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => __( 'Overview', 'webmention' ),
				'content' =>
					'<p>' . __( 'Webmention is a simple way to notify any URL when you mention it on your site. From the receiver\'s perspective, it\'s a way to request notifications when other sites mention it.', 'webmention' ) . '</p>' .
					'<p>' . __( 'A Webmention is a notification that one URL links to another. For example, Alice writes an interesting post on her blog. Bob then writes a response to her post on his own site, linking back to Alice\'s original post. Bob\'s publishing software sends a Webmention to Alice notifying that her article was replied to, and Alice\'s software can show that reply as a comment on the original post.', 'webmention' ) . '</p>' .
					'<p>' . __( 'Sending a Webmention is not limited to blog posts, and can be used for additional kinds of content and responses as well. For example, a response can be an RSVP to an event, an indication that someone "likes" another post, a "bookmark" of another post, and many others. Webmention enables these interactions to happen across different websites, enabling a distributed social web.', 'webmention' ) . '</p>',
			)
		);

		get_current_screen()->add_help_tab(
			array(
				'id'      => 'screencast',
				'title'   => __( 'Screencast', 'webmention' ),
				'content' =>
					'<p><iframe src="https://player.vimeo.com/video/85217592?app_id=122963" width="640" height="480" frameborder="0" title="Add the Webmention plugin to your WordPress weblog" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe></p>',
			)
		);

		get_current_screen()->add_help_tab(
			array(
				'id'      => 'indieweb',
				'title'   => __( 'The IndieWeb', 'webmention' ),
				'content' =>
					'<p>' . __( 'The IndieWeb is a people-focused alternative to the "corporate web".', 'webmention' ) . '</p>' .
					'<p>
						<strong>' . __( 'Your content is yours', 'webmention' ) . '</strong><br />' .
						__( 'When you post something on the web, it should belong to you, not a corporation. Too many companies have gone out of business and lost all of their users’ data. By joining the IndieWeb, your content stays yours and in your control.', 'webmention' ) .
					'</p>' .
					'<p>
						<strong>' . __( 'You are better connected', 'webmention' ) . '</strong><br />' .
						__( 'Your articles and status messages can go to all services, not just one, allowing you to engage with everyone. Even replies and likes on other services can come back to your site so they’re all in one place.', 'webmention' ) .
					'</p>' .
					'<p>
						<strong>' . __( 'You are in control', 'webmention' ) . '</strong><br />' .
						__( 'You can post anything you want, in any format you want, with no one monitoring you. In addition, you share simple readable links such as example.com/ideas. These links are permanent and will always work.', 'webmention' ) .
					'</p>',
			)
		);

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'webmention' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://indieweb.org/Webmention">IndieWeb Wiki page</a>', 'webmention' ) . '</p>' .
			'<p>' . __( '<a href="https://webmention.rocks/">Test suite</a>', 'webmention' ) . '</p>' .
			'<p>' . __( '<a href="https://www.w3.org/TR/webmention/">W3C Spec</a>', 'webmention' ) . '</p>'
		);
	}

	public static function register_settings() {
		register_setting(
			'webmention', 'webmention_disable_selfpings_same_url', array(
				'type'         => 'boolean',
				'description'  => __( 'Disable self Webmentions on the same URL', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention', 'webmention_disable_selfpings_same_domain', array(
				'type'         => 'boolean',
				'description'  => __( 'Disable self Webmentions on the same domain', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 0,
			)
		);
		register_setting(
			'webmention', 'webmention_support_pages', array(
				'type'         => 'boolean',
				'description'  => __( 'Enable Webmention support for pages', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention', 'webmention_show_comment_form', array(
				'type'         => 'boolean',
				'description'  => __( 'Show Webmention comment-form', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention', 'webmention_comment_form_text', array(
				'type'         => 'string',
				'description'  => __( 'Change the text of the Webmention comment-form', 'webmention' ),
				'show_in_rest' => true,
				'default'      => '',
			)
		);
		register_setting(
			'webmention', 'webmention_home_mentions', array(
				'type'         => 'int',
				'description'  => __( 'Where to direct Mentions of the home page', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 0,
			)
		);
		register_setting(
			'webmention', 'webmention_approve_domains', array(
				'type'         => 'string',
				'description'  => __( 'Automatically approve Webmentions from these domains', 'webmention' ),
				'show_in_rest' => false,
				'default'      => 'indieweb.org',
			)
		);
		register_setting(
			'webmention', 'webmention_avatars', array(
				'type'         => 'int',
				'description'  => __( 'Show Avatars on Webmentions', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
	}

	/**
	 * Add recommended privacy content
	 */
	public static function add_privacy_policy_content() {
		$content =
			'<p>' . __( 'Webmentions are an explicit feature of your content management system: by sending a webmention to the webmention endpoint of this website, you request the server to take notice of that referral and process it. As long as public content is concerned (i.e. you are not sending a private webmention), such use of this website’s webmention endpoint implies that you are aware of it being published.', 'webmention' ) . '</p>' .
			'<p>' . __( 'You can at any time request the removal of one or all webmentions originating from your website.', 'webmention' ) . '</p>' .

			'<h3>' . __( 'Processing', 'webmention' ) . '</h3>' .
			'<p>' . __( 'Incoming Webmentions are handled as a request to process personal data that you make available by explicitly providing metadata in your website\'s markup.', 'webmention' ) . '</p>' .

			'<h3>' . __( 'Publishing', 'webmention' ) . '</h3>' .
			'<p>' . __( 'An incoming Webmention request is by design a request for publishing a comment from elsewhere on the web; this is what the protocol was designed for and why it is active on your website.', 'webmention' ) . '</p>' .

			'<h3>' . __( 'Personal data', 'webmention' ) . '</h3>' .
			'<p>' . __( 'The Webmention plugin processes the following data (if available):', 'webmention' ) . '</p>' .

			'<ul>' .
				'<li>' . __( 'Your name', 'webmention' ) . '</li>' .
				'<li>' . __( 'The profile picture from your website', 'webmention' ) . '</li>' .
				'<li>' . __( 'The URL of your website', 'webmention' ) . '</li>' .
				'<li>' . __( 'Personal information you include in your post', 'webmention' ) . '</li>' .
			'<ul>';

		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'Webmention', 'webmention' ), $content );
		}
	}
}
