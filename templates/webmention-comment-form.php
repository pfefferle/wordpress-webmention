<form id="webmention-form" action="<?php echo get_webmention_endpoint(); ?>" method="post">
	<p>
		<label for="webmention-source"><?php echo get_webmention_form_text( get_the_ID() ); ?></label>
		<input id="webmention-source" type="url" name="source" placeholder="<?php _e( 'URL/Permalink of your article', 'webmention' ); ?>" />
	</p>
	<p>
		<input id="webmention-submit" type="submit" name="submit" value="<?php _e( 'Ping me!', 'webmention' ); ?>" />
	</p>
	<input id="webmention-target" type="hidden" name="target" value="<?php the_permalink(); ?>" />
</form>
