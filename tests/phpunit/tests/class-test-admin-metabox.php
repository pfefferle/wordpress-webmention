<?php
/**
 * Test the Webmention comment edit metabox.
 *
 * @package Webmention
 */

use Webmention\WP_Admin\Admin;

/**
 * Test the Webmention comment edit metabox for stored XSS regressions.
 */
class Test_Admin_Metabox extends WP_UnitTestCase {
	/**
	 * An attribute-breaking payload as supplied through parsed Webmention metadata.
	 *
	 * @var string
	 */
	const XSS_PAYLOAD = 'https://attacker.invalid/avatar.jpg" /><script>alert(1)</script><input value="';

	/**
	 * Render the comment metabox for a given comment.
	 *
	 * @param WP_Comment $comment The comment to render the metabox for.
	 *
	 * @return string The captured metabox HTML.
	 */
	private function render_metabox( $comment ) {
		$GLOBALS['comment'] = $comment;
		ob_start();
		Admin::comment_metabox( $comment );
		$output = ob_get_clean();
		unset( $GLOBALS['comment'] );

		return $output;
	}

	/**
	 * The URL meta should be sanitized with esc_url_raw before it is stored.
	 */
	public function test_url_meta_is_sanitized_on_store() {
		$comment_id = self::factory()->comment->create();

		foreach ( array( 'avatar', 'url', 'webmention_source_url', 'webmention_target_url' ) as $meta_key ) {
			add_comment_meta( $comment_id, $meta_key, self::XSS_PAYLOAD, true );
			$stored = get_comment_meta( $comment_id, $meta_key, true );

			$this->assertStringNotContainsString( '<script>', $stored, "Stored {$meta_key} must not contain a script tag." );
			$this->assertStringNotContainsString( '"', $stored, "Stored {$meta_key} must not contain a double quote." );
			$this->assertStringNotContainsString( '<', $stored, "Stored {$meta_key} must not contain an angle bracket." );
		}
	}

	/**
	 * Even a raw (pre-sanitization) value in the database must be escaped on output.
	 */
	public function test_metabox_escapes_unsanitized_meta_on_output() {
		global $wpdb;

		$comment_id = self::factory()->comment->create();
		update_comment_meta( $comment_id, 'protocol', 'webmention' );

		// Simulate a legacy row written before the sanitize callback existed by
		// inserting the raw payload directly, bypassing the sanitize callback.
		$wpdb->insert(
			$wpdb->commentmeta,
			array(
				'comment_id' => $comment_id,
				'meta_key'   => 'avatar',
				'meta_value' => self::XSS_PAYLOAD,
			)
		);
		wp_cache_delete( $comment_id, 'comment_meta' );

		// Confirm the unescaped payload really is in the database.
		$this->assertSame( self::XSS_PAYLOAD, get_comment_meta( $comment_id, 'avatar', true ) );

		$output = $this->render_metabox( get_comment( $comment_id ) );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output, 'The metabox must not emit an unescaped script tag.' );
		$this->assertStringNotContainsString( 'value="' . self::XSS_PAYLOAD . '"', $output, 'The raw payload must not break out of the value attribute.' );
	}
}
