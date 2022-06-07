<?php


$mentions = get_comments(
	array(
		'post_id' => get_the_ID(),
		'type__in' => get_webmention_comment_type_names(),
		'status' => 'approve',
	)
);

echo '<ul class="mention-list">';

wp_list_comments( 
	array(
		'avatar_only' => true,
		'avatar_size' => 64
	),
	$mentions
); 

echo '</ul>';

load_template( get_theme_file_path( '/comments.php' ) );
