<?php

namespace Webmention;

/**
 * Undocumented class
 */
class Comment {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 *
	 * @return void
	 */
	public static function init() {
		self::register_comment_types();

		add_filter( 'query_vars', array( static::class, 'query_var' ) );

		// Threaded comments support
		add_filter( 'template_include', array( static::class, 'comment_template_include' ) );

		add_filter( 'get_comment_link', array( static::class, 'remote_comment_link' ), 11, 2 );

		// Default Comment Status.
		add_filter( 'get_default_comment_status', 'webmention_get_default_comment_status', 11, 3 );

		add_action( 'comment_form_after', 'webmention_comment_form', 11 );
		add_action( 'comment_form_comments_closed', 'webmention_comment_form' );
	}

	/**
	 * Link remote comments to source url.
	 *
	 * @param string            $comment_link The comment link.
	 * @param object|WP_Comment $comment      The comment object.
	 *
	 * @return string $url The url of the source.
	 */
	public static function remote_comment_link( $comment_link, $comment ) {
		if ( is_admin() || ! is_webmention( $comment ) ) {
			return $comment_link;
		}

		$webmention_link = get_url_from_webmention( $comment );

		if ( $webmention_link ) {
			return $webmention_link;
		}

		return $comment_link;
	}

	/**
	 * Return the registered custom comment types.
	 *
	 * @return array The registered custom comment types
	 */
	public static function get_comment_types() {
		global $webmention_comment_types;

		return $webmention_comment_types;
	}

	/**
	 * Is this a registered comment type
	 *
	 * @param string $slug The name of the type
	 * @return boolean True if registered.
	 */
	public static function is_registered_comment_type( $slug ) {
		return in_array( $slug, array_merge( self::get_comment_type_names(), array( 'comment', 'pingback', 'trackback' ) ), true );
	}

	/**
	 * Return the registered custom comment types names plus Webmention for backcompat.
	 *
	 * @return array The registered custom comment type names
	 */
	public static function get_comment_type_names() {
		$types   = array_values( wp_list_pluck( self::get_comment_types(), 'name' ) );
		$types[] = 'webmention';

		return $types;
	}

	public static function get_comment_type_attr( $type, $attr ) {
		$types = self::get_comment_types();

		if ( in_array( $type, array_keys( $types ), true ) ) {
			$comment_type = $types[ $type ];
		} else {
			$comment_type = $types['mention'];
		}

		$return = $comment_type->get( $attr );

		return apply_filters( "webmention_comment_type_{$attr}", $return, $type );
	}

	/**
	 * Register the comment types used by the Webmention plugin
	 *
	 * The icons are the original SVGs from `@wordpress/icons` 18.0.0
	 * (`packages/icons/src/library`): repost `update`, like `thumbs-up`,
	 * favorite `star-filled`, tag `tag`, bookmark `pin`, listen `audio`,
	 * watch `video`, read `page`, follow `people` and mention `comment`.
	 *
	 * @return void
	 */
	public static function register_comment_types() {
		register_webmention_comment_type(
			'repost',
			array(
				'label'       => __( 'Reposts', 'webmention' ),
				'singular'    => __( 'Repost', 'webmention' ),
				'description' => __( 'A repost on the indieweb is a post that is purely a 100% re-publication of another (typically someone else\'s) post.', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M12 5.5L17.7929 11.2929C18.1834 11.6834 18.1834 12.3166 17.7929 12.7071L16 14.5M16 5.5H12V9.5M12 18.5L6.20711 12.7071C5.81658 12.3166 5.81658 11.6834 6.20711 11.2929L8 9.5M8 18.5H12V14.5" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-repost',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s reposted %2$s on <a href="%3$s">%4$s</a>.', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'like',
			array(
				'label'       => __( 'Likes', 'webmention' ),
				'singular'    => __( 'Like', 'webmention' ),
				'description' => __( 'A like is a popular webaction button and in some cases post type on various silos such as Facebook and Instagram.', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M4.00002 12L5.00001 20M13.2295 4.75C12.7709 4.75 12.3398 4.96848 12.0686 5.33829L7.06032 12.1678C7.02166 12.2205 7.00519 12.2862 7.01443 12.351L7.87735 18.3914C7.94773 18.8841 8.36965 19.25 8.8673 19.25L17.2725 19.25C17.7062 19.25 18.0905 18.9705 18.224 18.5578L20.2211 12.3848C20.4823 11.5774 19.8804 10.75 19.0318 10.75L14.9758 10.75C14.3435 10.75 13.8711 10.1689 14 9.55L14.6389 6.48326C14.8252 5.58927 14.1427 4.75 13.2295 4.75Z" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-like',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s liked %2$s on <a href="%3$s">%4$s</a>.', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'favorite',
			array(
				'label'       => __( 'Favorites', 'webmention' ),
				'singular'    => __( 'Favorite', 'webmention' ),
				'description' => __( 'A favorite is a common webaction on many silos (like Flickr, Twitter), typically visually indicated with a star symbol that fills in with a color when activated (pink, orange).', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M12 5.71576L13.6106 8.97924C13.7563 9.27438 14.0379 9.47895 14.3636 9.52628L17.965 10.0496L15.359 12.5899C15.1233 12.8196 15.0157 13.1506 15.0714 13.475L15.6866 17.0619L12.4653 15.3684C12.174 15.2152 11.826 15.2152 11.5347 15.3684L8.31341 17.0619L8.92861 13.475C8.98425 13.1506 8.8767 12.8196 8.64102 12.5899L6.03497 10.0496L9.63644 9.52628C9.96215 9.47895 10.2437 9.27438 10.3894 8.97924L12 5.71576Z" fill="currentColor" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-favorite',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s favorited %2$s on <a href="%3$s">%4$s</a>.', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'tag',
			array(
				'label'       => __( 'Tags', 'webmention' ),
				'singular'    => __( 'Tag', 'webmention' ),
				'description' => __( 'Tags or tagging refers to categorizing or labeling content, your own or others (tag-reply), with words, phrases, names, or other information, optionally linked to specific people, events, locations, such as the practice of tagging posts being about certain people (person-tag), like tagging people or other items where (area-tag) they\'re depicted in a photo.', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M4.75 12.5429V4.85C4.75 4.79477 4.79477 4.75 4.85 4.75H12.5429C12.6755 4.75 12.8027 4.80268 12.8964 4.89645L20.1464 12.1464C20.3417 12.3417 20.3417 12.6583 20.1464 12.8536L12.8536 20.1464C12.6583 20.3417 12.3417 20.3417 12.1464 20.1464L4.89645 12.8964C4.80268 12.8027 4.75 12.6755 4.75 12.5429Z" vector-effect="non-scaling-stroke" /><path d="M9.75 8.75a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" fill="currentColor" stroke="none" /></svg>',
				'class'       => 'p-tag',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s tagged %2$s on <a href="%3$s">%4$s</a>.', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'bookmark',
			array(
				'label'       => __( 'Bookmarks', 'webmention' ),
				'singular'    => __( 'Bookmark', 'webmention' ),
				'description' => __( 'A bookmark (or linkblog) is a post that is primarily comprised of a URL, often title text from that URL, sometimes optional text describing, tagging, or quoting from its contents.', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M5 19L10 14M13.5355 17.5355L6.46447 10.4645C7.731 9.19794 9.50794 8.75292 11.1332 9.12941L14.5 4.50002L19.5 9.50002L14.8706 12.8669C15.2471 14.4921 14.8021 16.269 13.5355 17.5355Z" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-bookmark',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s bookmarked %2$s on <a href="%3$s">%4$s</a>.', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'listen',
			array(
				'label'       => __( 'Listens', 'webmention' ),
				'singular'    => __( 'Listen', 'webmention' ),
				'description' => __( 'A "listen" is a passive type of post used to publish a song (music or audio track, including concert recordings or DJ sets) or podcast that you have listened to.', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M13.25 16.5C13.25 18.0188 12.0188 19.25 10.5 19.25C8.98122 19.25 7.75 18.0188 7.75 16.5C7.75 14.9812 8.98122 13.75 10.5 13.75C12.0188 13.75 13.25 14.9812 13.25 16.5Z" fill="currentColor" stroke="none" /><path d="M10.5 19.25C12.0188 19.25 13.25 18.0188 13.25 16.5C13.25 14.9812 12.0188 13.75 10.5 13.75C8.98122 13.75 7.75 14.9812 7.75 16.5C7.75 18.0188 8.98122 19.25 10.5 19.25ZM10.5 19.25C13.4867 19.25 13.75 16.5 13.75 15.1379V7.99998C13.75 4.9108 16.4 5.00254 18 5.00233" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-listen',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s <strong>listened</strong> to %2$s (via <a href="%3$s">%4$s</a>).', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'watch',
			array(
				'label'       => __( 'Watches', 'webmention' ),
				'singular'    => __( 'Watch', 'webmention' ),
				'description' => __( 'A watch is a semi-passive type of post used to publish that you have watched a video (movie, TV, film), or a live show (theater, concert).', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M10 15L15 12L10 9V15Z" fill="currentColor" stroke="none" /><path d="M4.75 6C4.75 5.30964 5.30964 4.75 6 4.75H18C18.6904 4.75 19.25 5.30964 19.25 6V18C19.25 18.6904 18.6904 19.25 18 19.25H6C5.30964 19.25 4.75 18.6904 4.75 18V6Z" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-watch',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s <strong>watched</strong> %2$s (via <a href="%3$s">%4$s</a>).', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'read',
			array(
				'label'       => __( 'Reads', 'webmention' ),
				'singular'    => __( 'Read', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M8.5 8.25H15.5M8.5 11.75H15.5M8.5 15.25H15.5M18.25 18V6C18.25 5.30964 17.6904 4.75 17 4.75H7C6.30964 4.75 5.75 5.30964 5.75 6L5.75 18C5.75 18.6904 6.30964 19.25 7 19.25H17C17.6904 19.25 18.25 18.6904 18.25 18Z" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-read',
				'description' => __( 'To read or reading is the act of viewing and interpreting posts or other documents; on the IndieWeb, a read post expresses that something has been read, like a book or section thereof.', 'webmention' ),
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s <strong>read</strong> %2$s (via <a href="%3$s">%4$s</a>).', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'follow',
			array(
				'label'       => __( 'Follows', 'webmention' ),
				'singular'    => __( 'Follow', 'webmention' ),
				'description' => __( 'Follow is a common feature (and often UI button) in silo UIs (like Twitter) that adds updates from that profile (typically a person) to the stream shown in an integrated reader, and sometimes creates a follow post either in the follower\'s stream ("… followed …" or "… is following …") thus visible to their followers, and/or in the notifications of the user being followed ("… followed you").', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M4.5 17V15C4.5 13.8954 5.39543 13 6.5 13H10.5C11.6046 13 12.5 13.8954 12.5 15V17M15 13H17.5C18.6046 13 19.5 13.8954 19.5 15V17M10.25 8.5C10.25 9.4665 9.4665 10.25 8.5 10.25C7.5335 10.25 6.75 9.4665 6.75 8.5C6.75 7.5335 7.5335 6.75 8.5 6.75C9.4665 6.75 10.25 7.5335 10.25 8.5ZM17.25 8.5C17.25 9.4665 16.4665 10.25 15.5 10.25C14.5335 10.25 13.75 9.4665 13.75 8.5C13.75 7.5335 14.5335 6.75 15.5 6.75C16.4665 6.75 17.25 7.5335 17.25 8.5Z" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-follow',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s <strong>followed</strong> %2$s (via <a href="%3$s">%4$s</a>).', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'mention',
			array(
				'label'       => __( 'Mentions', 'webmention' ),
				'singular'    => __( 'Mention', 'webmention' ),
				'description' => __( 'A mention is a post which links to another post without explicitly being in response to it. In contrast, a reply, like, or repost are explicit responses to a post.', 'webmention' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="fill: none" stroke="currentColor" stroke-width="1.5"><path d="M18 5.5H6C5.30964 5.5 4.75 6.05964 4.75 6.75V16V18.4096C4.75 18.8725 5.32471 19.0869 5.62784 18.7371L8 16H18C18.6904 16 19.25 15.4404 19.25 14.75V6.75C19.25 6.05964 18.6904 5.5 18 5.5Z" vector-effect="non-scaling-stroke" /></svg>',
				'class'       => 'p-mention',
				// translators: %1$s username, %2$s opject format (post, audio, ...), %3$s URL, %4$s domain
				'excerpt'     => __( '%1$s <strong>mentioned</strong> %2$s on <a href="%3$s">%4$s</a>.', 'webmention' ),
			)
		);

		register_webmention_comment_type(
			'reacji',
			array(
				'label'       => __( 'Reacjis', 'webmention' ),
				'singular'    => __( 'Reacji', 'webmention' ),
				'description' => __( 'Reacji is an emoji reaction, the use of a single emoji character in response to a post.', 'webmention' ),
				'class'       => 'p-reacji',
				'excerpt'     => '%s',
			)
		);
	}

	/**
	 * replace the template for all URLs with a "replytocom" query-param
	 *
	 * @param string $template the template url
	 *
	 * @return string
	 */
	public static function comment_template_include( $template ) {
		global $wp_query;

		// replace template
		if ( isset( $wp_query->query['replytocom'] ) ) {
			return apply_filters( 'webmention_comment_template', WEBMENTION_PLUGIN_DIR . 'templates/comment.php' );
		}

		return $template;
	}

	/**
	 * adds some query vars
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function query_var( $vars ) {
		$vars[] = 'replytocom';
		return $vars;
	}
}
