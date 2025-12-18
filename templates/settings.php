<div class="wrap">
	<h1><?php esc_html_e( 'Webmention Settings', 'webmention' ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'webmention' );
		do_settings_sections( 'webmention' );
		submit_button();
		?>
	</form>
</div>
