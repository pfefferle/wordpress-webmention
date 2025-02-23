<?php
/**
 * Webmention Class
 *
 * @package Webmention
 */

namespace Webmention;

/**
 * Webmention Class
 *
 * @package Webmention
 */
class Webmention {
	/**
	 * Instance of the class.
	 *
	 * @var Webmention
	 */
	private static $instance;

	/**
	 * Text domain.
	 *
	 * @var string
	 */
	const TEXT_DOMAIN = 'webmention';

	/**
	 * Get the instance of the class.
	 *
	 * @return Webmention
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Do not allow multiple instances of the class.
	 */
	private function __construct() {
		// Do nothing.
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		$this->register_constants();

		$this->register_hooks();
		$this->register_admin_hooks();
		$this->add_post_type_support();

		// Load language files.
		load_plugin_textdomain( self::TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return WEBMENTION_VERSION;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		\add_action( 'init', array( Tools::class, 'init' ) );
		\add_action( 'init', array( Comment::class, 'init' ) );
		\add_action( 'init', array( Comment_Walker::class, 'init' ) );

		// load local avatar support.
		\add_action( 'init', array( Avatar::class, 'init' ) );

		// load HTTP 410 support.
		\add_action( 'init', array( HTTP_Gone::class, 'init' ) );

		// initialize Webmention Sender.
		\add_action( 'init', array( Sender::class, 'init' ) );

		// initialize Webmention Receiver.
		\add_action( 'init', array( Receiver::class, 'init' ) );

		// initialize Webmention Discovery.
		\add_action( 'init', array( Discovery::class, 'init' ) );

		if ( site_supports_blocks() ) {
			// initialize Webmention Bloks.
			\add_action( 'init', array( Block::class, 'init' ) );
		}

		// load local avatar store.
		if ( 1 === (int) get_option( 'webmention_avatar_store_enable', 0 ) ) {
			\add_action( 'init', array( Avatar_Store::class, 'init' ) );
		}

		// initialize Webmention Vouch
		if ( WEBMENTION_VOUCH ) {
			\add_action( 'init', array( Vouch::class, 'init' ) );
		}

		\add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// remove the "webmentions_disabled" meta value if the post is updated
		\add_action( 'updated_postmeta', array( $this, 'updated_postmeta' ), 10, 4 );
	}

	/**
	 * Register admin hooks.
	 */
	public function register_admin_hooks() {
		add_action( 'admin_init', array( Admin::class, 'admin_init' ) );
		add_action( 'admin_menu', array( Admin::class, 'admin_menu' ) );
	}

	/**
	 * Add support for Webmentions to custom post types.
	 */
	public function add_post_type_support() {
		// Add support for Webmentions to custom post types.
		$post_types = get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) ? get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) : array();

		foreach ( $post_types as $post_type ) {
			add_post_type_support( $post_type, 'webmentions' );
		}
	}

	/**
	 * Register constants.
	 */
	private function register_constants() {
		\defined( 'WEBMENTION_ALWAYS_SHOW_HEADERS' ) || \define( 'WEBMENTION_ALWAYS_SHOW_HEADERS', 0 );
		\defined( 'WEBMENTION_COMMENT_APPROVE' ) || \define( 'WEBMENTION_COMMENT_APPROVE', 0 );
		\defined( 'WEBMENTION_COMMENT_TYPE' ) || \define( 'WEBMENTION_COMMENT_TYPE', 'webmention' );
		\defined( 'WEBMENTION_GRAVATAR_CACHE_TIME' ) || \define( 'WEBMENTION_GRAVATAR_CACHE_TIME', WEEK_IN_SECONDS );

		\defined( 'WEBMENTION_AVATAR_QUALITY' ) || \define( 'WEBMENTION_AVATAR_QUALITY', null );
		\defined( 'WEBMENTION_AVATAR_SIZE' ) || \define( 'WEBMENTION_AVATAR_SIZE', 256 );

		\define( 'WEBMENTION_PROCESS_TYPE_ASYNC', 'async' );
		\define( 'WEBMENTION_PROCESS_TYPE_SYNC', 'sync' );

		\defined( 'WEBMENTION_PROCESS_TYPE' ) || \define( 'WEBMENTION_PROCESS_TYPE', WEBMENTION_PROCESS_TYPE_SYNC );

		\defined( 'WEBMENTION_VOUCH' ) || \define( 'WEBMENTION_VOUCH', false );

		// Mentions with content less than this length will be rendered in full.
		\defined( 'MAX_INLINE_MENTION_LENGTH' ) || \define( 'MAX_INLINE_MENTION_LENGTH', 300 );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		if ( is_singular() ) {
			wp_enqueue_style( self::TEXT_DOMAIN, WEBMENTION_PLUGIN_URL . 'assets/css/webmention.css', array(), $this->get_version() );
		}
	}

	/**
	 * Delete the webmentions_disabled meta value if the post is updated.
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $object_id  The object ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 */
	public function updated_postmeta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'webmentions_disabled' === $meta_key && empty( $meta_value ) ) {
			\delete_post_meta( $object_id, 'webmentions_disabled' );
		}
	}
}
