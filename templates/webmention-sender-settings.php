		<h2 class="title"><?php _e( 'Sender', 'webmention' ); ?></h2>

		<?php if ( ! class_exists( 'Semantic_Linkbacks_Plugin' ) ) : ?>
		<div class="notice notice-warning">
			<p><?php printf (
				__( 'The Webmention plugin primarily handles sending/receiving notifications of mentions from other websites, so the format of the comments can look odd on one\'s site. We highly recommend also installing and activating the <a class="thickbox open-plugin-details-modal" href="%1$s" target_"blank">Semantic Linkbacks Plugin</a> which has better parsing and display capabilities to allow richer looking comments as well as options for displaying many reply types as facepiles for improved user interface.', 'webmention' ),
				admin_url( '/plugin-install.php?tab=plugin-information&plugin=semantic-linkbacks&TB_iframe=true' )
			); ?></p>
		</div>
		<?php endif; ?>

		<p><?php _e( 'A Webmention Sender is an implementation that sends Webmentions.', 'webmention' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row"><?php _e( 'Self-Ping settings', 'webmention' ); ?></th>
				<td>
					<fieldset>
						<label for="webmention_disable_selfpings_same_url">
							<input type="checkbox" name="webmention_disable_selfpings_same_url" id="webmention_disable_selfpings_same_url" value="1" <?php
								echo checked( true, get_option( 'webmention_disable_selfpings_same_url' ) );  ?> />
							<?php _e( 'Disable self-pings on the same URL', 'webmention' ) ?>
							<p class="description">(for example "http://example.com/?p=123")</p>
						</label>

						<br />

						<label for="webmention_disable_selfpings_same_domain">
							<input type="checkbox" name="webmention_disable_selfpings_same_domain" id="webmention_disable_selfpings_same_domain" value="1" <?php
								echo checked( true, get_option( 'webmention_disable_selfpings_same_domain' ) );  ?> />
							<?php _e( 'Disable self-pings on the same Domain', 'webmention' ) ?>
							<p class="description">(for example "example.com")</p>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php do_settings_fields( 'webmention', 'sender' ); ?>
