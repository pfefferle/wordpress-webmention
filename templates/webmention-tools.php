<div class="wrap">
	<h1><?php esc_html_e( 'Webmention Testing Tools', 'webmention' ); ?></h1>
	<hr class="wp-header-end" />
	<h2><?php esc_html_e( 'Webmention Parsing Debugger', 'webmention' ); ?></h2>
	<p><?php esc_html_e( 'This tests the parsing of an incoming webmention to show what information is available to produce a rich comment', 'webmention' ); ?></p>
	<form method="get" action="<?php echo esc_url( rest_url( '/webmention/1.0/parse/' ) ); ?> ">
		<p><strong><label for="source"><?php esc_html_e( 'Source URL', 'webmention' ); ?></label></strong></p>
		<input type="url" class="regular-text" name="source" id="source" />
		<p><strong><label for="target"><?php esc_html_e( 'Target', 'webmention' ); ?></label></strong></p>
		<input type="url" class="regular-text" name="target" id="target" />
		<p><?php submit_button( __( 'Parse', 'webmention' ), 'small', 'submit', false ); ?></p>
		<?php wp_nonce_field( 'wp_rest' ); ?>
	</form>
</div>
