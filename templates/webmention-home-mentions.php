<ul id="webmention-home-mentions-list">
<?php
foreach ( $home_mentions as $mention ) {
	echo "<li>{$mention->comment_content} by {$mention->comment_author}</li>";
}
?>
</ul>
