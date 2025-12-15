<?php
/**
 * Settings Fields Class
 *
 * Registers and renders settings fields using the WordPress Settings API.
 *
 * @package Webmention
 */

namespace Webmention\WP_Admin;

/**
 * Settings Fields Class
 *
 * @package Webmention
 */
class Settings_Fields {

	/**
	 * Initialize the settings fields.
	 */
	public static function init() {
		// Hook into settings page load for both possible menu locations.
		\add_action( 'load-settings_page_webmention', array( self::class, 'register_settings_fields' ) );
		\add_action( 'load-indieweb_page_webmention', array( self::class, 'register_settings_fields' ) );
	}

	/**
	 * Register settings sections and fields.
	 */
	public static function register_settings_fields() {
		// Sender section.
		\add_settings_section(
			'sender',
			\esc_html__( 'Sender', 'webmention' ),
			array( self::class, 'render_sender_section' ),
			'webmention'
		);

		\add_settings_field(
			'webmention_selfpings',
			\esc_html__( 'Self-Ping settings', 'webmention' ),
			array( self::class, 'render_selfpings_field' ),
			'webmention',
			'sender'
		);

		// Receiver section.
		\add_settings_section(
			'receiver',
			\esc_html__( 'Receiver', 'webmention' ),
			array( self::class, 'render_receiver_section' ),
			'webmention'
		);

		\add_settings_field(
			'webmention_support_post_types',
			\esc_html__( 'Webmention support for post types', 'webmention' ),
			array( self::class, 'render_post_types_field' ),
			'webmention',
			'receiver'
		);

		\add_settings_field(
			'webmention_approve_domains',
			\esc_html__( 'Automatically approve Webmention from these domains', 'webmention' ),
			array( self::class, 'render_approve_domains_field' ),
			'webmention',
			'receiver'
		);

		\add_settings_field(
			'webmention_comment_settings',
			\esc_html__( 'Comment settings', 'webmention' ),
			array( self::class, 'render_comment_settings_field' ),
			'webmention',
			'receiver'
		);

		\add_settings_field(
			'webmention_avatars',
			\esc_html__( 'Use Avatars', 'webmention' ),
			array( self::class, 'render_avatars_field' ),
			'webmention',
			'receiver'
		);

		\add_settings_field(
			'webmention_avatar_store_enable',
			\esc_html__( 'Store Avatars', 'webmention' ),
			array( self::class, 'render_avatar_store_field' ),
			'webmention',
			'receiver'
		);

		\add_settings_field(
			'webmention_display_settings',
			\esc_html__( 'Display', 'webmention' ),
			array( self::class, 'render_display_settings_field' ),
			'webmention',
			'receiver'
		);
	}

	/**
	 * Render sender section description.
	 */
	public static function render_sender_section() {
		echo '<p>' . \esc_html__( 'A Webmention Sender is an implementation that sends Webmentions.', 'webmention' ) . '</p>';
	}

	/**
	 * Render receiver section description.
	 */
	public static function render_receiver_section() {
		echo '<p>' . \esc_html__( 'A Webmention Receiver is an implementation that receives Webmentions to one or more target URLs on which the Receiver\'s Webmention endpoint is advertised.', 'webmention' ) . '</p>';
	}

	/**
	 * Render self-ping settings field.
	 */
	public static function render_selfpings_field() {
		?>
		<fieldset>
			<label for="webmention_disable_selfpings_same_url">
				<input type="checkbox" name="webmention_disable_selfpings_same_url" id="webmention_disable_selfpings_same_url" value="1" <?php \checked( true, \get_option( 'webmention_disable_selfpings_same_url' ) ); ?> />
				<?php \esc_html_e( 'Disable self-pings on the same URL', 'webmention' ); ?>
				<p class="description"><?php \esc_html_e( '(for example "http://example.com/?p=123")', 'webmention' ); ?></p>
			</label>

			<br />

			<label for="webmention_disable_selfpings_same_domain">
				<input type="checkbox" name="webmention_disable_selfpings_same_domain" id="webmention_disable_selfpings_same_domain" value="1" <?php \checked( true, \get_option( 'webmention_disable_selfpings_same_domain' ) ); ?> />
				<?php \esc_html_e( 'Disable self-pings on the same Domain', 'webmention' ); ?>
				<p class="description"><?php \esc_html_e( '(for example "example.com")', 'webmention' ); ?></p>
			</label>

			<br />

			<label for="webmention_disable_media_mentions">
				<input type="checkbox" name="webmention_disable_media_mentions" id="webmention_disable_media_mentions" value="1" <?php \checked( true, \get_option( 'webmention_disable_media_mentions' ) ); ?> />
				<?php \esc_html_e( 'Disable sending Webmentions for media links (image, video, audio)', 'webmention' ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render post types support field.
	 */
	public static function render_post_types_field() {
		$post_types         = \get_post_types( array( 'public' => true ), 'objects' );
		$support_post_types = \get_option( 'webmention_support_post_types', array( 'post', 'page' ) );
		$support_post_types = $support_post_types ? $support_post_types : array();
		?>
		<fieldset>
			<?php \esc_html_e( 'Enable Webmention support for the following post types:', 'webmention' ); ?>

			<ul>
			<?php foreach ( $post_types as $post_type ) : ?>
				<li>
					<label for="webmention_support_post_types_<?php echo \esc_attr( $post_type->name ); ?>">
						<input type="checkbox" id="webmention_support_post_types_<?php echo \esc_attr( $post_type->name ); ?>" name="webmention_support_post_types[]" value="<?php echo \esc_attr( $post_type->name ); ?>" <?php \checked( true, \in_array( $post_type->name, $support_post_types, true ) ); ?> />
						<?php echo \esc_html( $post_type->label ); ?>
					</label>
				</li>
			<?php endforeach; ?>
			</ul>

			<br />

			<label for="webmention_home_mentions">
				<?php \esc_html_e( 'Set a page for mentions of the homepage to be sent to:', 'webmention' ); ?>

				<?php
				\wp_dropdown_pages(
					array(
						'show_option_none' => \esc_html__( 'No homepage mentions', 'webmention' ),
						'name'             => 'webmention_home_mentions',
						'id'               => 'webmention_home_mentions',
						'selected'         => \get_option( 'webmention_home_mentions' ),
					)
				);
				?>

				<?php
				if ( \get_option( 'webmention_home_mentions' ) ) {
					\printf( '<a href="%s">%s</a>', \esc_url( \get_permalink( \get_option( 'webmention_home_mentions' ) ) ), \esc_html__( 'Visit site', 'webmention' ) );
				}
				?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render approve domains field.
	 */
	public static function render_approve_domains_field() {
		?>
		<fieldset>
			<p>
				<textarea name="webmention_approve_domains" id="webmention_approve_domains" rows="10" cols="50" class="large-text code"><?php echo \esc_textarea( \get_option( 'webmention_approve_domains' ) ); ?></textarea>
			</p>
			<p class="description">
				<label for="webmention_approve_domains">
					<?php \esc_html_e( 'A Webmention received from a site that matches a domain in this list will be auto-approved. One domain (e.g. indieweb.org) per line.', 'webmention' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render comment settings field.
	 */
	public static function render_comment_settings_field() {
		?>
		<fieldset>
			<p>
				<label for="webmention_show_comment_form">
					<input type="checkbox" name="webmention_show_comment_form" id="webmention_show_comment_form" value="1" <?php \checked( true, \get_option( 'webmention_show_comment_form' ) ); ?> />
					<?php \esc_html_e( 'Show a Webmention form at the comment section, to allow anyone to notify you of a mention.', 'webmention' ); ?>
				</label>
			</p>

			<p>
				<textarea name="webmention_comment_form_text" id="webmention_comment_form_text" rows="10" cols="50" class="large-text code" placeholder="<?php echo \esc_attr( get_default_webmention_form_text() ); ?>"><?php echo \esc_textarea( \get_option( 'webmention_comment_form_text', '' ) ); ?></textarea>
			</p>
			<p class="description">
				<label for="webmention_comment_form_text">
					<?php \esc_html_e( 'Change the default help text of the Webmention form', 'webmention' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render avatars field.
	 */
	public static function render_avatars_field() {
		?>
		<fieldset>
			<label for="webmention_avatars">
				<input type="checkbox" name="webmention_avatars" id="webmention_avatars" value="1" <?php \checked( true, \get_option( 'webmention_avatars', 1 ) ); ?> />
				<?php \esc_html_e( 'Show avatars on Webmentions if available.', 'webmention' ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render avatar store field.
	 */
	public static function render_avatar_store_field() {
		?>
		<fieldset>
			<label for="webmention_avatar_store_enable">
				<input type="checkbox" name="webmention_avatar_store_enable" id="webmention_avatar_store_enable" value="1" <?php \checked( true, \get_option( 'webmention_avatar_store_enable', 0 ) ); ?> />
				<?php \esc_html_e( 'Enable Local Caching of Avatars.', 'webmention' ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render display settings field.
	 */
	public static function render_display_settings_field() {
		?>
		<fieldset>
			<label for="webmention_separate_comment">
				<input type="checkbox" name="webmention_separate_comment" id="webmention_separate_comment" value="1" <?php \checked( true, \get_option( 'webmention_separate_comment', 1 ) ); ?> />
				<?php \esc_html_e( 'Separate Webmention Types from Comments.', 'webmention' ); ?>
			</label>
		</fieldset>

		<fieldset>
			<label for="webmention_show_facepile">
				<input type="checkbox" name="webmention_show_facepile" id="webmention_show_facepile" value="1" <?php \checked( true, \get_option( 'webmention_show_facepile', 1 ) ); ?> />
				<?php \esc_html_e( 'Automatically add Facepile to the comments section.', 'webmention' ); ?>
			</label>
		</fieldset>

		<fieldset>
			<label for="webmention_facepile_fold_limit">
				<input type="number" min="0" class="small-text" name="webmention_facepile_fold_limit" id="webmention_facepile_fold_limit" value="<?php echo \esc_attr( \get_option( 'webmention_facepile_fold_limit' ) ); ?>" />
				<?php \esc_html_e( 'Initial number of faces to show in facepiles (0 for all).', 'webmention' ); ?>
			</label>
		</fieldset>
		<?php
	}
}
