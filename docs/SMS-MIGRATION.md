# SMS migration to ModirPayamak (IPPanel)

## Summary

Licensed customer sites should use **only** ModirPayamak (CRM wallet + Edge API) for:

- Site OTP (login / register)
- WooCommerce order notifications
- Marketing panel sends
- Product newsletter

Legacy Melipayamak / ParsGreen / Kavenegar paths in WebinoDashboard are no longer used for new sends when license + CRM ModirPayamak are configured.

## Requirements on CRM (webina.dev)

1. Enable ModirPayamak in CRM settings (`modirpayamak_enabled`, `modirpayamak_api_key`).
2. Valid license for customer domain.
3. Customer wallet auto-created on first API call.

## Customer wallet (not CRM shared balance)

- Each licensed domain has a row in `webinocrm_modirpayamak_accounts`.
- Every customer-facing send uses `customer_send($domain)` which runs `deduct_for_send` on that domain’s balance before calling IPPanel Edge.
- The CRM Edge API key is only the reseller technical credential; it does not replace per-domain billing in our ledger.
- `admin_send()` is reserved for CRM operator tools and does not charge the customer wallet.

## Dashboard changes

- `Webino_Dashboard_Sms::send()` always routes to CRM `modirpayamak/send`.
- OTP: `send_otp` / `verify_otp` → CRM `auth/send-otp`, `auth/verify-otp`.
- Shop/site SMS settings stored in CRM per domain (not local `webino_dashboard_sms` option for full config).

## Rollout

1. Deploy webinocrm with SMS tables (`WebinoCRM_Sms_Install`).
2. Deploy WebinoDashboard with hooks + UI.
3. Configure lines in site/shop SMS settings.
4. Sync patterns from shop SMS template editor.
5. Test one order status toggle end-to-end.
