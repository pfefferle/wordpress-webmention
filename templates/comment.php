<?php 
global $wp_query, $post;
$comment_id = $wp_query->query['replytocom'];
$comment = get_comment($comment_id);
$permalink = get_permalink($post->ID);

get_header();
?>
<section id="primary">
  <main id="content" role="main">
    
    <article id="comment-<?php comment_ID(); ?>" class="format-status post h-comment h-as-comment h-entry hentry <?php $comment->comment_type; ?>">
      <div class="entry-content e-content p-summary p-name"><?php comment_text(); ?></div>

      <footer class="entry-meta">
        <address class="comment-author p-author author vcard hcard h-card">
          <?php echo get_avatar( $comment, 50 ); ?>
          <?php printf( __( '%s <span class="says">says:</span>', 'sempress' ), sprintf( '<cite class="fn p-name">%s</cite>', get_comment_author_link() ) ); ?>
        </address><!-- .comment-author .vcard -->
        <?php if ( $comment->comment_approved == '0' ) : ?>
          <em><?php _e( 'Your comment is awaiting moderation.', 'sempress' ); ?></em>
          <br />
        <?php endif; ?>

        <div class="comment-meta commentmetadata">
          <a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>"><time datetime="<?php comment_time( 'c' ); ?>" class="dt-published dt-updated published updated">
          <?php
            /* translators: 1: date, 2: time */
            printf( __( '%1$s at %2$s', 'sempress' ), get_comment_date(), get_comment_time() ); ?>
          </time></a>
          <?php edit_comment_link( __( '(Edit)', 'sempress' ), ' ' ); ?>
        </div><!-- .comment-meta .commentmetadata -->
      </footer>
    </article><!-- #comment-## -->
    

    <?php comment_form( array( 'format' => 'html5' ) ); ?>
  </main><!-- #content -->
</section><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
