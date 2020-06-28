<?php
/**
 * Class for webmention parsing using Microformats 2.
*/
class Webmention_Handler_MF2 extends Webmention_Handler_Base {

	/**
	 * Handler Slug to Uniquely Identify it.
	 *
	 * @var string
	 */
	protected $slug = 'mf2';

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @param Webmention_Item $item A Parsed Item. If null, a new one will be created.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( $request, $item = null ) {
		if ( $item instanceof Webmention_Item ) {
			$this->webmention_item = $item;
		}
		$dom = clone $request->get_domdocument();
		if ( ! class_exists( '\Webmention\Mf2\Parser' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'libraries/mf2/Mf2/Parser.php';
		}
		$parser   = new Webmention\Mf2\Parser( $domdocument, $url );
		$mf_array = $parser->parse();

		// Set raw data.
		$this->webmention_item->set__raw( $mf_array );
	}


}
