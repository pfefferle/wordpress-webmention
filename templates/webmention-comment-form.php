<form id="webmention-form" action="<?php echo get_webmention_endpoint(); ?>" method="post">
	<p>
		<label for="webmention-source"><?php _e( 'Responding with a post on your own blog? Send me a <a href="http://indieweb.org/webmention">WebMention</a> by writing something on your website that links to this post and then enter your post URL below.', 'webmention' ); ?></label>
		<input id="webmention-source" type="url" name="source" placeholder="<?php _e( 'URL/Permalink of your article', 'webmention' ); ?>" />
	</p>
	<p>
		<input id="webmention-submit" type="submit" name="submit" value="Ping me!" />
	</p>
	<input id="webmention-target" type="hidden" name="target" value="<?php the_permalink(); ?>" />
</form>
