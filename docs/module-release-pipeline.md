# Module Release Pipeline (CRM Owner)

## Three-repo model

1. Module repository: source code of one module.
2. CRM repository: marketplace catalog, release metadata, and build/release automation.
3. Dashboard repository: runtime installer/activator only.

## Responsibilities

### Module repository

- Own module source and tests.
- Produce `client/dist/module.js` in CI.
- Publish release artifact metadata to CRM pipeline.

### CRM repository

- Build and package module ZIP artifacts.
- Store release metadata (`slug`, `version`, package path/source).
- Enforce entitlement and download token checks.
- Provide marketplace catalog APIs for Dashboard.

### Dashboard repository

- Download ZIP from CRM token endpoint.
- Extract to external `Modules/{slug}`.
- Validate package contract and activate/deactivate.
- Load routes/settings from manifest dynamically.

## ZIP contract (required)

- `manifest.json`
- `bootstrap.php`
- `includes/`
- `client/dist/module.js`

Gateway modules with bundled upstream WooCommerce plugins also declare `vendor.required_paths` in `manifest.json`. CRM `validate_zip_contract()` enforces those paths at publish time (e.g. SnappPay, TorobPay, Torob Products Extractor).

Example:

```json
"vendor": {
  "required_paths": [
    "vendor/torobpay-woocommerce-gateway/index.php"
  ]
}
```

Place licensed upstream code under `vendor/` in the module repository before packaging. Customers may alternatively install the same plugin under `wp-content/plugins/`; module bootstraps prefer an active standalone install.


- files at zip root, or
- one top-level folder (Dashboard flattening supports it).

## Module onboarding checklist

1. Create module repo and implement module contract.
2. Configure module CI build and ZIP packaging.
3. Register module in CRM catalog (`package_source=gitea`, owner/repo/version/slug).
4. Publish release from CRM pipeline.
5. Install and activate from Dashboard marketplace.

## Validation checklist

1. Clean Dashboard install: install module from marketplace.
2. Activate/deactivate module and verify routes/settings.
3. Uninstall module and verify cleanup.
4. Publish new module release and verify update path without Dashboard core update.

## Republish after module architecture hardening

Sites may still have legacy `bootstrap.php` files (e.g. `telegram-bot-module` requiring `bale-bot/includes/`). Dashboard core now blocks unsafe bootstraps, but customers should receive fixed ZIPs:

1. Rebuild and publish CRM releases for at least:
   - `telegram-bot-module` (no cross-module require; `requires_modules: ["bale-bot-module"]` in manifest)
   - `bale-bot-module` (empty bootstrap; shared bots in Dashboard core)
2. Bump `version` in each `manifest.json` before packaging.
3. Run CRM package script so marketplace download serves the new artifact.
4. Customer sites: update module from marketplace **or** upload files listed in `WebinoDashboard/scripts/deploy-module-hardening.sh`.

## Core update checklist (Dashboard + CRM)

1. Run status check:
   - Dashboard: `GET /wp-json/webino-dashboard/v1/core/update-status`
   - CRM: `GET /wp-json/webinocrm/v1/core/update-status`
2. Trigger update:
   - Dashboard: `POST /wp-json/webino-dashboard/v1/core/update`
   - CRM: `POST /wp-json/webinocrm/v1/core/update`
3. Verify rollback safety:
   - Dashboard backups: `wp-content/uploads/webino-dashboard-backups/`
   - CRM backups: `wp-content/uploads/webinocrm-core-backups/`
4. Confirm module independence:
   - Updating Dashboard/CRM core must not install/update marketplace modules.
   - Installed module directories and activation states remain unchanged.

## Pre-publish command (CRM)

Before publishing a release, run CRM command/action:

- `webinocrm_check_marketplace_release`
- input: `release_id`

This enforces ZIP contract and returns precise errors for missing paths:

- `manifest.json`
- `bootstrap.php`
- `includes/`
- `client/dist/module.js`
- `vendor/*` entries listed in `manifest.vendor.required_paths` (gateway modules)
