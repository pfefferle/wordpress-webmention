<ul id="webmention-home-mentions-list" class="webmention-home-mentions-list">
<?php
foreach ( $home_mentions as $mention ) {
	$mention_content = get_comment_text( $mention );
	echo "<li class='mention-{$mention->comment_ID}'>{$mention_content} by {$mention->comment_author}</li>";
}
?>
</ul>
