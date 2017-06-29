<fieldset id="webmention">
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

	<br />

	<label for="webmention_support_pages">
		<input type="checkbox" name="webmention_support_pages" id="webmention_support_pages" value="1" <?php
			echo checked( true, get_option( 'webmention_support_pages' ) );  ?> />
		<?php _e( 'Enable Webmention Support for Pages', 'webmention' ) ?>
	</label>

	<br />

	<label for="webmention_show_comment_form">
		<input type="checkbox" name="webmention_show_comment_form" id="webmention_show_comment_form" value="1" <?php
			echo checked( true, get_option( 'webmention_show_comment_form' ) );  ?> />
		<?php _e( 'Show a Webmention form at the comment section, to enable manual pings.', 'webmention' ) ?>
	</label>

	<br />

	<label for="webmention_home_mentions">
		<?php _e( 'Set a page for mentions of the homepage to be sent to:', 'webmention' ); ?>

		<?php
		wp_dropdown_pages( array(
			'show_option_none' => __( 'No Homepage Mentions', 'webmention' ),
			'name' => 'webmention_home_mentions',
			'id' => 'webmention_home_mentions',
			'selected' => get_option( 'webmention_home_mentions' ),
		) );
		?>

		<?php
		if ( get_option( 'webmention_home_mentions' ) ) {
			printf( '<a href="%s">%s</a>', get_permalink( get_option( 'webmention_home_mentions' ) ), __( 'Visit site', 'webmention' ) );
		}
		?>
	</label>
</fieldset>
