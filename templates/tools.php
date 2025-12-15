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

	<?php if ( 1 === (int) get_option( 'webmention_avatar_store_enable', 0 ) ) : ?>
		<h2><?php esc_html_e( 'Avatar Store Management', 'webmention' ); ?></h2>
		<p><?php esc_html_e( 'Manage avatars stored by author URL. Replace allows you to manually set an avatar URL, Refresh retrieves the avatar from the author URL. Avatars are automatically removed when all comments using them are deleted.', 'webmention' ); ?></p>

		<p>
			<button type="button" class="button" id="webmention-store-existing-avatars"><?php esc_html_e( 'Store Avatars for Existing Comments', 'webmention' ); ?></button>
			<button type="button" class="button" id="webmention-cleanup-avatars"><?php esc_html_e( 'Clean Up Orphaned Avatars', 'webmention' ); ?></button>
			<span id="webmention-status"></span>
		</p>

		<div id="webmention-avatar-store">
			<p class="description"><?php esc_html_e( 'Loading avatars...', 'webmention' ); ?></p>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var apiUrl = '<?php echo esc_url( rest_url( 'webmention/1.0/avatars' ) ); ?>';
			var nonce = '<?php echo wp_create_nonce( 'wp_rest' ); ?>';

			// Cleanup orphaned avatars
			$('#webmention-cleanup-avatars').on('click', function() {
				var $button = $(this);
				var $status = $('#webmention-status');
				
				if (!confirm('<?php esc_html_e( 'Clean up orphaned avatar files (not used by any comments)?', 'webmention' ); ?>')) {
					return;
				}

				$button.prop('disabled', true);
				$status.html('<?php esc_html_e( 'Cleaning up...', 'webmention' ); ?>');

				$.ajax({
					url: '<?php echo esc_url( rest_url( 'webmention/1.0/avatar/cleanup' ) ); ?>',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					},
					success: function(response) {
						var message = response.message || '<?php esc_html_e( 'Cleanup completed.', 'webmention' ); ?>';
						$status.html('<span style="color: green;">' + message + '</span>');
						setTimeout(function() {
							$status.html('');
							loadAvatars();
						}, 2000);
					},
					error: function(xhr) {
						var errorMsg = '<?php esc_html_e( 'Failed to cleanup avatars.', 'webmention' ); ?>';
						if (xhr.responseJSON && xhr.responseJSON.message) {
							errorMsg = xhr.responseJSON.message;
						}
						$status.html('<span style="color: red;">' + errorMsg + '</span>');
					},
					complete: function() {
						$button.prop('disabled', false);
					}
				});
			});

			// Store avatars for existing comments
			$('#webmention-store-existing-avatars').on('click', function() {
				var $button = $(this);
				var $status = $('#webmention-status');
				
				if (!confirm('<?php esc_html_e( 'This will process existing webmention comments and store their avatars. Continue?', 'webmention' ); ?>')) {
					return;
				}
				
				$button.prop('disabled', true);
				$status.html('<?php esc_html_e( 'Processing existing comments...', 'webmention' ); ?>');

				$.ajax({
					url: '<?php echo esc_url( rest_url( 'webmention/1.0/avatar/store-existing' ) ); ?>',
					method: 'POST',
					beforeSend: function(xhr) {
						xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					},
					success: function(response) {
						var message = response.message || '<?php esc_html_e( 'Avatars stored successfully!', 'webmention' ); ?>';
						var color = 'green';
						
						if (response.errors && response.errors.length > 0) {
							message += '<br><small>' + response.errors.slice(0, 5).join('<br>') + (response.errors.length > 5 ? '<br>...' : '') + '</small>';
							color = 'orange';
						}
						
						$status.html('<span style="color: ' + color + ';">' + message + '</span>');
						setTimeout(function() {
							$status.html('');
							loadAvatars();
						}, 3000);
					},
					error: function(xhr) {
						var errorMsg = '<?php esc_html_e( 'Failed to store avatars.', 'webmention' ); ?>';
						if (xhr.responseJSON && xhr.responseJSON.message) {
							errorMsg = xhr.responseJSON.message;
						} else if (xhr.responseJSON && xhr.responseJSON.code) {
							errorMsg = xhr.responseJSON.code + ': ' + (xhr.responseJSON.message || errorMsg);
						}
						$status.html('<span style="color: red;">' + errorMsg + '</span>');
					},
					complete: function() {
						$button.prop('disabled', false);
					}
				});
			});

			// Load avatars
			function loadAvatars() {
				$.ajax({
					url: apiUrl,
					method: 'GET',
					beforeSend: function(xhr) {
						xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					},
					success: function(response) {
						if (response && Array.isArray(response)) {
							displayAvatars(response);
						} else {
							$('#webmention-avatar-store').html('<p class="error"><?php esc_html_e( 'Invalid response format.', 'webmention' ); ?></p>');
						}
					},
					error: function(xhr) {
						var errorMsg = '<?php esc_html_e( 'Failed to load avatars.', 'webmention' ); ?>';
						if (xhr.responseJSON && xhr.responseJSON.message) {
							errorMsg += ' ' + xhr.responseJSON.message;
						}
						$('#webmention-avatar-store').html('<p class="error">' + errorMsg + '</p>');
					}
				});
			}

			function displayAvatars(avatars) {
				if (avatars.length === 0) {
					$('#webmention-avatar-store').html('<p><?php esc_html_e( 'No stored avatars found.', 'webmention' ); ?></p>');
					return;
				}

				var html = '<table class="wp-list-table widefat fixed striped">';
				html += '<thead><tr>';
				html += '<th><?php esc_html_e( 'Avatar', 'webmention' ); ?></th>';
				html += '<th><?php esc_html_e( 'Author URL', 'webmention' ); ?></th>';
				html += '<th><?php esc_html_e( 'Host', 'webmention' ); ?></th>';
				html += '<th><?php esc_html_e( 'Actions', 'webmention' ); ?></th>';
				html += '</tr></thead><tbody>';

				avatars.forEach(function(avatar) {
					html += '<tr data-author-url="' + escapeHtml(avatar.author_url || '') + '" data-host="' + escapeHtml(avatar.host) + '">';
					html += '<td><img src="' + escapeHtml(avatar.avatar_url) + '" style="width: 48px; height: 48px; object-fit: cover;" /></td>';
					html += '<td>' + escapeHtml(avatar.author_url || '<?php esc_html_e( 'Unknown', 'webmention' ); ?>') + '</td>';
					html += '<td>' + escapeHtml(avatar.host) + '</td>';
					html += '<td>';
					html += '<button type="button" class="button button-small webmention-refresh-avatar"><?php esc_html_e( 'Refresh', 'webmention' ); ?></button> ';
					html += '<button type="button" class="button button-small webmention-replace-avatar"><?php esc_html_e( 'Replace', 'webmention' ); ?></button>';
					html += '</td>';
					html += '</tr>';
				});

				html += '</tbody></table>';
				$('#webmention-avatar-store').html(html);

				// Re-bind event handlers for dynamically added buttons
				bindAvatarHandlers();
			}

			// Bind event handlers for avatar actions
			function bindAvatarHandlers() {
				// Handle refresh
				$('.webmention-refresh-avatar').off('click').on('click', function() {
					var $row = $(this).closest('tr');
					var authorUrl = $row.data('author-url');
					var host = $row.data('host');
					var $avatarImg = $row.find('img');

					if (!authorUrl) {
						showDialog('<?php esc_html_e( 'Error', 'webmention' ); ?>', '<?php esc_html_e( 'Author URL not available for this avatar. Cannot refresh without knowing the author URL.', 'webmention' ); ?>', 'error');
						return;
					}

					if (!confirm('<?php esc_html_e( 'Refresh avatar from author URL? This will fetch the author page and update the avatar.', 'webmention' ); ?>')) {
						return;
					}

					var $button = $(this);
					$button.prop('disabled', true).text('<?php esc_html_e( 'Refreshing...', 'webmention' ); ?>');

					var refreshUrl = '<?php echo esc_url( rest_url( 'webmention/1.0/avatar/refresh' ) ); ?>';
					
					$.ajax({
						url: refreshUrl,
						method: 'POST',
						beforeSend: function(xhr) {
							xhr.setRequestHeader( 'X-WP-Nonce', nonce );
						},
						data: {
							author_url: authorUrl,
							host: host
						},
						success: function(response) {
							// Update avatar image immediately
							if (response.avatar_url) {
								$avatarImg.attr('src', response.avatar_url + '?t=' + new Date().getTime());
							}
							showDialog('<?php esc_html_e( 'Success', 'webmention' ); ?>', '<?php esc_html_e( 'Avatar refreshed successfully.', 'webmention' ); ?>', 'success');
							$button.prop('disabled', false).text('<?php esc_html_e( 'Refresh', 'webmention' ); ?>');
							// Reload full list in background
							setTimeout(loadAvatars, 500);
						},
						error: function(xhr) {
							var error = '<?php esc_html_e( 'Failed to refresh avatar.', 'webmention' ); ?>';
							if (xhr.status === 404) {
								error = '<?php esc_html_e( 'Refresh endpoint not found. Please try refreshing the page.', 'webmention' ); ?>';
							} else if (xhr.responseJSON && xhr.responseJSON.message) {
								error = xhr.responseJSON.message;
							} else if (xhr.responseJSON && xhr.responseJSON.code) {
								error = xhr.responseJSON.code + ': ' + (xhr.responseJSON.message || error);
							}
							console.error('Refresh avatar error:', xhr.status, xhr.responseJSON, 'URL:', refreshUrl);
							showDialog('<?php esc_html_e( 'Error', 'webmention' ); ?>', error, 'error');
							$button.prop('disabled', false).text('<?php esc_html_e( 'Refresh', 'webmention' ); ?>');
						}
					});
				});

				// Handle replace
				$('.webmention-replace-avatar').off('click').on('click', function() {
					var $row = $(this).closest('tr');
					var authorUrl = $row.data('author-url');
					var host = $row.data('host');
					var $avatarImg = $row.find('img');

					if (!authorUrl) {
						showDialog('<?php esc_html_e( 'Error', 'webmention' ); ?>', '<?php esc_html_e( 'Author URL not available for this avatar.', 'webmention' ); ?>', 'error');
						return;
					}

					// Create dialog HTML
					var dialogHtml = '<div id="webmention-replace-dialog" style="display:none;">' +
						'<p><label for="webmention-new-avatar-url"><?php esc_html_e( 'Enter new avatar URL:', 'webmention' ); ?></label></p>' +
						'<p><input type="url" id="webmention-new-avatar-url" class="regular-text" style="width:100%;" /></p>' +
						'</div>';

					// Remove existing dialog if any
					$('#webmention-replace-dialog').remove();
					$('body').append(dialogHtml);

					var $dialog = $('#webmention-replace-dialog');
					var $input = $('#webmention-new-avatar-url');

					// Show WordPress-style dialog
					$dialog.dialog({
						title: '<?php esc_html_e( 'Replace Avatar', 'webmention' ); ?>',
						modal: true,
						width: 500,
						resizable: false,
						buttons: {
							'<?php esc_html_e( 'Replace', 'webmention' ); ?>': function() {
								var newUrl = $input.val().trim();
								if (!newUrl) {
									showDialog('<?php esc_html_e( 'Error', 'webmention' ); ?>', '<?php esc_html_e( 'Please enter a valid URL.', 'webmention' ); ?>', 'error');
									return;
								}

								$dialog.dialog('close');
								var $button = $row.find('.webmention-replace-avatar');
								$button.prop('disabled', true).text('<?php esc_html_e( 'Replacing...', 'webmention' ); ?>');

								$.ajax({
									url: '<?php echo esc_url( rest_url( 'webmention/1.0/avatar/replace' ) ); ?>',
									method: 'POST',
									beforeSend: function(xhr) {
										xhr.setRequestHeader( 'X-WP-Nonce', nonce );
									},
									data: {
										author_url: authorUrl,
										host: host,
										avatar_url: newUrl
									},
									success: function(response) {
										// Update avatar image immediately
										if (response.avatar_url) {
											$avatarImg.attr('src', response.avatar_url + '?t=' + new Date().getTime());
										}
										showDialog('<?php esc_html_e( 'Success', 'webmention' ); ?>', '<?php esc_html_e( 'Avatar replaced successfully.', 'webmention' ); ?>', 'success');
										$button.prop('disabled', false).text('<?php esc_html_e( 'Replace', 'webmention' ); ?>');
										// Reload full list in background
										setTimeout(loadAvatars, 500);
									},
									error: function(xhr) {
										var error = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '<?php esc_html_e( 'Failed to replace avatar.', 'webmention' ); ?>';
										showDialog('<?php esc_html_e( 'Error', 'webmention' ); ?>', error, 'error');
										$button.prop('disabled', false).text('<?php esc_html_e( 'Replace', 'webmention' ); ?>');
									}
								});
							},
							'<?php esc_html_e( 'Cancel', 'webmention' ); ?>': function() {
								$dialog.dialog('close');
							}
						},
						open: function() {
							$input.focus();
						},
						close: function() {
							$dialog.remove();
						}
					});
				});

				// Show dialog function
				function showDialog(title, message, type) {
					var icon = type === 'success' ? 'yes' : 'warning';
					var dialogHtml = '<div id="webmention-message-dialog" style="display:none;">' +
						'<p>' + escapeHtml(message) + '</p>' +
						'</div>';

					$('#webmention-message-dialog').remove();
					$('body').append(dialogHtml);

					$('#webmention-message-dialog').dialog({
						title: title,
						modal: true,
						width: 400,
						resizable: false,
						buttons: {
							'<?php esc_html_e( 'OK', 'webmention' ); ?>': function() {
								$(this).dialog('close');
							}
						},
						close: function() {
							$(this).remove();
						}
					});
				}
			}

			function escapeHtml(text) {
				var map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
			}

			loadAvatars();
		});
		</script>
	<?php endif; ?>
</div>
