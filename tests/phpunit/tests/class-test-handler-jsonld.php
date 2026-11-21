<?php
/**
 * Test Handler JSON-LD class.
 *
 * @package Webmention
 */

/**
 * Test Handler JSON-LD class.
 */
class Test_Handler_Jsonld extends WP_UnitTestCase {
	/**
	 * Parse a JSON-LD document.
	 *
	 * @param string $jsonld The JSON-LD document.
	 *
	 * @return \Webmention\Entity\Item The parsed item.
	 */
	private function parse_jsonld( $jsonld ) {
		$response = new \Webmention\Response( 'https://www.example.org/blog/a-post/' );
		$response->set_content_type( 'text/html' );
		$response->set_body( sprintf( '<html><head><script type="application/ld+json">%s</script></head><body></body></html>', $jsonld ) );

		$handler = new \Webmention\Handler\Jsonld();
		$handler->parse( $response, 'http://example.com/webmention/target/placeholder' );

		return $handler->get_webmention_item();
	}

	/**
	 * Images and the photo that has to be picked.
	 *
	 * @return array[]
	 */
	public function data_images() {
		return array(
			'URL'            => array( '"https://www.example.org/image.png"', 'https://www.example.org/image.png' ),
			'list of URLs'   => array( '["https://www.example.org/small.png","https://www.example.org/large.png"]', 'https://www.example.org/large.png' ),
			'ImageObject'    => array( '{"@type":"ImageObject","url":"https://www.example.org/image.png"}', 'https://www.example.org/image.png' ),
			'without a URL'  => array( '{"@type":"ImageObject"}', array() ),
			'another object' => array( '{"@type":"Thing","url":"https://www.example.org/thing"}', array() ),
		);
	}

	/**
	 * The image of an article is its photo.
	 *
	 * @dataProvider data_images
	 *
	 * @param string       $image    The JSON-LD image.
	 * @param string|array $expected The expected photo.
	 */
	public function test_image( $image, $expected ) {
		$item = $this->parse_jsonld( sprintf( '{"@context":"https://schema.org","@type":"Article","image":%s}', $image ) );

		$this->assertSame( $expected, $item->get_photo() );
	}

	/**
	 * The keywords of an article are kept as category.
	 */
	public function test_keywords() {
		$item = $this->parse_jsonld( '{"@context":"https://schema.org","@type":"Article","headline":"A post","keywords":"one, two"}' );

		$this->assertSame( 'one, two', $item->to_array()['category'] );
	}

	/**
	 * Dates that can not be read do not break the parsing.
	 *
	 * @dataProvider data_broken_dates
	 *
	 * @param string $date The JSON-LD date.
	 */
	public function test_broken_date( $date ) {
		$item = $this->parse_jsonld( sprintf( '{"@context":"https://schema.org","@type":"Article","headline":"A post","datePublished":%1$s,"dateModified":%1$s}', $date ) );

		$this->assertSame( 'A post', $item->get_name() );
		$this->assertInstanceOf( 'DateTimeImmutable', $item->get_published() );
	}

	/**
	 * Dates that can not be read.
	 *
	 * @return array[]
	 */
	public function data_broken_dates() {
		return array(
			'not a date' => array( '"not a date"' ),
			'a list'     => array( '["2026-01-01"]' ),
		);
	}

	/**
	 * A date keeps its time zone.
	 */
	public function test_date() {
		$item = $this->parse_jsonld( '{"@context":"https://schema.org","@type":"Article","datePublished":"2026-01-01T10:00:00+02:00"}' );

		$this->assertSame( '2026-01-01T10:00:00+02:00', $item->get_published()->format( DATE_ATOM ) );
	}

	/**
	 * An author whose image is a URL instead of an object does not break the parsing.
	 */
	public function test_author_with_image_url() {
		$item = $this->parse_jsonld( '{"@context":"https://schema.org","@type":"Article","author":{"@type":"Person","name":"Jane","image":"https://www.example.org/jane.png"}}' );

		$this->assertSame( 'Jane', $item->get_author( 'name' ) );
	}
}
