<?php

if ( ! function_exists( 'mb_convert_encoding' ) ) {
	/**
	 * @link https://github.com/symfony/polyfill-mbstring
	 *
	 * @since 3.7.1
	 */
	function mb_convert_encoding( $s, $toEncoding, $fromEncoding = null ) {
		if ( is_array( $fromEncoding ) || strpos( $fromEncoding, ',' ) !== false ) {
			$fromEncoding = mb_detect_encoding( $s, $fromEncoding );
		} else {
			$fromEncoding = mb_validate_encoding( $fromEncoding );
		}

		$toEncoding = mb_validate_encoding( $toEncoding );

		if ( $fromEncoding == 'BASE64' ) {
			$s            = base64_decode( $s );
			$fromEncoding = $toEncoding;
		}

		if ( $toEncoding == 'BASE64' ) {
			return base64_encode( $s );
		}

		if ( $toEncoding == 'HTML-ENTITIES' || $toEncoding == 'HTML' ) {
			if ( $fromEncoding == 'HTML-ENTITIES' || $fromEncoding == 'HTML' ) {
				$fromEncoding = 'Windows-1252';
			}

			if ( $fromEncoding != 'UTF-8' ) {
				$s = iconv( $fromEncoding, 'UTF-8//IGNORE', $s );
			}

			return preg_replace_callback( '/[\x80-\xFF]+/', 'mb_convert_encoding_callback', $s );
		}

		if ( $fromEncoding == 'HTML-ENTITIES' ) {
			$s            = html_entity_decode( $s, ENT_COMPAT, 'UTF-8' );
			$fromEncoding = 'UTF-8';
		}

		return iconv( $fromEncoding, $toEncoding . '//IGNORE', $s );
	}
}

if ( ! function_exists( 'mb_convert_encoding_callback' ) ) {
	/**
	 * @link https://github.com/symfony/polyfill-mbstring
	 *
	 * @since 3.7.1
	 */
	function mb_convert_encoding_callback( $m ) {
		$i        = 1;
		$entities = '';
		$m        = unpack( 'C*', htmlentities( $m[0], ENT_COMPAT, 'UTF-8' ) );

		while ( isset( $m[ $i ] ) ) {
			if ( $m[ $i ] < 0x80 ) {
				$entities .= chr( $m[ $i++ ] );
				continue;
			}

			if ( $m[ $i ] >= 0xF0 ) {
				$c = ( ( $m[ $i++ ] - 0xF0 ) << 18 ) + ( ( $m[ $i++ ] - 0x80 ) << 12 ) + ( ( $m[ $i++ ] - 0x80 ) << 6 ) + $m[ $i++ ] - 0x80;
			} elseif ( $m[ $i ] >= 0xE0 ) {
				$c = ( ( $m[ $i++ ] - 0xE0 ) << 12 ) + ( ( $m[ $i++ ] - 0x80 ) << 6 ) + $m[ $i++ ] - 0x80;
			} else {
				$c = ( ( $m[ $i++ ] - 0xC0 ) << 6 ) + $m[ $i++ ] - 0x80;
			}

			$entities .= '&#' . $c . ';';
		}

		return $entities;
	}
}

if ( ! function_exists( 'mb_detect_encoding' ) ) {
	/**
	 * @link https://github.com/symfony/polyfill-mbstring
	 *
	 * @since 3.7.1
	 */
	function mb_detect_encoding( $s, $encodings = null, $strict = false ) {
		if ( is_null( $encodings ) ) {
			$encodings = array( 'ASCII', 'UTF-8' );
		} else {
			if ( ! is_array( $encodings ) ) {
				$encodings = array_map( 'trim', explode( ',', $encodings ) );
			}

			$encodings = array_map( 'strtoupper', $encodings );
		}

		foreach ( $encodings as $encoding ) {
			switch ( $encoding ) {
				case 'ASCII':
					if ( ! preg_match( '/[\x80-\xFF]/', $s ) ) {
						return $encoding;
					}
					break;

				case 'UTF8':
				case 'UTF-8':
					if ( preg_match( '//u', $s ) ) {
						return 'UTF-8';
					}
					break;

				default:
					if ( strncmp( $encoding, 'ISO-8859-', 9 ) == 0 ) {
						return $encoding;
					}
					break;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'mb_encode_numericentity' ) ) {
	/**
	 * @link https://github.com/symfony/polyfill-mbstring
	 *
	 * @since 6.x.x
	 */
	function mb_encode_numericentity($s, $convmap, $encoding = null, $is_hex = false)
	{
		if ( $s !== null && ! is_scalar( $s ) && ! ( is_object( $s ) && method_exists( $s, '__toString' ) ) ) {
			trigger_error( 'mb_encode_numericentity() expects parameter 1 to be string, ' . gettype( $s ) . ' given', E_USER_WARNING );

			return null;
		}

		if ( ! is_array( $convmap ) || ( PHP_VERSION_ID < 80000 && ! $convmap ) ) {
			return false;
		}

		if ( $encoding !== null && ! is_scalar( $encoding ) ) {
			trigger_error( 'mb_encode_numericentity() expects parameter 3 to be string, ' . gettype( $s ) . ' given', E_USER_WARNING );

			return null;  // Instead of '' (cf. mb_decode_numericentity)
		}

		if ( $is_hex !== null && ! is_scalar( $is_hex ) ) {
			trigger_error( 'mb_encode_numericentity() expects parameter 4 to be boolean, ' . gettype( $s ) . ' given', E_USER_WARNING );

			return null;
		}

		$s = (string) $s;

		if ( $s === '' ) {
			return '';
		}

		$encoding = mb_validate_encoding( $encoding );

		if ( $encoding === 'UTF-8' ) {
			$encoding = null;
			if ( ! preg_match( '//u', $s ) ) {
				$s = @iconv( 'UTF-8', 'UTF-8//IGNORE', $s );
			}
		} else {
			$s = iconv( $encoding, 'UTF-8//IGNORE', $s );
		}

		static $ulenMask = array( "\xC0" => 2, "\xD0" => 2, "\xE0" => 3, "\xF0" => 4 );

		$cnt = floor( count( $convmap ) / 4 ) * 4;
		$i = 0;
		$len = strlen( $s );
		$result = '';

		while ( $i < $len ) {
			$ulen = $s[ $i ] < "\x80" ? 1 : $ulenMask[ $s[ $i ] & "\xF0" ];
			$uchr = substr( $s, $i, $ulen );
			$i += $ulen;
			$c = mb_ord( $uchr );

			for ( $j = 0; $j < $cnt; $j += 4 ) {
				if ( $c >= $convmap[ $j ] && $c <= $convmap[ $j + 1 ] ) {
					$cOffset = ( $c + $convmap[ $j + 2 ] ) & $convmap[ $j + 3 ];
					$result .= $is_hex ? sprintf( '&#x%X;', $cOffset ) : '&#' . $cOffset . ';';
					continue 2;
				}
			}

			$result .= $uchr;
		}

		if ( $encoding === null ) {
			return $result;
		}

		return iconv( 'UTF-8', $encoding . '//IGNORE', $result );
	}
}

if ( ! function_exists( 'mb_ord' ) ) {
	/**
	 * @link https://github.com/symfony/polyfill-mbstring
	 *
	 * @since 6.x.x
	 */
	function mb_ord( $s, $encoding = null )	{
		$encoding = mb_validate_encoding( $encoding );

		if ( $encoding !== 'UTF-8' ) {
			$s = mb_convert_encoding( $s, 'UTF-8', $encoding );
		}

		if ( strlen( $s ) === 1 ) {
			return ord( $s );
		}

		$code = ( $s = unpack( 'C*', substr( $s, 0, 4 ) ) ) ? $s[1] : 0;

		if ( $code >= 0xF0 ) {
			return ( ( $code - 0xF0 ) << 18 ) + ( ( $s[2] - 0x80 ) << 12 ) + ( ( $s[3] - 0x80 ) << 6 ) + $s[4] - 0x80;
		} elseif ( $code >= 0xE0 ) {
			return ( ( $code - 0xE0 ) << 12 ) + ( ( $s[2] - 0x80 ) << 6 ) + $s[3] - 0x80;
		} elseif ( $code >= 0xC0 ) {
			return ( ( $code - 0xC0 ) << 6 ) + $s[2] - 0x80;
		}

		return $code;
	}
}

if ( ! function_exists( 'mb_validate_encoding' ) ) {
	/**
	 * @link https://github.com/symfony/polyfill-mbstring
	 *
	 * @since 3.7.1
	 */
	function mb_validate_encoding( $encoding ) {
		if ( is_null( $encoding ) ) {
			return 'UTF-8';
		}

		$encoding = strtoupper( $encoding );

		if ( $encoding == '8BIT' || $encoding == 'BINARY' ) {
			$encoding = 'CP850';
		} elseif ( $encoding == 'UTF8' ) {
			$encoding = 'UTF-8';
		}

		return $encoding;
	}
}
