<div class="wrap">
	<h1><?php esc_html_e( 'Webmention Testing Tools', 'webmention' ); ?></h1>
	<hr class="wp-header-end" />
	<h2><?php esc_html_e( 'Webmention Parsing Debugger', 'webmention' ); ?></h2>
	<p><?php _e( 'Some tools to debug source URLs and different parsers.', 'webmention' ); ?></p>
	<form method="get" action="<?php echo esc_url( rest_url( '/webmention/1.0/parse/' ) ); ?> ">
		<p><strong><label for="url"><?php esc_html_e( 'Parse URL', 'webmention' ); ?></label></strong></p>
		<input type="url" class="regular-text" name="url" id="url" />
		<?php submit_button( __( 'Parse', 'webmention' ), 'small', 'submit', false ); ?>
		<?php wp_nonce_field( 'wp_rest' ); ?>
	</form>
</div>
