<?php
/**
 * ModirPayamak SMS tariffs (Rial per part) and quote engine.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editable line-type × operator rates; billing converts Rial → Toman for wallet debit.
 */
class WebinoCRM_ModirPayamak_Tariffs {

	const OP_MCI   = 'mci';
	const OP_OTHER = 'other';

	/**
	 * @return void
	 */
	public static function ensure_table() {
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'tariffs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) === $table ) {
			self::seed_defaults();
			return;
		}
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/class-installer.php';
		WebinoCRM_Installer::create_modirpayamak_tables();
		self::seed_defaults();
	}

	/**
	 * Default tariff rows (Rial per SMS part).
	 *
	 * @return array<int,array{line_type:string,operator:string,rate_fa:float,rate_la:float,sort:int}>
	 */
	public static function default_rows() {
		$pairs = array(
			array( '1000', 1936.0, 4840.0, 2129.6, 5324.0, 10 ),
			array( '2000', 1936.0, 4840.0, 2129.6, 5324.0, 20 ),
			array( '3000', 1936.0, 4840.0, 2129.6, 5324.0, 30 ),
			array( '50001-50009', 1936.0, 4840.0, 2129.6, 5324.0, 40 ),
			array( '50004', 1936.0, 4840.0, 2129.6, 5324.0, 50 ),
			array( 'BTS', 1936.0, 4840.0, 2129.6, 5324.0, 60 ),
			array( '998', 1936.0, 4840.0, 2129.6, 5324.0, 70 ),
			array( 'voice', 2057.0, 5142.5, 2057.0, 5142.5, 80 ),
			array( '9000', 1936.0, 4840.0, 2129.6, 5324.0, 90 ),
			array( 'EVENT', 3872.0, 9680.0, 4259.2, 10648.0, 100 ),
			array( 'bale', 2613.6, 6534.0, 2613.6, 6534.0, 110 ),
			array( 'TARGETA', 3872.0, 9680.0, 3872.0, 9680.0, 120 ),
		);
		$rows = array();
		foreach ( $pairs as $p ) {
			$rows[] = array(
				'line_type' => $p[0],
				'operator'  => self::OP_MCI,
				'rate_fa'   => $p[1],
				'rate_la'   => $p[2],
				'sort'      => $p[5],
			);
			$rows[] = array(
				'line_type' => $p[0],
				'operator'  => self::OP_OTHER,
				'rate_fa'   => $p[3],
				'rate_la'   => $p[4],
				'sort'      => $p[5],
			);
		}
		return $rows;
	}

	/**
	 * Seed defaults when empty.
	 *
	 * @return void
	 */
	public static function seed_defaults() {
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'tariffs' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $table ) . "'" ) !== $table ) {
			return;
		}
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $count > 0 ) {
			return;
		}
		foreach ( self::default_rows() as $row ) {
			$wpdb->insert(
				$table,
				array(
					'line_type'  => $row['line_type'],
					'operator'   => $row['operator'],
					'rate_fa'    => $row['rate_fa'],
					'rate_la'    => $row['rate_la'],
					'sort'       => $row['sort'],
					'status'     => 'active',
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * @return float
	 */
	public static function tax_percent() {
		return max( 0, (float) WebinoCRM_Settings_Handler::get_setting( 'modirpayamak_sms_tax_percent', 10 ) );
	}

	/**
	 * @return float
	 */
	public static function surcharge_rial() {
		return max( 0, (float) WebinoCRM_Settings_Handler::get_setting( 'modirpayamak_sms_surcharge_rial', 40 ) );
	}

	/**
	 * @param bool $active_only Active only.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_all( $active_only = false ) {
		self::ensure_table();
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'tariffs' );
		$sql   = "SELECT * FROM $table";
		if ( $active_only ) {
			$sql .= " WHERE status = 'active'";
		}
		$sql .= ' ORDER BY sort ASC, line_type ASC, operator ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	/**
	 * @param string $line_type Line type.
	 * @param string $operator mci|other.
	 * @return array<string,mixed>|null
	 */
	public static function get_rate( $line_type, $operator ) {
		self::ensure_table();
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'tariffs' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE line_type = %s AND operator = %s AND status = 'active' LIMIT 1",
				sanitize_text_field( (string) $line_type ),
				self::normalize_operator( $operator )
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * @param string $operator Operator.
	 * @return string
	 */
	public static function normalize_operator( $operator ) {
		$op = sanitize_key( (string) $operator );
		return self::OP_MCI === $op ? self::OP_MCI : self::OP_OTHER;
	}

	/**
	 * MCI prefixes (Iran local 10-digit without leading 0): 910–919, 990–994.
	 *
	 * @param string $phone E.164 or local.
	 * @return string mci|other
	 */
	public static function detect_operator( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( ! is_string( $digits ) || '' === $digits ) {
			return self::OP_OTHER;
		}
		if ( 0 === strpos( $digits, '98' ) && strlen( $digits ) >= 12 ) {
			$digits = substr( $digits, 2 );
		}
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = substr( $digits, 1 );
		}
		$prefix3 = substr( $digits, 0, 3 );
		$mci     = array(
			'910', '911', '912', '913', '914', '915', '916', '917', '918', '919',
			'990', '991', '992', '993', '994',
		);
		return in_array( $prefix3, $mci, true ) ? self::OP_MCI : self::OP_OTHER;
	}

	/**
	 * Detect line type from sender number / optional edge type label.
	 *
	 * @param string $from_number From.
	 * @param string $edge_type   Optional Edge type/title.
	 * @return string
	 */
	public static function detect_line_type( $from_number, $edge_type = '' ) {
		$hint = strtoupper( trim( (string) $edge_type ) );
		$map  = array(
			'BTS'     => 'BTS',
			'EVENT'   => 'EVENT',
			'BALE'    => 'bale',
			'TARGETA' => 'TARGETA',
			'VOICE'   => 'voice',
			'پیام صوتی' => 'voice',
		);
		foreach ( $map as $needle => $type ) {
			if ( '' !== $hint && false !== strpos( $hint, (string) $needle ) ) {
				return $type;
			}
		}

		$digits = preg_replace( '/\D+/', '', (string) $from_number );
		if ( ! is_string( $digits ) || '' === $digits ) {
			return '1000';
		}
		if ( 0 === strpos( $digits, '98' ) ) {
			$digits = substr( $digits, 2 );
		}

		if ( preg_match( '/^50004/', $digits ) ) {
			return '50004';
		}
		if ( preg_match( '/^5000[1-9]/', $digits ) ) {
			return '50001-50009';
		}
		if ( 0 === strpos( $digits, '998' ) || preg_match( '/998$/', $digits ) ) {
			return '998';
		}
		if ( 0 === strpos( $digits, '9000' ) || false !== strpos( $digits, '9000' ) ) {
			return '9000';
		}
		if ( 0 === strpos( $digits, '3000' ) || preg_match( '/3000/', $digits ) ) {
			return '3000';
		}
		if ( 0 === strpos( $digits, '2000' ) || preg_match( '/2000/', $digits ) ) {
			return '2000';
		}
		if ( 0 === strpos( $digits, '1000' ) || preg_match( '/1000/', $digits ) ) {
			return '1000';
		}

		// Common shortcodes like 3000505 → 3000.
		if ( preg_match( '/^(1000|2000|3000|9000|998)/', $digits, $m ) ) {
			return $m[1];
		}

		return '1000';
	}

	/**
	 * Whether message needs UCS-2 (Farsi/Arabic).
	 *
	 * @param string $text Message.
	 * @return bool
	 */
	public static function is_farsi( $text ) {
		return (bool) preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', (string) $text );
	}

	/**
	 * GSM / UCS-2 part count (single then concatenated limits).
	 *
	 * @param string $text Message body.
	 * @return int
	 */
	public static function sms_part_count( $text ) {
		$text = (string) $text;
		$len  = function_exists( 'mb_strlen' ) ? (int) mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $len <= 0 ) {
			return 1;
		}
		if ( self::is_farsi( $text ) ) {
			if ( $len <= 70 ) {
				return 1;
			}
			return (int) ceil( $len / 67 );
		}
		if ( $len <= 160 ) {
			return 1;
		}
		return (int) ceil( $len / 153 );
	}

	/**
	 * Per-part billable Rial (rate + tax + surcharge).
	 *
	 * @param float $rate_rial Base rate.
	 * @return float
	 */
	public static function per_part_rial( $rate_rial ) {
		$rate = max( 0, (float) $rate_rial );
		$tax  = self::tax_percent();
		return ( $rate * ( 1 + ( $tax / 100 ) ) ) + self::surcharge_rial();
	}

	/**
	 * Rial → Toman for wallet.
	 *
	 * @param float $rial Rial.
	 * @return float
	 */
	public static function rial_to_toman( $rial ) {
		return round( (float) $rial / 10, 4 );
	}

	/**
	 * Quote a send for debit / estimate.
	 *
	 * @param string               $from_number From.
	 * @param string               $message     Body (pattern sends may pass empty → 1 part).
	 * @param array<int,string>    $recipients  Phones.
	 * @param string               $edge_type   Optional.
	 * @return array{cost_toman:float,cost_rial:float,parts:int,line_type:string,encoding:string,recipient_count:int,breakdown:array<int,array<string,mixed>>,note:string}
	 */
	public static function quote_send( $from_number, $message, array $recipients, $edge_type = '' ) {
		$recipients = array_values(
			array_filter(
				array_map(
					static function ( $r ) {
						return trim( (string) $r );
					},
					$recipients
				)
			)
		);
		if ( ! $recipients ) {
			$recipients = array( '' );
		}

		$line_type = self::detect_line_type( $from_number, $edge_type );
		$parts     = max( 1, self::sms_part_count( $message ) );
		$encoding  = self::is_farsi( $message ) ? 'fa' : 'la';
		$total_rial = 0.0;
		$breakdown  = array();

		foreach ( $recipients as $phone ) {
			$op   = '' !== $phone ? self::detect_operator( $phone ) : self::OP_OTHER;
			$row  = self::get_rate( $line_type, $op );
			$rate = 0.0;
			if ( $row ) {
				$rate = 'fa' === $encoding ? (float) $row['rate_fa'] : (float) $row['rate_la'];
			} else {
				// Fallback: flat Toman setting converted to Rial-equivalent unit.
				$rate = WebinoCRM_ModirPayamak_Manager::price_per_unit() * 10;
			}
			$per   = self::per_part_rial( $rate );
			$cost  = $per * $parts;
			$total_rial += $cost;
			$breakdown[] = array(
				'phone'        => $phone,
				'operator'     => $op,
				'rate_rial'    => $rate,
				'per_part'     => $per,
				'parts'        => $parts,
				'cost_rial'    => $cost,
				'cost_toman'   => self::rial_to_toman( $cost ),
			);
		}

		$cost_toman = self::rial_to_toman( $total_rial );
		$note       = sprintf(
			/* translators: 1: parts 2: recipient count 3: line type */
			__( 'SMS send: %1$d parts × %2$d recipient(s) [%3$s]', 'webinocrm' ),
			$parts,
			count( $recipients ),
			$line_type
		);

		return array(
			'cost_toman'       => $cost_toman,
			'cost_rial'        => $total_rial,
			'parts'            => $parts,
			'line_type'        => $line_type,
			'encoding'         => $encoding,
			'recipient_count'  => count( $recipients ),
			'breakdown'        => $breakdown,
			'note'             => $note,
			'tax_percent'      => self::tax_percent(),
			'surcharge_rial'   => self::surcharge_rial(),
		);
	}

	/**
	 * Package helper: amount in Toman from tariff + part count.
	 *
	 * @param int    $tariff_id Tariff row id.
	 * @param int    $parts     Parts.
	 * @param string $encoding  fa|la.
	 * @return float|WP_Error
	 */
	public static function package_amount_from_tariff( $tariff_id, $parts = 1, $encoding = 'fa' ) {
		self::ensure_table();
		global $wpdb;
		$table = WebinoCRM_ModirPayamak_Manager::table( 'tariffs' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", (int) $tariff_id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Tariff not found.', 'webinocrm' ) );
		}
		$parts = max( 1, (int) $parts );
		$rate  = 'la' === $encoding ? (float) $row['rate_la'] : (float) $row['rate_fa'];
		return self::rial_to_toman( self::per_part_rial( $rate ) * $parts );
	}

	/**
	 * Upsert tariff row.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int|WP_Error
	 */
	public static function save( array $data ) {
		self::ensure_table();
		global $wpdb;
		$table     = WebinoCRM_ModirPayamak_Manager::table( 'tariffs' );
		$id        = (int) ( $data['id'] ?? 0 );
		$line_type = sanitize_text_field( (string) ( $data['line_type'] ?? '' ) );
		$operator  = self::normalize_operator( $data['operator'] ?? self::OP_OTHER );
		$rate_fa   = (float) ( $data['rate_fa'] ?? 0 );
		$rate_la   = (float) ( $data['rate_la'] ?? 0 );
		$sort      = (int) ( $data['sort'] ?? 0 );
		$status    = sanitize_key( (string) ( $data['status'] ?? 'active' ) );
		if ( '' === $line_type ) {
			return new WP_Error( 'invalid', __( 'Line type is required.', 'webinocrm' ) );
		}
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'active';
		}
		$row = array(
			'line_type'  => $line_type,
			'operator'   => $operator,
			'rate_fa'    => $rate_fa,
			'rate_la'    => $rate_la,
			'sort'       => $sort,
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( $id > 0 ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) );
			return $id;
		}
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id Id.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		self::ensure_table();
		$id = (int) $id;
		if ( $id <= 0 ) {
			return new WP_Error( 'invalid', __( 'Invalid id.', 'webinocrm' ) );
		}
		global $wpdb;
		$wpdb->delete( WebinoCRM_ModirPayamak_Manager::table( 'tariffs' ), array( 'id' => $id ) );
		return true;
	}
}
