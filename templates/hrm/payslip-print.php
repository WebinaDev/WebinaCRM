<?php
/**
 * Iranian payslip print template (A4 RTL).
 *
 * @var array $slip
 * @var array $emp
 * @var array $run
 * @var array|null $wh
 * @var array|null $decree
 * @var array $items
 * @var array $meta
 * @var array $company
 * @package WebinoDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$jy = (int) ( $run['jalali_year'] ?? 0 );
$jm = (int) ( $run['jalali_month'] ?? 0 );
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8"/>
	<title><?php echo esc_html( sprintf( /* translators: %s employee */ __( 'Payslip — %s', 'webino-dashboard' ), $emp['name'] ?? '' ) ); ?></title>
	<style>
		@page { size: A4; margin: 12mm; }
		* { box-sizing: border-box; }
		body { font-family: Tahoma, "Segoe UI", sans-serif; font-size: 12px; color: #111; margin: 0; background: #fff; }
		.sheet { max-width: 190mm; margin: 0 auto; }
		h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
		.sub { text-align: center; color: #444; margin-bottom: 12px; }
		.meta, .grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
		.meta td, .grid th, .grid td { border: 1px solid #333; padding: 5px 7px; }
		.grid th { background: #f0f0f0; font-weight: 600; }
		.num { text-align: left; font-variant-numeric: tabular-nums; direction: ltr; }
		.sign { display: flex; justify-content: space-between; margin-top: 28px; }
		.sign div { width: 30%; text-align: center; border-top: 1px solid #333; padding-top: 6px; }
		@media print {
			.no-print { display: none !important; }
			body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		}
		.no-print { text-align: center; margin: 12px; }
		.no-print button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
	</style>
</head>
<body onload="window.print()">
<div class="no-print"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'webino-dashboard' ); ?></button></div>
<div class="sheet">
	<h1><?php echo esc_html( $company['company_name'] ?: __( 'Payslip', 'webino-dashboard' ) ); ?></h1>
	<div class="sub">
		<?php esc_html_e( 'فیش حقوق و دستمزد', 'webino-dashboard' ); ?>
		— <?php echo esc_html( $jy . '/' . str_pad( (string) $jm, 2, '0', STR_PAD_LEFT ) ); ?>
		<?php if ( $wh ) : ?>
			— <?php echo esc_html( $wh['name'] . ' (' . $wh['code'] . ')' ); ?>
		<?php endif; ?>
	</div>
	<table class="meta">
		<tr>
			<td><?php esc_html_e( 'نام', 'webino-dashboard' ); ?>: <?php echo esc_html( $emp['name'] ?? '' ); ?></td>
			<td><?php esc_html_e( 'کد ملی', 'webino-dashboard' ); ?>: <?php echo esc_html( $emp['national_id'] ?? '' ); ?></td>
			<td><?php esc_html_e( 'شماره بیمه', 'webino-dashboard' ); ?>: <?php echo esc_html( $emp['insurance_no'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td><?php esc_html_e( 'شغل', 'webino-dashboard' ); ?>: <?php echo esc_html( ( $emp['job_title'] ?? '' ) . ' / ' . ( $emp['job_code'] ?? '' ) ); ?></td>
			<td><?php esc_html_e( 'روز کارکرد', 'webino-dashboard' ); ?>: <?php echo esc_html( (string) $slip['days_worked'] ); ?></td>
			<td><?php esc_html_e( 'حکم', 'webino-dashboard' ); ?>: <?php echo esc_html( $decree['decree_no'] ?? '—' ); ?></td>
		</tr>
	</table>
	<table class="grid">
		<thead>
			<tr>
				<th><?php esc_html_e( 'شرح', 'webino-dashboard' ); ?></th>
				<th class="num"><?php esc_html_e( 'مبلغ', 'webino-dashboard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$labels = array(
				'base_pay'         => 'حقوق پایه / مزد',
				'overtime'         => 'اضافه‌کاری',
				'overtime_night'   => 'اضافه‌کار شب',
				'overtime_holiday' => 'اضافه‌کار تعطیل',
				'volume'           => 'کارکرد حجمی',
				'food'             => 'بن خواربار',
				'housing'          => 'حق مسکن',
				'child'            => 'حق اولاد',
				'marriage'         => 'حق تأهل',
				'seniority'        => 'پایه سنوات',
				'transport'        => 'ایاب و ذهاب',
				'other_ins'        => 'سایر مشمول بیمه',
				'other_non'        => 'سایر غیرمشمول',
			);
			foreach ( $labels as $key => $label ) :
				$val = (float) ( $items[ $key ] ?? 0 );
				if ( $val <= 0 ) {
					continue;
				}
				?>
			<tr>
				<td><?php echo esc_html( $label ); ?></td>
				<td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $val ) ); ?></td>
			</tr>
			<?php endforeach; ?>
			<tr>
				<td><strong><?php esc_html_e( 'ناخالص', 'webino-dashboard' ); ?></strong></td>
				<td class="num"><strong><?php echo esc_html( Accounting_Payroll_Print::money( $slip['gross'] ) ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'مشمول بیمه (سقف‌خورده)', 'webino-dashboard' ); ?></td>
				<td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $slip['insurable_capped'] ) ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'بیمه سهم کارگر ۷٪', 'webino-dashboard' ); ?></td>
				<td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $slip['employee_insurance'] ) ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'مالیات حقوق', 'webino-dashboard' ); ?></td>
				<td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $slip['tax'] ) ); ?></td>
			</tr>
			<?php if ( (float) ( $slip['loan_deduction'] ?? 0 ) > 0 ) : ?>
			<tr>
				<td><?php esc_html_e( 'کسور وام', 'webino-dashboard' ); ?></td>
				<td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $slip['loan_deduction'] ) ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( (float) ( $slip['advance_deduction'] ?? 0 ) > 0 ) : ?>
			<tr>
				<td><?php esc_html_e( 'کسور مساعده', 'webino-dashboard' ); ?></td>
				<td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $slip['advance_deduction'] ) ); ?></td>
			</tr>
			<?php endif; ?>
			<tr>
				<td><strong><?php esc_html_e( 'خالص پرداختی', 'webino-dashboard' ); ?></strong></td>
				<td class="num"><strong><?php echo esc_html( Accounting_Payroll_Print::money( $slip['net'] ) ); ?></strong></td>
			</tr>
		</tbody>
	</table>
	<p style="font-size:11px;color:#555">
		<?php esc_html_e( 'سهم کارفرما (اطلاعاتی)', 'webino-dashboard' ); ?>:
		<?php echo esc_html( Accounting_Payroll_Print::money( $slip['employer_insurance'] ) ); ?> —
		<?php esc_html_e( 'بیکاری', 'webino-dashboard' ); ?>:
		<?php echo esc_html( Accounting_Payroll_Print::money( $slip['unemployment_insurance'] ) ); ?>
	</p>
	<div class="sign">
		<div><?php esc_html_e( 'کارمند', 'webino-dashboard' ); ?></div>
		<div><?php esc_html_e( 'کارگزینی', 'webino-dashboard' ); ?></div>
		<div><?php esc_html_e( 'مدیریت', 'webino-dashboard' ); ?></div>
	</div>
</div>
</body>
</html>
