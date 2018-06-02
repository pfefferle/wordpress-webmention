		<h2 class="title"><?php _e( 'Receiver', 'webmention' ); ?></h2>

		<p><?php _e( 'A Webmention Receiver is an implementation that receives Webmentions to one or more target URLs on which the Receiver\'s Webmention endpoint is advertised.', 'webmention' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row"><?php _e( 'Webmention support for pages', 'webmention' ); ?></th>
				<td>
					<fieldset>
						<label for="webmention_support_pages">
							<input type="checkbox" name="webmention_support_pages" id="webmention_support_pages" value="1" <?php
								echo checked( true, get_option( 'webmention_support_pages' ) );  ?> />
							<?php _e( 'Enable Webmention support for pages', 'webmention' ) ?>
						</label>

						<br />

						<label for="webmention_home_mentions">
							<?php _e( 'Set a page for mentions of the homepage to be sent to:', 'webmention' ); ?>

							<?php
							wp_dropdown_pages( array(
								'show_option_none' => __( 'No homepage mentions', 'webmention' ),
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
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e( 'Automatically approve Webmention from these domains', 'webmention' ); ?></p></th>
				<td>
					<fieldset>
						<p>
							<textarea name="webmention_approve_domains" id="webmention_approve_domains" rows="10" cols="50" class="large-text code"><?php echo get_option( 'webmention_approve_domains' ); ?></textarea>
						</p>
						<p class="description">
							<label for="webmention_approve_domains">
								<?php _e( 'A Webmention received from a site that matches a domain in this list will be auto-approved. One domain (e.g. indieweb.org) per line.
', 'webmention' ); ?>
							</label>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e( 'Comment settings', 'webmention' ); ?></th>
				<td>
					<fieldset>
						<p>
							<label for="webmention_show_comment_form">
								<input type="checkbox" name="webmention_show_comment_form" id="webmention_show_comment_form" value="1" <?php
									echo checked( true, get_option( 'webmention_show_comment_form' ) );  ?> />
								<?php _e( 'Show a Webmention form at the comment section, to allow anyone to notify you of a mention.', 'webmention' ) ?>
							</label>
						</p>

						<p>
							<textarea name="webmention_comment_form_text" id="webmention_comment_form_text" rows="10" cols="50" class="large-text code" placeholder="<?php echo esc_html( get_default_webmention_form_text() ); ?>"><?php echo get_option( 'webmention_comment_form_text', '' ); ?></textarea>
						</p>
						<p class="description">
							<label for="webmention_comment_form_text">
								<?php _e( 'Change the default help text of the Webmention form', 'webmention' ); ?>
							</label>
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e( 'Avatars', 'webmention' ); ?></th>
				<td>
					<fieldset>
						<label for="webmention_avatars">
							<input type="checkbox" name="webmention_avatars" id="webmention_avatars" value="1" <?php
								echo checked( true, get_option( 'webmention_avatars' ) );  ?> />
							<?php _e( 'Show avatars on webmentions if available.', 'webmention' ) ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php do_settings_fields( 'webmention', 'receiver' ); ?>
