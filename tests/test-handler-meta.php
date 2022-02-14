<?php
class Webmention_Handler_Meta_Test extends WP_UnitTestCase {
	public function test_ogp() {
		require_once( dirname( __FILE__ ) . '/../includes/Handler/class-base.php' );
		require_once( dirname( __FILE__ ) . '/../includes/Handler/class-meta.php' );
		require_once( dirname( __FILE__ ) . '/../includes/Entity/class-item.php' );
		require_once( dirname( __FILE__ ) . '/../includes/class-response.php' );

		$response = new \Webmention\Response( 'http://example.com/webmention/target/placeholder' );
		$response->set_content_type( 'text/html' );
		$response->set_body( file_get_contents( dirname( __FILE__ ) . '/data/open-graph-test.html' ) );

		$handler = new \Webmention\Handler\Meta();

		$handler->parse( $response, 'http://example.com/webmention/target/placeholder' );

		$this->assertEquals( 'Hier & Jetzt – Open Web Nr. 5', $handler->get_webmention_item()->get_name() );
		$this->assertEquals( 'Was bedeutet die Annäherung von WordPress an Matrix? (Datei herunterladen) Marcel und ich sprechen nochmal über Automattic, WordPress und Matrix. Marcel ist übrigens nicht ganz so pessimistisch wie ich und kann dem Ganzen sogar etwas Positives abgewinnen. ‚Hier &amp; Jetzt‘ kann man per RSS-Feed abonnieren und findet man natürlich auch bei Apple Podcast und in [...]', $handler->get_webmention_item()->get_content() );
		$this->assertEquals( 'https://notiz.blog/2020/06/17/hier-und-jetzt-open-web-nr-5/', $handler->get_webmention_item()->get_url() );
	}
}