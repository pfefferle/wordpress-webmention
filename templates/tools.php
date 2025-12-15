<div class="wrap">
	<h1><?php esc_html_e( 'Webmention Testing Tools', 'webmention' ); ?></h1>

	<h2><?php esc_html_e( 'Webmention Parsing Debugger', 'webmention' ); ?></h2>
	<p><?php esc_html_e( 'This tests the parsing of an incoming webmention to show what information is available to produce a rich comment', 'webmention' ); ?></p>

	<form method="get" action="<?php echo esc_url( rest_url( '/webmention/1.0/parse/' ) ); ?> ">
		<?php if ( empty( get_option( 'permalink_structure' ) ) ) : ?>
			<input type="hidden"  name="rest_route" value="/webmention/1.0/parse/" />
		<?php endif; ?>
		<p><strong><label for="source"><?php esc_html_e( 'Source URL', 'webmention' ); ?></label></strong></p>
		<input type="url" class="regular-text" name="source" id="source" />

		<p><strong><label for="target"><?php esc_html_e( 'Target', 'webmention' ); ?></label></strong></p>
		<input type="url" class="regular-text" name="target" id="target" />

		<p><strong><label for="source"><?php esc_html_e( 'Output format', 'webmention' ); ?></label></strong></p>
		<label for="aggregated"><?php esc_html_e( 'Aggregated', 'webmention' ); ?></label>
		<input type="radio" id="aggregated" name="mode" value="aggregated" checked="checked">
		<label for="grouped">Grouped</label>
		<input type="radio" id="grouped" name="mode" value="grouped">

		<p><?php submit_button( __( 'Parse', 'webmention' ), 'small', 'submit', false ); ?></p>
		<?php wp_nonce_field( 'wp_rest' ); ?>
	</form>
</div>
