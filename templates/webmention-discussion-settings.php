<fieldset>
	<label for="webmention_disable_selfpings_same_url">
		<input type="checkbox" name="webmention_disable_selfpings_same_url" id="webmention_disable_selfpings_same_url" value="1" <?php
			echo checked( true, get_option( 'webmention_disable_selfpings_same_url' ) );  ?> />
		<?php _e( 'Disable self-pings on the same URL <small>(for example "http://example.com/?p=123")</small>', 'webmention' ) ?>
	</label>

	<br />

	<label for="webmention_disable_selfpings_same_domain">
		<input type="checkbox" name="webmention_disable_selfpings_same_domain" id="webmention_disable_selfpings_same_domain" value="1" <?php
			echo checked( true, get_option( 'webmention_disable_selfpings_same_domain' ) );  ?> />
		<?php _e( 'Disable self-pings on the same Domain <small>(for example "example.com")</small>', 'webmention' ) ?>
	</label>
</fieldset>
