<?php
$mentions = get_comments(
	array(
		'post_id'  => get_the_ID(),
		'type__in' => get_webmention_comment_type_names(),
		'status'   => 'approve',
	)
);
?>

<ul class="mention-list">

<?php
wp_list_comments(
	array(
		'avatar_only' => true,
		'avatar_size' => 64
	),
	$mentions
);
?>
</ul>

<?php load_template( locate_template( 'comments.php' ) ); ?>
