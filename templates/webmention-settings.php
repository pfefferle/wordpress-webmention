<?php

	// Get the active tab from the $_GET param
	$default_tab = null;
	$tab = $_GET['tab'] ?? $default_tab;

?>



<div class="webmention-header">
	<div class="webmention-title-section">
		<h1><?php esc_html_e( 'Webmention Settings', 'webmention' ); ?></h1>
	</div>

	<!-- tabs -->
	<nav class="webmention-tabs-wrapper">
		<a href="?page=webmention" class="webmention-tab <?php if ( $tab === null ) :?>active" aria-current="true<?php endif; ?>">Setup Wizard</a>
		<a href="?page=webmention&tab=settings" class="webmention-tab <?php if ( $tab === 'settings' ): ?>active" aria-current="true<?php endif; ?>">Advanced Settings</a>
		<a href="?page=webmention&tab=more" class="webmention-tab <?php if ( $tab === 'more' ): ?>active" aria-current="true<?php endif; ?>">More Information</a>
	</nav>
	<div class="wp-clearfix"></div>
</div>
<hr class="wp-header-end">

<div class="webmention-body">
	<?php if ( $tab === null) :
		// Tab Content: Setup Wizard
	?>

		<h2><?php _e( 'Setup Wizard, welcome!', 'webmention' ); ?></h2>
		<p><?php _e( 'The webmention plugin enables you to receive and send webmentions from your WordPress site.', 'webmention' ); ?></p>
		<p><?php _e( 'We designed this simplified installation process, called the Setup Wizard, to help you get started. The 5 steps of the Setup Wizard cover your essential needs.', 'webmention' ); ?></p>
		<p><?php echo sprintf( __( 'However if you want to experiment with Webmention, the <a href="%s">Advanced Settings</a> tab is what you are looking for.', 'webmention' ), esc_url( '?page=webmention&tab=settings' ) ); ?></p>
		<p>&nbsp;</p>

		<p><small><?php _e( 'Note: If you want to know more about the Indieweb movement and/or the webmention protocol and implementation, you’ll find more information at the end of the setup wizard.', 'webmention' ); ?></small></p>


		<form method="post" action="options.php">
			<?php settings_fields( 'webmention' ); ?>

			<section class="webmention-card">
				<h2><?php _e( 'Configure post types', 'webmention' ); ?></h2>

				<fieldset>
					<?php esc_html_e( 'Enable Webmention support for the following post types:', 'webmention' ); ?>

					<?php
						$post_types = get_post_types( array( 'public' => true ), 'objects' );$support_post_types = get_option( 'webmention_support_post_types', array( 'post', 'page' ) ) ?: array();
					?>
					<ul>
					<?php foreach ( $post_types as $post_type ) : ?>
						<li>
							<label for="webmention_support_post_types_<?php echo esc_attr( $post_type->name ); ?>">
								<input type="checkbox" class="ui-toggle" id="webmention_support_post_types_<?php echo esc_attr( $post_type->name ); ?>" name="webmention_support_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php echo checked( true, in_array( $post_type->name, $support_post_types, true ) ); ?> />
								<?php echo esc_html( $post_type->label ); ?>
							</label>
						</li>
					<?php endforeach; ?>
					</ul>
				</fieldset>

				<p><?php _e( 'Note: You can also enable or disable webmentions per post', 'webmention' ); ?></p>
				<?php submit_button(); ?>
			</section>


			<section class="webmention-card">
				<h2><?php _e( 'Configure reactions', 'webmention' ); ?></h2>
				<p style="color:red">--- <?php echo capital_P_dangit('To be implemented like in the Wordpress semantic backlinks plugin'); ?> ---</p>
			</section>

			<section class="webmention-card">
				<h2><?php _e( 'Configure comment field', 'webmention' ); ?></h2>
				<fieldset>
					<p>
						<label for="webmention_show_comment_form">
							<input type="checkbox" class="ui-toggle js-show-hide-target" name="webmention_show_comment_form" id="webmention_show_comment_form" value="1" <?php echo checked( true, get_option( 'webmention_show_comment_form' ) ); ?> data-target="webmention_comment_form_text_wrapper">
							<?php esc_html_e( 'Show a Webmention form at the comment section, to allow anyone to notify you of a mention.', 'webmention' ); ?>
						</label>
					</p>

					<div id="webmention_comment_form_text_wrapper" <?php echo get_option( 'webmention_show_comment_form' ) == false ? 'style="display: none"' : ''?>>
						<p class="description">
							<label class="description" for="webmention_comment_form_text">
								<?php esc_html_e( 'Change the default help text of the Webmention form', 'webmention' ); ?>:
							</label>
						</p>
						<p>
							<textarea name="webmention_comment_form_text" id="webmention_comment_form_text" rows="5" cols="50" class="large-text code"><?php echo get_option( 'webmention_comment_form_text', esc_html( get_default_webmention_form_text() ) ); ?></textarea>
						</p>
					</div>
				</fieldset>
				<?php submit_button(); ?>
			</section>

			<section class="webmention-card">
				<h2><?php _e( 'Configure avatars', 'webmention' ); ?></h2>
				<fieldset>
					<label for="webmention_avatars">
						<input type="checkbox" class="ui-toggle" name="webmention_avatars" id="webmention_avatars" value="1" <?php echo checked( true, get_option( 'webmention_avatars', 1 ) ); ?> />
						<?php esc_html_e( 'Show avatars on webmentions if available.', 'webmention' ); ?>
					</label>
				</fieldset>
				<?php submit_button(); ?>
			</section>

			<section class="webmention-card">
				<h2><?php _e( 'Enable self pings', 'webmention' ); ?></h2>
				<fieldset>
					<label for="webmention_disable_selfpings_same_domain">
						<input type="checkbox" class="ui-toggle" name="webmention_disable_selfpings_same_domain" id="webmention_disable_selfpings_same_domain" value="1" <?php echo checked( false, get_option( 'webmention_disable_selfpings_same_domain', false ) ); ?> />
						<?php esc_html_e( 'Enable self-pings on the same Domain', 'webmention' ); ?>
						<p class="description"><?php esc_html_e( 'We recommend enabling self-pings.', 'webmention' ); ?></p>
					</label>
				</fieldset>
				<?php submit_button(); ?>
			</section>

		</form>



	<?php elseif ( $tab === 'settings' ) :
		// Tab Content: Advanced Settings
	?>

		<form method="post" action="options.php">
			<?php settings_fields( 'webmention' ); ?>

			<section class="webmention-card not-numbered">
				<h2><?php _e( 'Moderation', 'webmention' ); ?></h2>

				<p><?php _e( 'Automatically approve Webmentions from these domains:', 'webmention' ); ?></p>
				<fieldset>
					<textarea name="webmention_approve_domains" id="webmention_approve_domains" rows="5" cols="50" class="large-text code"><?php echo get_option( 'webmention_approve_domains' ); ?></textarea>
					<p class="description">
						<label for="webmention_approve_domains">
							<?php esc_html_e( 'A Webmention received from a site that matches a domain in this list will be auto-approved. One domain (e.g. indieweb.org) per line.', 'webmention' ); ?>
						</label>
					</p>
				</fieldset>

				<?php submit_button(); ?>
			</section>

			<section class="webmention-card not-numbered">
				<h2><?php _e( 'Ping options', 'webmention' ); ?></h2>

				<h3><?php _e( 'Self-Pings', 'webmention' ); ?></h3>
				<fieldset>
					<label for="webmention_disable_selfpings_same_url">
						<input type="checkbox" class="ui-toggle" name="webmention_disable_selfpings_same_url" id="webmention_disable_selfpings_same_url" value="1" <?php echo checked( false, get_option( 'webmention_disable_selfpings_same_url', false ) ); ?> >

						<?php _e( 'Enable self-pings on the same URL', 'webmention' ); ?>
						<p class="description"><?php esc_html_e( '(for example "http://example.com/?p=123")', 'webmention' ); ?></p>
					</label>

					<label for="webmention_disable_media_mentions">
						<input type="checkbox" class="ui-toggle" name="webmention_disable_media_mentions" id="webmention_disable_media_mentions" value="1" <?php echo checked( false, get_option( 'webmention_disable_media_mentions', false ) ); ?> />
						<?php esc_html_e( 'Enable sending webmentions for media links (image, video, audio)', 'webmention' ); ?>
					</label>
				</fieldset>

				<h3><?php _e( 'Pings towards your home URL', 'webmention' ); ?></h3>

				<fieldset>
					<label for="webmention_home_mentions">
						<?php esc_html_e( 'Set a page for mentions of the homepage to be sent to:', 'webmention' ); ?>

						<?php
						wp_dropdown_pages(
							array(
								'show_option_none' => esc_html__( 'No homepage mentions', 'webmention' ),
								'name'             => 'webmention_home_mentions',
								'id'               => 'webmention_home_mentions',
								'selected'         => get_option( 'webmention_home_mentions' ),
							)
						);
						?>

						<?php
						if ( get_option( 'webmention_home_mentions' ) ) {
							printf( '<a href="%s">%s</a>', get_permalink( get_option( 'webmention_home_mentions' ) ), esc_html__( 'Visit site', 'webmention' ) );
						}
						?>
					</label>
				</fieldset>

				<?php submit_button(); ?>
			</section>

			<?php do_settings_fields( 'webmention', 'receiver' ); ?>

			<?php do_settings_sections( 'webmention' ); ?>
		</form>

	<?php endif; ?>
</div>
