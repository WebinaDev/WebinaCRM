<?php

namespace WebinaBaleBusiness\Admin;

use WebinaBaleBusiness\Analytics\EventLogger;
use WebinaBaleBusiness\Api\WebhookController;
use WebinaBaleBusiness\Bale\Client;
use WebinaBaleBusiness\Support\Logger;

class DiagnosticsPage {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$webhook_info = null;
		if ( isset( $_POST['wbb_diag_action'] ) && check_admin_referer( 'wbb_diag_action' ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['wbb_diag_action'] ) );
			if ( $action === 'webhook_info' ) {
				$webhook_info = ( new Client() )->get_webhook_info();
			}
			if ( $action === 'test_log' ) {
				Logger::log( 'info', 'manual_test_log', array( 'source' => 'diagnostics_page' ) );
			}
		}

		$logs = Logger::latest( 40 );
		$support_stats = array(
			'support_opened'       => EventLogger::count_by_event( 'support_opened' ),
			'support_item_clicked' => EventLogger::count_by_event( 'support_item_clicked' ),
		);
		?>
		<div class="wrap">
			<h1>عیب یابی و لاگ ربات کسب و کار وبینا</h1>
			<p>Webhook: <code><?php echo esc_html( WebhookController::instance()->webhook_url() ); ?></code></p>
			<form method="post" style="margin-bottom:16px">
				<?php wp_nonce_field( 'wbb_diag_action' ); ?>
				<button class="button button-primary" name="wbb_diag_action" value="webhook_info">دریافت webhook info</button>
				<button class="button" name="wbb_diag_action" value="test_log">ثبت لاگ تستی</button>
			</form>

			<?php if ( is_array( $webhook_info ) ) : ?>
				<h2>Webhook Info</h2>
				<pre style="background:#fff;padding:12px;border:1px solid #ddd"><?php echo esc_html( wp_json_encode( $webhook_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			<?php endif; ?>

			<h2>رویدادهای پشتیبانی</h2>
			<ul style="list-style:disc;padding-right:20px">
				<li>باز شدن پشتیبانی: <?php echo esc_html( (string) $support_stats['support_opened'] ); ?></li>
				<li>کلیک روی آیتم پشتیبانی: <?php echo esc_html( (string) $support_stats['support_item_clicked'] ); ?></li>
			</ul>

			<h2>آخرین لاگ ها</h2>
			<table class="widefat striped">
				<thead><tr><th>#</th><th>زمان</th><th>سطح</th><th>نوع</th><th>Context</th></tr></thead>
				<tbody>
				<?php foreach ( $logs as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row['id'] ); ?></td>
						<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
						<td><?php echo esc_html( (string) $row['level'] ); ?></td>
						<td><?php echo esc_html( (string) $row['log_type'] ); ?></td>
						<td><code><?php echo esc_html( (string) $row['context'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
