<?php
$mentions = get_comments(
	array(
		'post_id'  => get_the_ID(),
		'type__in' => get_webmention_comment_type_names(),
		'status'   => 'approve',
	)
);

$grouped_mentions = separate_comments( $mentions );
$fold_limit = get_option( 'webmention_facepile_fold_limit', 0 );

do_action( 'webmention_before_reaction_list' );

foreach ( $grouped_mentions as $mention_type => $mentions ) {
	if ( empty( $mentions ) ) {
		continue;
	}
	?>

<ul class="reaction-list reaction-list--<?php echo esc_attr( $mention_type ); ?>">
	<h2><?php echo get_webmention_comment_type_attr( $mention_type, 'label' ); ?></h2>

	<?php if( ( $fold_limit > 0 ) && $fold_limit < count( $mentions ) ) { 
		$overflow = array_slice( $mentions, $fold_limit );
		$show = array_slice( $mentions, 0, $fold_limit );
	?>
		<details class="webmention-facepile">
		<summary>
		<?php 
			wp_list_comments( 
				array(
					'avatar_only' => true,
					'avatar_size' => 64,
				),
				$show
			);
		?>
		</summary>
		<?php
			wp_list_comments( 
				array(
					'avatar_only' => true,
					'avatar_size' => 64,
				),
				$overflow
			);
		?>
		</details>
	<?php
	} else {
		wp_list_comments(
			array(
				'avatar_only' => true,
				'avatar_size' => 64,

			),
			$mentions
		);
	}
	?>
</ul>
	<?php
}

do_action( 'webmention_after_reaction_list' );
