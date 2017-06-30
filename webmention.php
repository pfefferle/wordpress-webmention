<?php
/**
 * Plugin Name: Webmention
 * Plugin URI: https://github.com/pfefferle/wordpress-webmention
 * Description: Webmention support for WordPress posts
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * Version: 3.4.1
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: webmention
 * Domain Path: /languages
 */

defined( 'WEBMENTION_COMMENT_APPROVE' ) || define( 'WEBMENTION_COMMENT_APPROVE', 0 );
defined( 'WEBMENTION_COMMENT_TYPE' ) || define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );

define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

defined( 'WEBMENTION_PROCESS_TYPE' ) || define( 'WEBMENTION_PROCESS_TYPE', WEBMENTION_PROCESS_TYPE_SYNC );

add_action( 'plugins_loaded', array( 'Webmention_Plugin', 'init' ) );

/**
 * Webmention Plugin Class
 *
 * @author Matthias Pfefferle
 */
class Webmention_Plugin {

	/**
	 * Initialize Webmention Plugin
	 */
	public static function init() {
		// Add a new feature type to posts for webmentions
		add_post_type_support( 'post', 'webmentions' );
		if ( 1 == get_option( 'webmention_support_pages' ) ) {
			add_post_type_support( 'page', 'webmentions' );
		}
		if ( WP_DEBUG ) {
			require_once( dirname( __FILE__ ) . '/includes/debug.php' );
		}

		// list of various public helper functions
		require_once( dirname( __FILE__ ) . '/includes/functions.php' );

		// initialize Webmention Sender
		require_once( dirname( __FILE__ ) . '/includes/class-webmention-sender.php' );
		add_action( 'init', array( 'Webmention_Sender', 'init' ) );

		// initialize Webmention Receiver
		require_once( dirname( __FILE__ ) . '/includes/class-webmention-receiver.php' );
		add_action( 'init', array( 'Webmention_Receiver', 'init' ) );

		// Default Comment Status
		add_filter( 'get_default_comment_status', array( 'Webmention_Plugin', 'get_default_comment_status' ), 11, 3 );
		add_filter( 'pings_open', array( 'Webmention_Plugin', 'pings_open' ), 10, 2 );

		// initialize admin settings
		add_action( 'admin_init', array( 'Webmention_Plugin', 'admin_init' ) );

		add_action( 'admin_comment_types_dropdown', array( 'Webmention_Plugin', 'comment_types_dropdown' ) );
		add_action( 'comment_form_after', array( 'Webmention_Plugin', 'comment_form' ), 11 );

		register_setting( 'discussion', 'webmention_disable_selfpings_same_url', array(
			'type' => 'boolean',
			'description' => __( 'Disable Self Webmentions on the Same URL', 'webmention' ),
			'show_in_rest' => true,
			'default' => 1,
		) );
		register_setting( 'discussion', 'webmention_disable_selfpings_same_domain', array(
			'type' => 'boolean',
			'description' => __( 'Disable Self Webmentions on the Same Domain', 'webmention' ),
			'show_in_rest' => true,
			'default' => 0,
		) );
		register_setting( 'discussion', 'webmention_support_pages', array(
			'type' => 'boolean',
			'description' => __( 'Enable Webmention Support for Pages', 'webmention' ),
			'show_in_rest' => true,
			'default' => 1,
		) );
		register_setting( 'discussion', 'webmention_show_comment_form', array(
			'type' => 'boolean',
			'description' => __( 'Show Webmention Comment Form', 'webmention' ),
			'show_in_rest' => true,
			'default' => 1,
		) );
		register_setting( 'discussion', 'webmention_home_mentions', array(
			'type' => 'int',
			'description' => __( 'Where to Direct Mentions of the Home Page', 'webmention' ),
			'show_in_rest' => true,
			'default' => 0,
		) );
	}

	public static function get_default_comment_status( $status, $post_type, $comment_type ) {
		if ( 'webmention' === $comment_type ) {
			return post_type_supports( $post_type, 'webmentions' ) ? 'open' : 'closed' ;
		}
		// Since support for the pingback comment type is used to keep pings open...
		if ( ( 'pingback' === $comment_type ) ) {
			return ( post_type_supports( $post_type, 'webmentions' ) ? 'open' : $status );
		}

		return $status;
	}

	/**
	 * Register Webmention admin settings.
	 */
	public static function admin_init() {
		add_settings_field( 'webmention_discussion_settings', __( 'Webmention Settings', 'webmention' ), array( 'Webmention_Plugin', 'discussion_settings' ), 'discussion', 'default' );

		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Webmention_Plugin', 'add_meta_boxes' ) );

		add_filter( 'plugin_action_links', array( 'Webmention_Plugin', 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( 'Webmention_Plugin', 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add Webmention options to the WordPress discussion settings page.
	 */
	public static function discussion_settings() {
		load_template( plugin_dir_path( __FILE__ ) . 'templates/webmention-discussion-settings.php' );
	}

	/**
	 * render the comment form
	 */
	public static function comment_form() {
		$template = apply_filters( 'webmention_comment_form', plugin_dir_path( __FILE__ ) . 'templates/webmention-comment-form.php' );

		if ( 1 == get_option( 'webmention_show_comment_form' ) ) {
			load_template( $template );
		}
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
	 * Return true if page is enabled for Homepage Webmentions
	 *
	 * @param bool $open    Whether the current post is open for pings.
	 * @param int  $post_id The post ID.
	 *
	 * @return boolean if pings are open
	 */
	public static function pings_open( $open, $post_id ) {
		if ( get_option( 'webmention_home_mentions' ) == $post_id ) {
			return true;
		}

		return $open;
	}

	/*
	 * Create a  meta boxes to be displayed on the comment editor screen.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'webmention-meta',      // Unique ID
			esc_html__( 'Webmention Data', 'webmention' ),    // Title
			array( 'Webmention_Plugin', 'meta_boxes' ),   // Callback function
			'comment',
			'normal',         // Context
			'default'         // Priority
		);
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
}
