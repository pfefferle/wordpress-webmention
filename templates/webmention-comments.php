<?php
$mentions = get_comments(
	array(
		'post_id'  => get_the_ID(),
		'type__in' => get_webmention_comment_type_names(),
		'status'   => 'approve',
	)
);

$grouped_mentions = separate_comments( $mentions );

foreach ( $grouped_mentions as $mention_type => $mentions ) {
	if ( empty( $mentions ) ) {
		continue;
	}
	?>

<ul class="reaction-list">
	<h2><?php echo get_webmention_comment_type_label( $mention_type ); ?></h2>
	<?php
	wp_list_comments(
		array(
			'avatar_only' => true,
			'avatar_size' => 64,
		),
		$mentions
	);
	?>
</ul>
	<?php
}

load_template( locate_template( 'comments.php' ) );
?>
