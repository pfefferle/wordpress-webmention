<?php

namespace Webmention;

use WP_Query;

/**
 * Send HTTP 410 for deleted posts
 *
 * @author Matthias Pfefferle
 */
class HTTP_Gone {
	/**
	 * Initialize Deleted Posts Plugin
	 */
	public static function init() {
		add_action( 'template_redirect', array( static::class, 'handle_410' ), 99 );
	}

	public static function handle_410() {
		if ( ! is_404() ) {
			return;
		}

		global $wp_query;

		// check slug
		if ( ! empty( $wp_query->query['pagename'] ) ) {
			$query = new WP_Query(
				array(
					'pagename'    => $wp_query->query['pagename'] . '__trashed',
					'post_status' => 'trash',
				)
			);
		} elseif ( ! empty( $wp_query->query['name'] ) ) {
			$query = new WP_Query(
				array(
					'name'        => $wp_query->query['name'] . '__trashed',
					'post_status' => 'trash',
				)
			);
		} else {
			return;
		}

		// return 410
		if ( $query->get_posts() ) {
			status_header( 410 );
			// check if theme has a 410.php template
			$template_410 = get_query_template( 410 );
			// return 410 template
			if ( $template_410 ) {
				load_template( $template_410 );
				exit;
			}
		}
	}
}
