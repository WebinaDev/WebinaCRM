<?php
/**
 * Minimal dBase III (.DBF) writer for Tamin list-disk files.
 *
 * No Composer dependency. Uses Windows-1256 for Persian text when available.
 *
 * @package WebinoDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Write DBF tables (character + numeric fields).
 */
final class WebinoCRM_Hrm_Tamin_Dbf {

	/**
	 * Encode string for DBF (Iran System / Windows-1256 preferred).
	 *
	 * @param string $text UTF-8 text.
	 * @param int    $len  Field length.
	 * @return string Binary padded.
	 */
	public static function encode_field( $text, $len ) {
		$text = (string) $text;
		if ( function_exists( 'iconv' ) ) {
			$conv = @iconv( 'UTF-8', 'Windows-1256//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $conv && '' !== $conv ) {
				$text = $conv;
			}
		}
		if ( strlen( $text ) > $len ) {
			$text = substr( $text, 0, $len );
		}
		return str_pad( $text, $len, ' ', STR_PAD_RIGHT );
	}

	/**
	 * Encode numeric for DBF.
	 *
	 * @param float|int $num Number.
	 * @param int       $len Width.
	 * @param int       $dec Decimals.
	 * @return string
	 */
	public static function encode_number( $num, $len, $dec = 0 ) {
		$dec = max( 0, (int) $dec );
		$s   = $dec > 0 ? sprintf( '%.' . $dec . 'f', (float) $num ) : (string) (int) round( (float) $num );
		if ( strlen( $s ) > $len ) {
			$s = substr( $s, 0, $len );
		}
		return str_pad( $s, $len, ' ', STR_PAD_LEFT );
	}

	/**
	 * Write a DBF file.
	 *
	 * @param string                                 $path Path.
	 * @param array<int,array{name:string,type:string,len:int,dec?:int}> $fields Field defs (type C|N).
	 * @param array<int,array<string,mixed>>         $rows Associative rows keyed by field name.
	 * @return true|WP_Error
	 */
	public static function write( $path, array $fields, array $rows ) {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'tamin_dbf_dir', __( 'Cannot create export directory.', 'webinocrm' ) );
		}

		$nfields = count( $fields );
		$rec_len = 1; // deletion flag.
		foreach ( $fields as $f ) {
			$rec_len += (int) $f['len'];
		}
		$header_len = 32 + ( $nfields * 32 ) + 1;
		$nrecords   = count( $rows );

		$fp = fopen( $path, 'wb' );
		if ( ! $fp ) {
			return new WP_Error( 'tamin_dbf_open', __( 'Cannot write DBF file.', 'webinocrm' ) );
		}

		// Header.
		$now = getdate();
		$header = '';
		$header .= chr( 0x03 ); // dBase III.
		$header .= chr( $now['year'] % 100 );
		$header .= chr( $now['mon'] );
		$header .= chr( $now['mday'] );
		$header .= pack( 'V', $nrecords );
		$header .= pack( 'v', $header_len );
		$header .= pack( 'v', $rec_len );
		$header .= str_repeat( "\0", 20 );
		fwrite( $fp, $header );

		foreach ( $fields as $f ) {
			$name = strtoupper( substr( (string) $f['name'], 0, 11 ) );
			$name = str_pad( $name, 11, "\0" );
			$type = strtoupper( (string) $f['type'] );
			$len  = (int) $f['len'];
			$dec  = (int) ( $f['dec'] ?? 0 );
			$fd   = $name . $type . str_repeat( "\0", 4 ) . chr( $len ) . chr( $dec ) . str_repeat( "\0", 14 );
			fwrite( $fp, $fd );
		}
		fwrite( $fp, chr( 0x0D ) ); // terminator.

		foreach ( $rows as $row ) {
			$rec = ' '; // not deleted
			foreach ( $fields as $f ) {
				$key = (string) $f['name'];
				$val = $row[ $key ] ?? ( $row[ strtoupper( $key ) ] ?? '' );
				if ( 'N' === strtoupper( (string) $f['type'] ) ) {
					$rec .= self::encode_number( $val, (int) $f['len'], (int) ( $f['dec'] ?? 0 ) );
				} else {
					$rec .= self::encode_field( (string) $val, (int) $f['len'] );
				}
			}
			fwrite( $fp, $rec );
		}
		fwrite( $fp, chr( 0x1A ) ); // EOF.
		fclose( $fp );
		return true;
	}
}
