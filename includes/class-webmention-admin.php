<?php
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

		add_settings_field( 'discussion_settings', esc_html__( 'Webmention Settings', 'webmention' ), array( 'Webmention_Admin', 'discussion_settings' ), 'discussion', 'default' );

		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Webmention_Admin', 'add_meta_boxes' ) );

		add_filter( 'plugin_action_links', array( 'Webmention_Admin', 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( 'Webmention_Admin', 'plugin_row_meta' ), 10, 2 );

		add_action( 'admin_comment_types_dropdown', array( 'Webmention_Admin', 'comment_types_dropdown' ) );
		add_filter( 'manage_edit-comments_columns', array( 'Webmention_Admin', 'comment_columns' ) );
		add_filter( 'manage_comments_custom_column', array( 'Webmention_Admin', 'manage_comments_custom_column' ), 10, 2 );

		add_filter( 'comment_row_actions', array( 'Webmention_Admin', 'comment_row_actions' ), 13, 2 );
		add_filter( 'comment_unapproved_to_approved', array( 'Webmention_Admin', 'transition_to_approvelist' ), 10 );

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

		/* translators: 1. settings page URL */
		printf( __( 'Based on your feedback and to improve the user experience, we decided to move the settings to a separate <a href="%1$s">settings-page</a>.', 'webmention' ), $path );
	}

	public static function meta_boxes( $object, $box ) {
		wp_nonce_field( 'webmention_comment_metabox', 'webmention_comment_nonce' );

		if ( ! $object instanceof WP_Comment ) {
			return;
		}
		load_template( dirname( __FILE__ ) . '/../templates/webmention-edit-comment-form.php' );
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
		echo esc_html( get_webmention_comment_type_string( $comment_id ) );
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
		$links[] = sprintf( '<a href="%s">%s</a>', admin_url( $path ), esc_html__( 'Settings', 'webmention' ) );

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
			$links[] = sprintf( '<a href="%s">%s</a>', get_the_permalink( $home_mentions ), esc_html__( 'Homepage Webmentions', 'webmention' ) );
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
		$types['webmention'] = esc_html__( 'Webmentions', 'webmention' );

		return $types;
	}

	/**
	 * Add comment-type as column in WP-Admin
	 *
	 * @param array $columns the list of column names
	 */
	public static function comment_columns( $columns ) {
		$columns['comment_type'] = esc_html__( 'Comment-Type', 'webmention' );

		return $columns;
	}

	public static function comment_row_actions( $actions, $comment ) {
		$query = array(
			'_wpnonce' => wp_create_nonce( "approve-comment_$comment->comment_ID" ),
			'action'   => 'approvecomment',
			'domain'   => 'true',
			'c'        => $comment->comment_ID,
		);

		$approve_url = admin_url( 'comment.php' );
		$approve_url = add_query_arg( $query, $approve_url );

		$status = wp_get_comment_status( $comment );
		if ( 'unapproved' === $status ) {
			$actions['domainapprovelist'] = sprintf( '<a href="%1$s" aria-label="%2$s">%2$s</a>', esc_url( $approve_url ), esc_attr__( 'Approve & Always Allow', 'webmention' ) );
		}
		return $actions;
	}

	public static function add_webmention_approve_domain( $host ) {
		$approvelist   = get_webmention_approve_domains();
		$approvelist[] = $host;
		$approvelist   = array_unique( $approvelist );
		$approvelist   = implode( "\n", $approvelist );
		update_option( 'webmention_approve_domains', $approvelist );
	}

	public static function transition_to_approvelist( $comment ) {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			return;
		}
		if ( isset( $_REQUEST['domain'] ) ) {
			$url = get_comment_meta( $comment->comment_ID, 'webmention_source_url', true );
			if ( ! $url ) {
				return;
			}
			$host = webmention_extract_domain( $url );
			self::add_webmention_approve_domain( $host );
		}
	}

	/**
	 * Add admin menu entry
	 */
	public static function admin_menu() {
		$title = esc_html__( 'Webmention', 'webmention' );
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
				'title'   => esc_html__( 'Overview', 'webmention' ),
				'content' =>
					'<p>' . esc_html__( 'Webmention is a simple way to notify any URL when you mention it on your site. From the receiver\'s perspective, it\'s a way to request notifications when other sites mention it.', 'webmention' ) . '</p>' .
					'<p>' . esc_html__( 'A Webmention is a notification that one URL links to another. For example, Alice writes an interesting post on her blog. Bob then writes a response to her post on his own site, linking back to Alice\'s original post. Bob\'s publishing software sends a Webmention to Alice notifying that her article was replied to, and Alice\'s software can show that reply as a comment on the original post.', 'webmention' ) . '</p>' .
					'<p>' . esc_html__( 'Sending a Webmention is not limited to blog posts, and can be used for additional kinds of content and responses as well. For example, a response can be an RSVP to an event, an indication that someone "likes" another post, a "bookmark" of another post, and many others. Webmention enables these interactions to happen across different websites, enabling a distributed social web.', 'webmention' ) . '</p>',
			)
		);

		get_current_screen()->add_help_tab(
			array(
				'id'      => 'screencast',
				'title'   => esc_html__( 'Screencast', 'webmention' ),
				'content' =>
					'<p><iframe src="https://player.vimeo.com/video/85217592?app_id=122963" width="640" height="480" frameborder="0" title="Add the Webmention plugin to your WordPress weblog" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe></p>',
			)
		);

		get_current_screen()->add_help_tab(
			array(
				'id'      => 'indieweb',
				'title'   => esc_html__( 'The IndieWeb', 'webmention' ),
				'content' =>
					'<p>' . esc_html__( 'The IndieWeb is a people-focused alternative to the "corporate web".', 'webmention' ) . '</p>' .
					'<p>
						<strong>' . esc_html__( 'Your content is yours', 'webmention' ) . '</strong><br />' .
						esc_html__( 'When you post something on the web, it should belong to you, not a corporation. Too many companies have gone out of business and lost all of their users’ data. By joining the IndieWeb, your content stays yours and in your control.', 'webmention' ) .
					'</p>' .
					'<p>
						<strong>' . esc_html__( 'You are better connected', 'webmention' ) . '</strong><br />' .
						esc_html__( 'Your articles and status messages can go to all services, not just one, allowing you to engage with everyone. Even replies and likes on other services can come back to your site so they’re all in one place.', 'webmention' ) .
					'</p>' .
					'<p>
						<strong>' . esc_html__( 'You are in control', 'webmention' ) . '</strong><br />' .
						__( 'You can post anything you want, in any format you want, with no one monitoring you. In addition, you share simple readable links such as example.com/ideas. These links are permanent and will always work.', 'webmention' ) .
					'</p>',
			)
		);

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'webmention' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://indieweb.org/Webmention">IndieWeb Wiki page</a>', 'webmention' ) . '</p>' .
			'<p>' . __( '<a href="https://webmention.rocks/">Test suite</a>', 'webmention' ) . '</p>' .
			'<p>' . __( '<a href="https://www.w3.org/TR/webmention/">W3C Spec</a>', 'webmention' ) . '</p>'
		);
	}

	public static function register_settings() {
		register_setting(
			'webmention',
			'webmention_disable_selfpings_same_url',
			array(
				'type'         => 'boolean',
				'description'  => esc_html__( 'Disable self Webmentions on the same URL', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention',
			'webmention_disable_selfpings_same_domain',
			array(
				'type'         => 'boolean',
				'description'  => esc_html__( 'Disable self Webmentions on the same domain', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 0,
			)
		);
		register_setting(
			'webmention',
			'webmention_disable_media_mentions',
			array(
				'type'         => 'boolean',
				'description'  => esc_html__( 'Disable sending webmentions for media links (image, video, and audio tags)', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention',
			'webmention_support_post_types',
			array(
				'type'         => 'string',
				'description'  => esc_html__( 'Enable Webmention support for post types', 'webmention' ),
				'show_in_rest' => true,
				'default'      => array( 'post', 'pages' ),
			)
		);
		register_setting(
			'webmention',
			'webmention_show_comment_form',
			array(
				'type'         => 'boolean',
				'description'  => esc_html__( 'Show Webmention comment-form', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 1,
			)
		);
		register_setting(
			'webmention',
			'webmention_comment_form_text',
			array(
				'type'         => 'string',
				'description'  => esc_html__( 'Change the text of the Webmention comment-form', 'webmention' ),
				'show_in_rest' => true,
				'default'      => '',
			)
		);
		register_setting(
			'webmention',
			'webmention_home_mentions',
			array(
				'type'         => 'int',
				'description'  => esc_html__( 'Where to direct Mentions of the home page', 'webmention' ),
				'show_in_rest' => true,
				'default'      => 0,
			)
		);
		register_setting(
			'webmention',
			'webmention_approve_domains',
			array(
				'type'         => 'string',
				'description'  => esc_html__( 'Automatically approve Webmentions from these domains', 'webmention' ),
				'show_in_rest' => false,
				'default'      => 'indieweb.org',
			)
		);
		register_setting(
			'webmention',
			'webmention_avatars',
			array(
				'type'         => 'int',
				'description'  => esc_html__( 'Show Avatars on Webmentions', 'webmention' ),
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
			'<p>' . esc_html__( 'Webmentions are an explicit feature of your content management system: by sending a webmention to the webmention endpoint of this website, you request the server to take notice of that referral and process it. As long as public content is concerned (i.e. you are not sending a private webmention), such use of this website’s webmention endpoint implies that you are aware of it being published.', 'webmention' ) . '</p>' .
			'<p>' . esc_html__( 'You can at any time request the removal of one or all webmentions originating from your website.', 'webmention' ) . '</p>' .

			'<h3>' . esc_html__( 'Processing', 'webmention' ) . '</h3>' .
			'<p>' . esc_html__( 'Incoming Webmentions are handled as a request to process personal data that you make available by explicitly providing metadata in your website\'s markup.', 'webmention' ) . '</p>' .

			'<h3>' . esc_html__( 'Publishing', 'webmention' ) . '</h3>' .
			'<p>' . esc_html__( 'An incoming Webmention request is by design a request for publishing a comment from elsewhere on the web; this is what the protocol was designed for and why it is active on your website.', 'webmention' ) . '</p>' .

			'<h3>' . esc_html__( 'Personal data', 'webmention' ) . '</h3>' .
			'<p>' . esc_html__( 'The Webmention plugin processes the following data (if available):', 'webmention' ) . '</p>' .

			'<ul>' .
				'<li>' . esc_html__( 'Your name', 'webmention' ) . '</li>' .
				'<li>' . esc_html__( 'The profile picture from your website', 'webmention' ) . '</li>' .
				'<li>' . esc_html__( 'The URL of your website', 'webmention' ) . '</li>' .
				'<li>' . esc_html__( 'Personal information you include in your post', 'webmention' ) . '</li>' .
			'<ul>';

		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'Webmention', 'webmention' ), $content );
		}
	}
}
