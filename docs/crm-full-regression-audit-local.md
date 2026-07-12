# CRM Full Regression Audit (Local Codebase)

## Scope
- SPA dashboard routes in `webinocrm/client/src/routes/routes.config.tsx` and `webinocrm/client/src/App.tsx`
- WP admin pages/menu registrations under `webinocrm/includes`
- Public auth and dashboard bootstrap flows in REST/PHP templates
- REST/service contracts, permissions, and nonce handling

## Route-Service-Permission Matrix
- `dashboard/projects/accounting/settings/marketplace/bots/auth` SPA routes are defined in `webinocrm/client/src/routes/routes.config.tsx` (64 mapped routes).
- Core REST registrations are in `webinocrm/includes/rest/controllers/class-rest-crm-controllers.php`.
- Accounting is dispatched by catch-all route in `webinocrm/includes/rest/class-rest-routes.php` to `WebinoCRM_Accounting_Service::by_segment()`.
- REST permission gating uses `WebinoCRM_REST_Registry::can_route()` and route capabilities (`webinocrm_route_*`) from frontend route definitions.
- WP admin menu/submenu registrations were identified in:
  - `webinocrm/includes/class-task-template-manager.php`
  - `webinocrm/includes/class-white-label.php`
  - `webinocrm/includes/integrations/bale-business/Admin/Menu.php`

## Baseline Gates
- `npm run build:check` passed.
- `npm run lint` initially failed with 2 blocking errors.
- PHP lint across `webinocrm/**/*.php` passed.

## Regression Issues Found and Fixed
1. `invoices-page` used `loadProjectsForCustomer` before declaration (react-hooks immutability error).
   - Fixed in `webinocrm/client/src/pages/crm/invoices-page.tsx` by hoisting as a function declaration.
2. `staff-page` used `openEdit` before declaration (react-hooks immutability error).
   - Fixed in `webinocrm/client/src/pages/crm/staff-page.tsx` by hoisting as a function declaration.
3. Services module auth check was called but its return value was ignored in multiple methods.
   - Fixed in `webinocrm/includes/services/class-crm-services-module-service.php` by enforcing early return on failed access.
4. Services module returned invalid `error()` payload shape (array as message argument).
   - Fixed in `webinocrm/includes/services/class-crm-services-module-service.php` by passing string messages to `WebinoCRM_Service_Base::error()`.
5. Accounting nonce contract mismatch (`webinocrm-ajax-nonce` only vs REST `X-WP-Nonce`/`wp_rest`).
   - Fixed in `webinocrm/includes/services/class-accounting-service.php` to accept both nonce models.
   - Also fixed at REST runner layer in `webinocrm/includes/rest/controllers/class-rest-controller-base.php` by forwarding `X-WP-Nonce` into service params as `_wpnonce`.

## Remaining Non-Blocking Observations
- ESLint still reports multiple warnings (`exhaustive-deps`, `react-refresh/only-export-components`) across older files. They are warnings, not runtime blockers.
- Full behavioral proof for every workflow still requires runtime WP environment + seeded data (outside local static-only scope).
