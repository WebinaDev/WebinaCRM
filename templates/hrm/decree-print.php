<?php
/**
 * Employment decree (حکم کارگزینی) print template.
 *
 * @var array $decree
 * @var array $emp
 * @var array|null $wh
 * @var array $company
 * @package WebinoDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$types = array(
	'hire'       => 'استخدام',
	'promote'    => 'ترفیع',
	'pay_change' => 'تغییر حقوق',
	'transfer'   => 'انتقال',
	'terminate'  => 'قطع همکاری',
);
$type_label = $types[ $decree['decree_type'] ?? '' ] ?? ( $decree['decree_type'] ?? '' );
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8"/>
	<title><?php echo esc_html( sprintf( __( 'Employment decree %s', 'webino-dashboard' ), $decree['decree_no'] ?? '' ) ); ?></title>
	<style>
		@page { size: A4; margin: 14mm; }
		body { font-family: Tahoma, "Segoe UI", sans-serif; font-size: 13px; color: #111; margin: 0; }
		.sheet { max-width: 190mm; margin: 0 auto; line-height: 1.7; }
		h1 { text-align: center; font-size: 18px; margin-bottom: 4px; }
		.sub { text-align: center; margin-bottom: 18px; }
		table { width: 100%; border-collapse: collapse; margin: 12px 0; }
		td, th { border: 1px solid #333; padding: 6px 8px; }
		th { background: #f3f3f3; text-align: right; width: 35%; }
		.num { direction: ltr; text-align: left; font-variant-numeric: tabular-nums; }
		.sign { display: flex; justify-content: space-between; margin-top: 40px; }
		.sign div { width: 28%; text-align: center; border-top: 1px solid #333; padding-top: 6px; }
		@media print { .no-print { display: none !important; } }
		.no-print { text-align: center; margin: 12px; }
	</style>
</head>
<body onload="window.print()">
<div class="no-print"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'webino-dashboard' ); ?></button></div>
<div class="sheet">
	<h1><?php echo esc_html( $company['company_name'] ?: __( 'Company', 'webino-dashboard' ) ); ?></h1>
	<div class="sub"><strong><?php esc_html_e( 'حکم کارگزینی', 'webino-dashboard' ); ?></strong></div>
	<table>
		<tr><th><?php esc_html_e( 'شماره حکم', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $decree['decree_no'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'نوع حکم', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $type_label ); ?></td></tr>
		<tr><th><?php esc_html_e( 'تاریخ صدور', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $decree['issue_date'] ?? '—' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'تاریخ اجرا', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $decree['effective_from'] ?? '' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'نام کارمند', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $emp['name'] ?? '' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'کد ملی', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $emp['national_id'] ?? '' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'شغل / کد شغل تامین', 'webino-dashboard' ); ?></th><td><?php echo esc_html( ( $decree['job_title'] ?? '' ) . ' / ' . ( $decree['job_code'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'واحد سازمانی', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $decree['department'] ?? '—' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'کارگاه', 'webino-dashboard' ); ?></th><td><?php echo esc_html( $wh ? ( $wh['name'] . ' (' . $wh['code'] . ')' ) : '—' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'مزد روزانه', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['daily_wage'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'حقوق پایه ماهانه', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['base_salary'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'بن خواربار', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['benefit_food'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'حق مسکن', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['benefit_housing'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'حق اولاد', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['benefit_child'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'حق تأهل', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['benefit_marriage'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'پایه سنوات', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['benefit_seniority'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'ایاب و ذهاب', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['benefit_transport'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'حق مسئولیت', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( Accounting_Payroll_Print::money( $decree['benefit_responsibility'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'نرخ سختی کار ٪', 'webino-dashboard' ); ?></th><td class="num"><?php echo esc_html( (string) $decree['hardship_rate'] ); ?></td></tr>
	</table>
	<?php if ( ! empty( $decree['notes'] ) ) : ?>
		<p><?php echo esc_html( $decree['notes'] ); ?></p>
	<?php endif; ?>
	<div class="sign">
		<div><?php esc_html_e( 'کارگزینی', 'webino-dashboard' ); ?></div>
		<div><?php esc_html_e( 'مدیر مالی', 'webino-dashboard' ); ?></div>
		<div><?php esc_html_e( 'مدیرعامل', 'webino-dashboard' ); ?></div>
	</div>
</div>
</body>
</html>
