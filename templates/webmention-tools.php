<div class="wrap">
	<h1><?php esc_html_e( 'Webmention Testing Tools', 'webmention' ); ?></h1>

		<h2> <?php esc_html_e( 'Webmention Parsing Debugger', 'webmention' ); ?> </h2>
			<form method="get" action="<?php echo esc_url( rest_url( '/webmention/1.0/parse/' ) ); ?> ">
					<p><label for="url"><?php esc_html_e( 'URL', 'webmention' ); ?></label><input type="url" class="widefat" name="url" id="url" /></p>
					<?php wp_nonce_field( 'wp_rest' ); ?>
					<?php submit_button( __( 'Parse', 'webmention' ) ); ?>
			</form>
</div>
