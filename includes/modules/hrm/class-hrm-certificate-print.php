<?php
/**
 * HRM certificate print templates.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinoCRM_Hrm_Certificate_Print {

	/**
	 * @param string              $type    certificate_employment|certificate_deduction.
	 * @param int                 $user_id User id.
	 * @param array<string,mixed> $payload Extra fields.
	 * @return string HTML.
	 */
	public static function render( $type, $user_id, array $payload = array() ) {
		require_once WEBINOCRM_PLUGIN_DIR . 'includes/modules/hrm/class-hrm-payroll-calculator.php';
		$user = get_userdata( (int) $user_id );
		$emp  = WebinoCRM_Hrm_Payroll_Calculator::employee_snapshot( (int) $user_id );
		$name = $user ? $user->display_name : '';
		$date = function_exists( 'webino_gregorian_to_jalali' )
			? webino_gregorian_to_jalali( current_time( 'Y-m-d' ) )
			: current_time( 'Y-m-d' );

		$title = 'certificate_deduction' === $type
			? __( 'گواهی کسر از حقوق', 'webinocrm' )
			: __( 'گواهی اشتغال به کار', 'webinocrm' );

		$body = '';
		if ( 'certificate_employment' === $type ) {
			$body = sprintf(
				/* translators: 1: name, 2: national id, 3: job title */
				__( 'بدین‌وسیله گواهی می‌شود آقا/خانم %1$s به شماره ملی %2$s در سمت %3$s مشغول به کار می‌باشد.', 'webinocrm' ),
				esc_html( $name ),
				esc_html( $emp['national_id'] ?? '' ),
				esc_html( (string) ( $payload['job_title'] ?? $emp['job_title'] ?? '' ) )
			);
		} else {
			$amount = isset( $payload['amount'] ) ? number_format_i18n( (float) $payload['amount'] ) : '—';
			$body   = sprintf(
				/* translators: 1: name, 2: amount */
				__( 'بدین‌وسیله گواهی می‌شود از حقوق آقا/خانم %1$s مبلغ %2$s ریال کسر خواهد شد.', 'webinocrm' ),
				esc_html( $name ),
				esc_html( $amount )
			);
		}

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="fa" dir="rtl">
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				body { font-family: Tahoma, Arial, sans-serif; padding: 40px; direction: rtl; }
				h1 { text-align: center; font-size: 20px; }
				.content { margin: 40px 0; line-height: 2; font-size: 14px; }
				.footer { margin-top: 60px; text-align: left; font-size: 12px; color: #555; }
			</style>
		</head>
		<body>
			<h1><?php echo esc_html( $title ); ?></h1>
			<div class="content"><?php echo wp_kses_post( $body ); ?></div>
			<div class="footer"><?php echo esc_html( $date ); ?></div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}
}
