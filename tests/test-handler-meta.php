<?php
class Webmention_Handler_Meta_Test extends WP_UnitTestCase {
	public function test_ogp() {
		require_once( dirname( __FILE__ ) . '/../includes/handlers/class-webmention-handler-base.php' );
		require_once( dirname( __FILE__ ) . '/../includes/handlers/class-webmention-handler-meta.php' );
		require_once( dirname( __FILE__ ) . '/../includes/entities/class-webmention-item.php' );

		$request = new Webmention_Request();
		$request->set_content_type( 'text/html' );
		$request->set_body( file_get_contents( dirname( __FILE__ ) . '/data/open-graph-test.html' ) );

		$handler = new Webmention_Handler_Meta();

		$handler->parse( $request );

		$this->assertEquals( 'Hier & Jetzt – Open Web Nr. 5', $handler->get_webmention_item()->get_name() );
		$this->assertEquals( 'Was bedeutet die Annäherung von WordPress an Matrix? (Datei herunterladen) Marcel und ich sprechen nochmal über Automattic, WordPress und Matrix. Marcel ist übrigens nicht ganz so pessimistisch wie ich und kann dem Ganzen sogar etwas Positives abgewinnen. ‚Hier & Jetzt‘ kann man per RSS-Feed abonnieren und findet man natürlich auch bei Apple Podcast und in [...]', $handler->get_webmention_item()->get_content() );
		$this->assertEquals( 'https://notiz.blog/2020/06/17/hier-und-jetzt-open-web-nr-5/', $handler->get_webmention_item()->get_url() );
	}
}