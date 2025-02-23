<?php

use Webmention\Sender;

class Test_Sender extends WP_UnitTestCase {
	private $post;

	public function set_up() {
		parent::set_up();

		// Erstelle einen Test-Beitrag
		$this->post = self::factory()->post->create_and_get( array(
			'post_content' => 'Test post with a link to <a href="https://example.com">Example</a>',
		) );
	}

	public function test_send_webmentions() {
		// Mock der Webmention-Endpunkt-Entdeckung
		add_filter( 'webmention_server_url', function( $url, $target ) {
			return 'https://example.com/webmention';
		}, 10, 2 );

		// Mock der HTTP-Anfrage
		add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => 'Webmention received',
			);
		}, 10, 3 );

		$result = Sender::send_webmentions( $this->post->ID );

		// Überprüfe, ob die Webmention gesendet wurde
		$this->assertIsArray( $result );
		$this->assertContains( 'https://example.com', $result );

		// Überprüfe, ob die URLs in den Post-Meta gespeichert wurden
		$mentioned_urls = get_post_meta( $this->post->ID, '_webmentioned', true );
		$this->assertIsArray( $mentioned_urls );
		$this->assertContains( 'https://example.com', $mentioned_urls );
	}

	public function test_update_ping() {
		$pinged = array(
			'https://example1.com',
			'https://example2.com',
		);

		$result = Sender::update_ping( $this->post->ID, $pinged );

		// Überprüfe, ob die Pings aktualisiert wurden
		$this->assertIsString( $result );
		$this->assertEquals( implode( "\n", $pinged ), $result );

		// Überprüfe die Datenbank direkt
		$updated_post = get_post( $this->post->ID );
		$this->assertEquals( $result, $updated_post->pinged );
	}

	public function test_update_ping_invalid_post() {
		$result = Sender::update_ping( 999999, array( 'https://example.com' ) );
		$this->assertFalse( $result );
	}

	public function test_update_ping_invalid_pinged() {
		$result = Sender::update_ping( $this->post->ID, 'not an array' );
		$this->assertFalse( $result );
	}

	public function test_send_webmentions_with_error_response() {
		// Mock der Webmention-Endpunkt-Entdeckung
		add_filter( 'webmention_server_url', function( $url, $target ) {
			return 'https://example.com/webmention';
		}, 10, 2 );

		// Mock der HTTP-Anfrage mit 500er Fehler
		add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
			return array(
				'response' => array(
					'code'    => 500,
					'message' => 'Internal Server Error',
				),
				'body'     => 'Server Error',
				'headers'  => array(),
				'cookies'  => array(),
			);
		}, 10, 3 );

		$result = Sender::send_webmentions( $this->post->ID );

		// Überprüfe, ob der Versuch neu geplant wurde
		$this->assertTrue( metadata_exists( 'post', $this->post->ID, '_mentionme' ) );
		$this->assertEquals( '1', get_post_meta( $this->post->ID, '_mentionme_tries', true ) );
	}

	public function tear_down() {
		parent::tear_down();
		remove_all_filters( 'webmention_server_url' );
		remove_all_filters( 'pre_http_request' );
	}
}
