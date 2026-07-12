<?php

namespace WebinaBaleBusiness\Admin;

use WebinaBaleBusiness\Analytics\EventLogger;

class DashboardPage {

	public static function init(): void {}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$stats = array(
			'conversation_started' => EventLogger::count_by_event( 'conversation_started' ),
			'features_opened'      => EventLogger::count_by_event( 'features_opened' ),
			'plans_opened'         => EventLogger::count_by_event( 'plans_opened' ),
			'support_opened'       => EventLogger::count_by_event( 'support_opened' ),
			'support_item_clicked' => EventLogger::count_by_event( 'support_item_clicked' ),
			'order_registered'     => EventLogger::count_by_event( 'order_registered' ),
			'payment_success'      => EventLogger::count_by_event( 'payment_success' ),
		);
		?>
		<div class="wrap">
			<h1>داشبورد ربات کسب و کار وبینا</h1>
			<p>نمایش عملکرد فروش ربات در بله</p>
			<table class="widefat striped" style="max-width:700px">
				<thead><tr><th>شاخص</th><th>مقدار</th></tr></thead>
				<tbody>
					<tr><td>شروع گفتگو</td><td><?php echo esc_html( (string) $stats['conversation_started'] ); ?></td></tr>
					<tr><td>بازدید امکانات</td><td><?php echo esc_html( (string) $stats['features_opened'] ); ?></td></tr>
					<tr><td>بازدید پلن ها</td><td><?php echo esc_html( (string) $stats['plans_opened'] ); ?></td></tr>
					<tr><td>باز شدن پشتیبانی</td><td><?php echo esc_html( (string) $stats['support_opened'] ); ?></td></tr>
					<tr><td>کلیک آیتم پشتیبانی</td><td><?php echo esc_html( (string) $stats['support_item_clicked'] ); ?></td></tr>
					<tr><td>ثبت سفارش</td><td><?php echo esc_html( (string) $stats['order_registered'] ); ?></td></tr>
					<tr><td>پرداخت موفق</td><td><?php echo esc_html( (string) $stats['payment_success'] ); ?></td></tr>
				</tbody>
			</table>
			<p style="margin-top:16px">برای تست عملی، در تنظیمات webhook را فعال کرده و با <code>/start</code> در بله آغاز کنید. برای خطاها به صفحه <a href="<?php echo esc_url( admin_url( 'admin.php?page=wbb-diagnostics' ) ); ?>">عیب یابی و لاگ</a> بروید.</p>
		</div>
		<?php
	}
}
