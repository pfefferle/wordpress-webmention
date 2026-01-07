<?php
/**
 * Test Autoloader class.
 *
 * @package Webmention
 */

/**
 * Test Autoloader class.
 */
class Test_Autoloader extends WP_UnitTestCase {

	/**
	 * Test that Handler classes are autoloaded correctly.
	 *
	 * @dataProvider handler_class_provider
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public function test_handler_classes_are_autoloaded( $class_name ) {
		$this->assertTrue(
			class_exists( $class_name ),
			sprintf( 'Class %s should be autoloaded', $class_name )
		);
	}

	/**
	 * Test that concrete Handler classes can be instantiated.
	 *
	 * @dataProvider concrete_handler_class_provider
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public function test_handler_classes_can_be_instantiated( $class_name ) {
		$instance = new $class_name();

		$this->assertInstanceOf(
			$class_name,
			$instance,
			sprintf( 'Should be able to instantiate %s', $class_name )
		);

		$this->assertInstanceOf(
			\Webmention\Handler\Base::class,
			$instance,
			sprintf( '%s should extend Base handler', $class_name )
		);
	}

	/**
	 * Test that the main Handler class can instantiate all handlers via autoloader.
	 */
	public function test_handler_instantiates_all_handlers() {
		$handler = new \Webmention\Handler();

		$this->assertInstanceOf(
			\Webmention\Handler::class,
			$handler,
			'Handler class should be instantiated successfully'
		);
	}

	/**
	 * Test autoloader resolves namespaced paths correctly.
	 */
	public function test_autoloader_resolves_handler_namespace() {
		$autoloader = new \Webmention\Autoloader( 'Webmention', WEBMENTION_PLUGIN_DIR . 'includes' );

		$reflection = new ReflectionClass( $autoloader );
		$method     = $reflection->getMethod( 'load' );
		$method->setAccessible( true );

		// Test that loading a Handler class doesn't throw an error
		// (the class should already be loaded, but this verifies the path resolution)
		$method->invoke( $autoloader, 'Webmention\Handler\MF2' );

		$this->assertTrue(
			class_exists( 'Webmention\Handler\MF2' ),
			'MF2 handler should exist after autoloader load attempt'
		);
	}

	/**
	 * Test Entity classes are also autoloaded (used by Handler).
	 */
	public function test_entity_classes_are_autoloaded() {
		$this->assertTrue(
			class_exists( \Webmention\Entity\Item::class ),
			'Entity\Item class should be autoloaded'
		);

		$item = new \Webmention\Entity\Item();
		$this->assertInstanceOf(
			\Webmention\Entity\Item::class,
			$item,
			'Should be able to instantiate Entity\Item'
		);
	}

	/**
	 * Test Response class is autoloaded (used by Handler).
	 */
	public function test_response_class_is_autoloaded() {
		$this->assertTrue(
			class_exists( \Webmention\Response::class ),
			'Response class should be autoloaded'
		);
	}

	/**
	 * Data provider for all Handler classes (including abstract).
	 *
	 * @return array Array of handler class names.
	 */
	public function handler_class_provider() {
		return array(
			'MF2 Handler'    => array( \Webmention\Handler\MF2::class ),
			'WP Handler'     => array( \Webmention\Handler\WP::class ),
			'Meta Handler'   => array( \Webmention\Handler\Meta::class ),
			'Jsonld Handler' => array( \Webmention\Handler\Jsonld::class ),
			'Base Handler'   => array( \Webmention\Handler\Base::class ),
		);
	}

	/**
	 * Data provider for concrete Handler classes only.
	 *
	 * @return array Array of handler class names.
	 */
	public function concrete_handler_class_provider() {
		return array(
			'MF2 Handler'    => array( \Webmention\Handler\MF2::class ),
			'WP Handler'     => array( \Webmention\Handler\WP::class ),
			'Meta Handler'   => array( \Webmention\Handler\Meta::class ),
			'Jsonld Handler' => array( \Webmention\Handler\Jsonld::class ),
		);
	}
}
