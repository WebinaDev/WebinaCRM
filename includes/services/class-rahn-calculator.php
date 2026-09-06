<?php
/**
 * Rahn-percent pricing calculator (contract lock + monthly billing).
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure formula engine for رهن‌درصد (see فرمول-رهن-درصد.md).
 */
class WebinoCRM_Rahn_Calculator {

	/**
	 * Map a catalog item to monthly U_i / M_i components.
	 *
	 * @param array<string,mixed> $item Catalog row.
	 * @return array{U: float, M: float}
	 */
	public static function item_to_um( array $item ) {
		$amount   = max( 0.0, (float) ( $item['amount'] ?? 0 ) );
		$billing  = sanitize_key( (string) ( $item['billing'] ?? 'once' ) );
		$period   = max( 1, (int) ( $item['period_months'] ?? 1 ) );
		$renewable = ! empty( $item['renewable'] );

		$U = 0.0;
		$M = 0.0;

		switch ( $billing ) {
			case 'monthly':
				$M = $amount;
				break;
			case 'yearly':
				$M = $amount / 12.0;
				break;
			case 'custom':
				if ( $renewable ) {
					$M = $amount / (float) $period;
				} else {
					$U = $amount;
				}
				break;
			case 'once':
			default:
				$U = $amount;
				break;
		}

		return array(
			'U' => $U,
			'M' => $M,
		);
	}

	/**
	 * Monthly internal cost C from selected catalog items.
	 *
	 * @param array<int,array<string,mixed>> $items Selected items (active a_i=1).
	 * @param int                            $T     Contract duration in months.
	 * @return array{C: float, breakdown: array<int,array<string,mixed>>}
	 */
	public static function compute_cost( array $items, $T ) {
		$T = max( 1, (int) $T );
		$C = 0.0;
		$breakdown = array();

		foreach ( $items as $item ) {
			$um    = self::item_to_um( $item );
			$share = $um['M'] + ( $um['U'] / (float) $T );
			$C    += $share;
			$breakdown[] = array(
				'id'           => (string) ( $item['id'] ?? '' ),
				'name'         => (string) ( $item['name'] ?? '' ),
				'billing'      => (string) ( $item['billing'] ?? 'once' ),
				'period_months'=> (int) ( $item['period_months'] ?? 1 ),
				'renewable'    => ! empty( $item['renewable'] ),
				'amount'       => (float) ( $item['amount'] ?? 0 ),
				'U'            => $um['U'],
				'M'            => $um['M'],
				'monthly_share'=> $share,
			);
		}

		return array(
			'C'          => $C,
			'breakdown'  => $breakdown,
		);
	}

	/**
	 * Layer 1 — lock F and p for a contract session.
	 *
	 * @param array<string,mixed> $args {
	 *   items, T, m, k, s_hat, p_min, p_max,
	 *   mode: 'from_p'|'from_f',
	 *   p_wanted?: float (0-1), F_wanted?: float
	 * }
	 * @return array<string,mixed>
	 */
	public static function lock_contract( array $args ) {
		$items  = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
		$T      = max( 1, (int) ( $args['T'] ?? 6 ) );
		$m      = max( 0.0, (float) ( $args['m'] ?? 0.60 ) );
		$k      = max( 0.0, min( 1.0, (float) ( $args['k'] ?? 0.70 ) ) );
		$s_hat  = max( 0.0, (float) ( $args['s_hat'] ?? 0 ) );
		$p_min  = max( 0.0, (float) ( $args['p_min'] ?? 0 ) );
		$p_max  = max( $p_min, (float) ( $args['p_max'] ?? 1 ) );
		$mode   = sanitize_key( (string) ( $args['mode'] ?? 'from_p' ) );

		$cost = self::compute_cost( $items, $T );
		$C    = (float) $cost['C'];
		$V_star = $C * ( 1.0 + $m );
		$F_min  = $k * $C;
		$alpha  = $s_hat > 0 ? ( $s_hat / 100.0 ) : 0.0;

		$p = 0.0;
		$F = 0.0;

		if ( 'from_f' === $mode ) {
			$F = max( 0.0, (float) ( $args['F_wanted'] ?? 0 ) );
			if ( $s_hat > 0 ) {
				$p = self::clip( ( $V_star - $F ) / $s_hat, $p_min, $p_max );
			} else {
				$p = self::clip( (float) ( $args['p_wanted'] ?? $p_min ), $p_min, $p_max );
			}
			$F = max( $F, $F_min );
			// Re-derive p after floor so V* target stays consistent when possible.
			if ( $s_hat > 0 ) {
				$p = self::clip( ( $V_star - $F ) / $s_hat, $p_min, $p_max );
			}
		} else {
			$p_wanted = (float) ( $args['p_wanted'] ?? $p_min );
			$p        = self::clip( $p_wanted, $p_min, $p_max );
			$F        = max( $F_min, $V_star - ( $p * $s_hat ) );
		}

		$V_hat = $F + ( $p * $s_hat );
		$S_BE  = self::breakeven( $C, $F, $p );

		return array(
			'C'          => $C,
			'V_star'     => $V_star,
			'F_min'      => $F_min,
			'alpha'      => $alpha,
			'F'          => $F,
			'p'          => $p,
			'p_percent'  => $p * 100.0,
			'V_hat'      => $V_hat,
			'S_BE'       => $S_BE,
			'S_hat'      => $s_hat,
			'T'          => $T,
			'm'          => $m,
			'k'          => $k,
			'p_min'      => $p_min,
			'p_max'      => $p_max,
			'breakdown'  => $cost['breakdown'],
			'mode'       => $mode,
		);
	}

	/**
	 * Layer 2 — monthly invoice amount.
	 *
	 * @param float               $F Locked fixed.
	 * @param float               $p Locked percent (0-1).
	 * @param array<string,mixed> $sales { G, R, D, X }.
	 * @param float               $C Optional internal cost for profit display.
	 * @return array<string,mixed>
	 */
	public static function monthly_bill( $F, $p, array $sales, $C = null ) {
		$G = max( 0.0, (float) ( $sales['G'] ?? 0 ) );
		$R = max( 0.0, (float) ( $sales['R'] ?? 0 ) );
		$D = max( 0.0, (float) ( $sales['D'] ?? 0 ) );
		$X = max( 0.0, (float) ( $sales['X'] ?? 0 ) );
		$S = max( 0.0, $G - $R - $D - $X );
		$F = max( 0.0, (float) $F );
		$p = max( 0.0, (float) $p );
		$V = $F + ( $p * $S );

		$out = array(
			'G'     => $G,
			'R'     => $R,
			'D'     => $D,
			'X'     => $X,
			'S'     => $S,
			'F'     => $F,
			'p'     => $p,
			'p_share' => $p * $S,
			'V'     => $V,
		);

		if ( null !== $C ) {
			$out['C']  = (float) $C;
			$out['Pi'] = $V - (float) $C;
		}

		return $out;
	}

	/**
	 * Render contract clause from template.
	 *
	 * @param string              $template Template with merge fields.
	 * @param array<string,mixed> $vars     Merge values.
	 * @return string
	 */
	public static function render_clause( $template, array $vars ) {
		$replacements = array(
			'{F}'      => self::format_money( (float) ( $vars['F'] ?? 0 ) ),
			'{p}'      => self::format_percent( (float) ( $vars['p'] ?? 0 ) ),
			'{S_hat}'  => self::format_money( (float) ( $vars['S_hat'] ?? 0 ) ),
			'{T}'      => (string) (int) ( $vars['T'] ?? 0 ),
			'{alpha}'  => self::format_money( (float) ( $vars['alpha'] ?? 0 ) ),
			'{start}'  => (string) ( $vars['start'] ?? '' ),
			'{review}' => (string) ( $vars['review'] ?? '' ),
		);
		return strtr( (string) $template, $replacements );
	}

	/**
	 * @param float $value Ratio 0-1.
	 * @param float $min   Min.
	 * @param float $max   Max.
	 * @return float
	 */
	public static function clip( $value, $min, $max ) {
		return max( (float) $min, min( (float) $max, (float) $value ) );
	}

	/**
	 * @param float $C Cost.
	 * @param float $F Fixed.
	 * @param float $p Percent 0-1.
	 * @return float|null Null = undefined / always loss.
	 */
	public static function breakeven( $C, $F, $p ) {
		$C = (float) $C;
		$F = (float) $F;
		$p = (float) $p;
		if ( $p <= 0 ) {
			return $F >= $C ? 0.0 : null;
		}
		return max( 0.0, ( $C - $F ) / $p );
	}

	/**
	 * @param float $n Amount.
	 * @return string
	 */
	public static function format_money( $n ) {
		$n = (float) $n;
		if ( class_exists( 'WebinoCRM_Number_Formatter' ) ) {
			return WebinoCRM_Number_Formatter::format_with_separator( $n );
		}
		return number_format( $n, 0, '.', ',' );
	}

	/**
	 * @param float $p Ratio 0-1.
	 * @return string
	 */
	public static function format_percent( $p ) {
		$pct = (float) $p * 100.0;
		$rounded = round( $pct, 2 );
		if ( abs( $rounded - round( $rounded ) ) < 0.001 ) {
			return (string) (int) round( $rounded );
		}
		return rtrim( rtrim( number_format( $rounded, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Public-safe subset of lock result (no internal cost/margins).
	 *
	 * @param array<string,mixed> $lock Full lock payload.
	 * @param array<int,array<string,mixed>> $items Selected items for names only.
	 * @param string              $clause Rendered clause.
	 * @return array<string,mixed>
	 */
	public static function public_payload( array $lock, array $items, $clause ) {
		$services = array();
		foreach ( $items as $item ) {
			$services[] = array(
				'id'             => (string) ( $item['id'] ?? '' ),
				'name'           => (string) ( $item['name'] ?? '' ),
				'billing'        => (string) ( $item['billing'] ?? 'once' ),
				'period_months'  => (int) ( $item['period_months'] ?? 1 ),
				'renewable'      => ! empty( $item['renewable'] ),
				'description'    => (string) ( $item['description'] ?? '' ),
			);
		}

		return array(
			'F'          => (float) ( $lock['F'] ?? 0 ),
			'p'          => (float) ( $lock['p'] ?? 0 ),
			'p_percent'  => (float) ( $lock['p_percent'] ?? 0 ),
			'V_hat'      => (float) ( $lock['V_hat'] ?? 0 ),
			'S_hat'      => (float) ( $lock['S_hat'] ?? 0 ),
			'alpha'      => (float) ( $lock['alpha'] ?? 0 ),
			'T'          => (int) ( $lock['T'] ?? 0 ),
			'p_min'      => (float) ( $lock['p_min'] ?? 0 ),
			'p_max'      => (float) ( $lock['p_max'] ?? 1 ),
			'F_min'      => (float) ( $lock['F_min'] ?? 0 ),
			'services'   => $services,
			'clause'     => (string) $clause,
		);
	}
}
