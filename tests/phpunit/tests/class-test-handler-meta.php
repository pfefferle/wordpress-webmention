<?php
/**
 * Test Handler Meta class.
 *
 * @package Webmention
 */

/**
 * Test Handler Meta class.
 */
class Test_Handler_Meta extends WP_UnitTestCase {
	/**
	 * Test OGP parsing.
	 */
	public function test_ogp() {
		$response = new \Webmention\Response( 'http://example.com/webmention/target/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( WEBMENTION_TESTS_DIR . '/data/open-graph-test.html' ) );

		$handler = new \Webmention\Handler\Meta();

		$handler->parse( $response, 'http://example.com/webmention/target/placeholder' );

		$this->assertEquals( 'Hier & Jetzt – Open Web Nr. 5', $handler->get_webmention_item()->get_name() );
		$this->assertEquals( "Was bedeutet die Annäherung von WordPress an Matrix? (Datei herunterladen) Marcel und ich sprechen nochmal über Automattic, WordPress und Matrix. Marcel ist übrigens nicht ganz so pessimistisch wie ich und kann dem Ganzen sogar etwas Positives abgewinnen. \u{201A}Hier &amp; Jetzt\u{2018} kann man per RSS-Feed abonnieren und findet man natürlich auch bei Apple Podcast und in [...]", $handler->get_webmention_item()->get_content() );
		$this->assertEquals( 'https://notiz.blog/2020/06/17/hier-und-jetzt-open-web-nr-5/', $handler->get_webmention_item()->get_url() );
	}
}
