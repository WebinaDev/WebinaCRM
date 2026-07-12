<?php

namespace WebinaBaleBusiness\Admin;

use WebinaBaleBusiness\Api\WebhookController;
use WebinaBaleBusiness\Bale\Client;
use WebinaBaleBusiness\Core\Plugin;

class SettingsPage {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting( 'wbb_settings_group', 'wbb_settings', array( $this, 'sanitize' ) );
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = Plugin::settings_defaults();
		$output   = array_merge( $defaults, $input );

		// Allow CRM REST to send structured arrays (not only *_raw JSON strings from wp-admin).
		$direct_arrays = array(
			'support_items'    => 'support_items_raw',
			'feature_docs'     => 'feature_docs_raw',
			'catalog_items'    => 'catalog_items_raw',
			'faq_items'        => 'faq_items_raw',
			'plan_product_map' => 'plan_product_map_raw',
		);
		foreach ( $direct_arrays as $key => $raw_key ) {
			if ( isset( $input[ $key ] ) && is_array( $input[ $key ] ) ) {
				$output[ $raw_key ] = wp_json_encode( $input[ $key ] );
			}
		}
		$output['sales_wc_product_id'] = absint( $output['sales_wc_product_id'] ?? 0 );
		$output['channel_id']          = sanitize_text_field( (string) ( $output['channel_id'] ?? '' ) );
		$output['channel_join_url']    = esc_url_raw( (string) ( $output['channel_join_url'] ?? '' ) );
		$output['membership_required'] = ! empty( $output['membership_required'] ) ? '1' : '0';
		$output['enable_menu_features'] = ! empty( $output['enable_menu_features'] ) ? '1' : '0';
		$output['enable_menu_support'] = ! empty( $output['enable_menu_support'] ) ? '1' : '0';
		$output['enable_menu_profile'] = ! empty( $output['enable_menu_profile'] ) ? '1' : '0';
		$output['enable_menu_businesses'] = ! empty( $output['enable_menu_businesses'] ) ? '1' : '0';
		$output['enable_menu_pricing'] = ! empty( $output['enable_menu_pricing'] ) ? '1' : '0';
		$output['enable_menu_faq'] = ! empty( $output['enable_menu_faq'] ) ? '1' : '0';
		$output['enable_menu_why_bale'] = ! empty( $output['enable_menu_why_bale'] ) ? '1' : '0';
		$output['enable_auto_register_user'] = ! empty( $output['enable_auto_register_user'] ) ? '1' : '0';
		$output['enable_auto_lead_from_business'] = ! empty( $output['enable_auto_lead_from_business'] ) ? '1' : '0';
		$output['membership_gate_text'] = sanitize_textarea_field( (string) ( $output['membership_gate_text'] ?? '' ) );
		$output['membership_error_text'] = sanitize_textarea_field( (string) ( $output['membership_error_text'] ?? '' ) );
		$output['start_hint_text']     = sanitize_textarea_field( (string) ( $output['start_hint_text'] ?? '' ) );
		$output['support_intro_text']  = sanitize_textarea_field( (string) ( $output['support_intro_text'] ?? '' ) );
		$output['support_cta_text']    = sanitize_textarea_field( (string) ( $output['support_cta_text'] ?? '' ) );
		$output['support_items']       = $this->sanitize_support_items( $this->decode_json_array( (string) ( $output['support_items_raw'] ?? '' ), $defaults['support_items'] ?? array() ) );
		$output['feature_docs']        = $this->sanitize_feature_docs( $this->decode_json_array( (string) ( $output['feature_docs_raw'] ?? '' ), $defaults['feature_docs'] ?? array() ) );
		$output['catalog_items']       = $this->decode_json_array( (string) ( $output['catalog_items_raw'] ?? '' ), $defaults['catalog_items'] );
		$output['faq_items']           = $this->decode_json_array( (string) ( $output['faq_items_raw'] ?? '' ), $defaults['faq_items'] );
		$output['plan_product_map']    = $this->decode_json_array( (string) ( $output['plan_product_map_raw'] ?? '' ), $defaults['plan_product_map'] ?? array() );
		unset( $output['catalog_items_raw'], $output['faq_items_raw'], $output['plan_product_map_raw'], $output['support_items_raw'], $output['feature_docs_raw'] );
		return $output;
	}

	/**
	 * @param array<int,mixed> $items
	 * @return array<int,array<string,mixed>>
	 */
	private function sanitize_support_items( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = sanitize_key( (string) ( $item['action_type'] ?? '' ) );
			if ( ! in_array( $type, array( 'url', 'phone', 'username', 'copy_text' ), true ) ) {
				$type = 'copy_text';
			}
			$value = (string) ( $item['action_value'] ?? '' );
			if ( $type === 'url' ) {
				$value = esc_url_raw( $value );
			} elseif ( $type === 'phone' ) {
				$value = preg_replace( '/[^0-9+]/', '', $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			$out[] = array(
				'emoji'        => sanitize_text_field( (string) ( $item['emoji'] ?? '' ) ),
				'title'        => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'description'  => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
				'action_type'  => $type,
				'action_value' => $value,
				'sort'         => absint( $item['sort'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param array<int,mixed> $items
	 * @return array<int,array<string,mixed>>
	 */
	private function sanitize_feature_docs( array $items ): array {
		$out = array();
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key   = sanitize_key( (string) ( $item['key'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			if ( $key === '' || $title === '' ) {
				continue;
			}
			$steps = array();
			if ( isset( $item['steps'] ) && is_array( $item['steps'] ) ) {
				foreach ( $item['steps'] as $step ) {
					$val = sanitize_textarea_field( (string) $step );
					if ( $val !== '' ) {
						$steps[] = $val;
					}
				}
			}
			$limitations = array();
			if ( isset( $item['limitations'] ) && is_array( $item['limitations'] ) ) {
				foreach ( $item['limitations'] as $row ) {
					$val = sanitize_textarea_field( (string) $row );
					if ( $val !== '' ) {
						$limitations[] = $val;
					}
				}
			}
			$related_actions = array();
			if ( isset( $item['related_actions'] ) && is_array( $item['related_actions'] ) ) {
				foreach ( $item['related_actions'] as $row ) {
					$val = sanitize_text_field( (string) $row );
					if ( $val !== '' ) {
						$related_actions[] = $val;
					}
				}
			}
			$tags = array();
			if ( isset( $item['tags'] ) && is_array( $item['tags'] ) ) {
				foreach ( $item['tags'] as $row ) {
					$val = sanitize_key( (string) $row );
					if ( $val !== '' ) {
						$tags[] = $val;
					}
				}
			}
			$out[] = array(
				'key'             => $key,
				'title'           => $title,
				'short'           => sanitize_textarea_field( (string) ( $item['short'] ?? '' ) ),
				'details'         => sanitize_textarea_field( (string) ( $item['details'] ?? '' ) ),
				'steps'           => $steps,
				'limitations'     => $limitations,
				'related_actions' => $related_actions,
				'tags'            => $tags,
				'sort'            => absint( $item['sort'] ?? ( (int) $index * 10 ) ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $fallback
	 * @return mixed
	 */
	private function decode_json_array( string $raw, $fallback ) {
		if ( $raw === '' ) {
			return $fallback;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : $fallback;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$self     = self::instance();
		$settings = Plugin::get_settings();

		if ( isset( $_POST['wbb_webhook_action'] ) && check_admin_referer( 'wbb_webhook_action' ) ) {
			$client = new Client();
			$action = sanitize_text_field( wp_unslash( $_POST['wbb_webhook_action'] ) );
			if ( $action === 'set' ) {
				$client->set_webhook( WebhookController::instance()->webhook_url() );
			}
			if ( $action === 'delete' ) {
				$client->delete_webhook();
			}
			if ( $action === 'info' ) {
				$info = $client->get_webhook_info();
				if ( is_array( $info ) ) {
					echo '<div class="notice notice-info"><p>' . esc_html( wp_json_encode( $info ) ) . '</p></div>';
				}
			}
		}
		?>
		<div class="wrap">
			<h1>تنظیمات ربات کسب و کار وبینا</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wbb_settings_group' ); ?>
				<table class="form-table">
					<tr><th>Bot Token</th><td><input name="wbb_settings[bot_token]" value="<?php echo esc_attr( (string) $settings['bot_token'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Provider Token</th><td><input name="wbb_settings[provider_token]" value="<?php echo esc_attr( (string) $settings['provider_token'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Webhook Secret</th><td><input name="wbb_settings[webhook_secret]" value="<?php echo esc_attr( (string) $settings['webhook_secret'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Channel ID</th><td><input name="wbb_settings[channel_id]" value="<?php echo esc_attr( (string) ( $settings['channel_id'] ?? '' ) ); ?>" class="regular-text"><p class="description">مثل @webina_channel یا شناسه عددی کانال</p></td></tr>
					<tr><th>Channel Join URL</th><td><input name="wbb_settings[channel_join_url]" value="<?php echo esc_attr( (string) ( $settings['channel_join_url'] ?? '' ) ); ?>" class="regular-text"><p class="description">لینک عضویت برای دکمه 📢 عضویت در کانال</p></td></tr>
					<tr><th>اجباری بودن عضویت</th><td><label><input type="checkbox" name="wbb_settings[membership_required]" value="1" <?php checked( (string) ( $settings['membership_required'] ?? '1' ), '1' ); ?>> قبل از شروع فلوهای فروش، عضویت کانال اجباری باشد</label></td></tr>
					<tr><th>محصول فروش ووکامرس</th><td><input type="number" name="wbb_settings[sales_wc_product_id]" value="<?php echo esc_attr( (string) $settings['sales_wc_product_id'] ); ?>"></td></tr>
					<tr><th>متن خوش آمد</th><td><textarea name="wbb_settings[welcome_text]" class="large-text" rows="3"><?php echo esc_textarea( (string) $settings['welcome_text'] ); ?></textarea></td></tr>
					<tr><th>متن گیت عضویت</th><td><textarea name="wbb_settings[membership_gate_text]" class="large-text" rows="2"><?php echo esc_textarea( (string) ( $settings['membership_gate_text'] ?? '' ) ); ?></textarea></td></tr>
					<tr><th>متن خطای عضویت</th><td><textarea name="wbb_settings[membership_error_text]" class="large-text" rows="2"><?php echo esc_textarea( (string) ( $settings['membership_error_text'] ?? '' ) ); ?></textarea></td></tr>
					<tr><th>متن راهنمای شروع</th><td><textarea name="wbb_settings[start_hint_text]" class="large-text" rows="2"><?php echo esc_textarea( (string) ( $settings['start_hint_text'] ?? '' ) ); ?></textarea></td></tr>
					<tr><th>متن معرفی پشتیبانی</th><td><textarea name="wbb_settings[support_intro_text]" class="large-text" rows="2"><?php echo esc_textarea( (string) ( $settings['support_intro_text'] ?? '' ) ); ?></textarea></td></tr>
					<tr><th>CTA پشتیبانی</th><td><textarea name="wbb_settings[support_cta_text]" class="large-text" rows="2"><?php echo esc_textarea( (string) ( $settings['support_cta_text'] ?? '' ) ); ?></textarea></td></tr>
					<tr><th>Support Items JSON</th><td><textarea name="wbb_settings[support_items_raw]" class="large-text code" rows="8"><?php echo esc_textarea( wp_json_encode( $settings['support_items'] ?? array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></textarea><p class="description">emoji,title,description,action_type(url|phone|username|copy_text),action_value,sort</p></td></tr>
					<tr><th>Feature Docs JSON</th><td><textarea name="wbb_settings[feature_docs_raw]" class="large-text code" rows="14"><?php echo esc_textarea( wp_json_encode( $settings['feature_docs'] ?? array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></textarea><p class="description">key,title,short,details,steps[],limitations[],related_actions[],tags[],sort</p></td></tr>
					<tr><th>متن معرفی امکانات</th><td><textarea name="wbb_settings[features_intro_text]" class="large-text" rows="3"><?php echo esc_textarea( (string) $settings['features_intro_text'] ); ?></textarea></td></tr>
					<tr><th>متن CTA سفارش</th><td><input name="wbb_settings[cta_order_text]" value="<?php echo esc_attr( (string) $settings['cta_order_text'] ); ?>" class="regular-text"></td></tr>
					<tr><th>متن تماس فروش</th><td><textarea name="wbb_settings[contact_sales_text]" class="large-text" rows="2"><?php echo esc_textarea( (string) $settings['contact_sales_text'] ); ?></textarea></td></tr>
					<tr><th>قالب لینک پرداخت</th><td><input name="wbb_settings[manual_payment_link_template]" value="<?php echo esc_attr( (string) $settings['manual_payment_link_template'] ); ?>" class="large-text"></td></tr>
					<tr><th>Plan -> Product Map JSON</th><td><textarea name="wbb_settings[plan_product_map_raw]" class="large-text code" rows="6"><?php echo esc_textarea( wp_json_encode( $settings['plan_product_map'] ?? array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></textarea><p class="description">نمونه: {"starter":123,"pro":456}</p></td></tr>
					<tr><th>Catalog Items JSON</th><td><textarea name="wbb_settings[catalog_items_raw]" class="large-text code" rows="8"><?php echo esc_textarea( wp_json_encode( $settings['catalog_items'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></textarea></td></tr>
					<tr><th>FAQ Items JSON</th><td><textarea name="wbb_settings[faq_items_raw]" class="large-text code" rows="8"><?php echo esc_textarea( wp_json_encode( $settings['faq_items'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></textarea></td></tr>
				</table>
				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>

			<hr>
			<h2>مدیریت Webhook</h2>
			<p><code><?php echo esc_html( WebhookController::instance()->webhook_url() ); ?></code></p>
			<form method="post">
				<?php wp_nonce_field( 'wbb_webhook_action' ); ?>
				<button class="button button-primary" name="wbb_webhook_action" value="set">setWebhook</button>
				<button class="button" name="wbb_webhook_action" value="delete">deleteWebhook</button>
				<button class="button" name="wbb_webhook_action" value="info">getWebhookInfo</button>
			</form>
		</div>
		<?php
	}
}
