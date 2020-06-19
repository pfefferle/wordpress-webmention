<?php
/**
 * Class for webmention parsing using META tags.
*/
class Webmention_Handler_Meta extends Webmention_Handler {

	/**
	 * Takes a request object and parses it.
	 *
	 * @param Webmention_Request $request Request Object.
	 * @return WP_Error|true Return error or true if successful.
	 */
	public function parse( $request ) {
		$dom   = clone $request->get_domdocument();
		$xpath = new DOMXPath( $dom );

		$meta = array();
		// Look for OGP properties
		foreach ( $xpath->query( '//meta[(@name or @property or @itemprop) and @content]' ) as $tag ) {
			$meta_name = $tag->getAttribute( 'property' );
			if ( ! $meta_name ) {
				$meta_name = $tag->getAttribute( 'name' );
			}
			if ( ! $meta_name ) {
				$meta_name = $tag->getAttribute( 'itemprop' );
			}
			$meta_value = $tag->getAttribute( 'content' );
			// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
			if ( strlen( $meta_name ) > 200 ) {
				continue;
			}
			$meta = self::add_property( $meta, $meta_name, $meta_value );
		}

		$meta['title'] = trim( $xpath->query( '//title' )->item( 0 )->textContent );
		$meta          = self::parse_ogp( $meta );
		if ( isset( $meta['og'] ) ) {
			$meta['og'] = self::parse_ogp( $meta['og'] );
		}

		$this->webmention_entity = new Webmention_Entity();
		$this->webmention_entity->set__raw( $meta );
	}

	protected function add_property( $array, $key, $value ) {
		if ( ! isset( $array[ $key ] ) ) {
			$array[ $key ] = $value;
		} elseif ( is_string( $array[ $key ] ) ) {
			$array[ $key ] = array( $array[ $key ], $value );
		} elseif ( is_array( $array[ $key ] ) ) {
			$array[ $key ][] = $value;
		}
		return $array;
	}

	/**
	 * Tries to parse OGP Meta tags by hierarchy.
	*/
	protected function parse_ogp( $meta ) {
		$return = array();
		if ( isset( $meta ) && is_array( $meta ) ) {
			foreach ( $meta as $key => $value ) {
				$name = explode( ':', $key );
				if ( 1 === count( $name ) ) {
					$name = explode( '.', $key );
				}
				if ( 1 < count( $name ) ) {
					$name = $name[0];
					$key  = str_replace( $name . ':', '', $key );
					$key  = str_replace( $name . '.', '', $key );
					if ( is_array( $value ) ) {
						$value = array_unique( $value );
						if ( 1 === count( $value ) ) {
							$value = array_shift( $value );
						}
					}
					if ( ! isset( $return[ $name ] ) ) {
						$return[ $name ] = array(
							$key => $value,
						);
					} else {
						if ( is_string( $return[ $name ] ) ) {
							$return[ $name ] = array( $return[ $name ] );
						}
						$return[ $name ][ $key ] = $value;
					}
				} else {
					$return[ $key ] = $value;
				}
			}
		}
		return $return;
	}
}
