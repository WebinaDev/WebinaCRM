# ERP module map and gaps (webinocrm)

This document maps the nine ERP modules to current implementation. Use it for backlog planning; routes refer to the dashboard SPA (basename `/dashboard`).

## Module 1 — HRM (`/hrm`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Staff records | **Present** | `/hrm/staff`, `/hrm/staff/:id` — `features/modules/hrm/staff/` |
| Attendance & leave | **Present** | `/hrm/attendance`, `/hrm/leave` — REST `webinocrm/v1/hrm/*` |
| Payroll | **Present** | `/hrm/payroll`, `/hrm/payroll/:id` — journal post on approve (`reference_type=hrm_payroll_run`) |
| Recruitment & onboarding | **Present** | `/hrm/recruitment` — applicant pipeline + hire → staff |
| Performance (KPI) | **Present** | `/hrm/performance` — review cycles + scores |
| Training & development | **Present** | `/hrm/training` — courses, sessions, enrollments |

Backend: `includes/services/class-hrm-*-service.php`, tables `webinocrm_hrm_*` via `WebinoCRM_Installer::create_hrm_tables()`.

## Module 2 — Finance (`/finance`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| General ledger (chart, journals, ledger) | **Present** | `/finance/chart`, `/finance/journals`, `/finance/ledger` |
| Invoicing (formal) | **Present** | `/finance/invoices` |
| Treasury (cash, receipts, checks) | **Present** | `/finance/cash-accounts`, `/finance/receipts`, `/finance/checks` |
| Fiscal year & reports | **Present** | `/finance/fiscal-year`, `/finance/reports` |
| Accounts receivable (dedicated) | **Partial** | Persons + invoices; no AR aging workflow |
| Accounts payable (dedicated) | **Partial** | Same as above |
| Budgeting | **Missing** | — |
| Fixed assets & depreciation | **Missing** | — |

Legacy URLs `/accounting/*` still work (same pages). Code: `features/modules/finance/`.

## Module 3 — CRM (`/crm`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Contacts & companies | **Present** | `/crm/customers` |
| Leads | **Present** | `/crm/leads` |
| Support & ticketing | **Present** | `/crm/tickets` |
| Consultations | **Present** | `/crm/consultations` |
| Opportunities / sales pipeline | **Missing** | — |
| Unified activity timeline | **Partial** | Per-entity history; not one global timeline |

Code: `features/modules/crm/`.

## Module 4 — Project management (`/pm`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Projects | **Present** | `/pm/projects` |
| Tasks | **Present** | `/pm/tasks` |
| Team chat | **Present** | `/pm/chat` |
| Time tracking / timesheet | **Present** | `/pm/time-tracking` |
| Appointments | **Present** | `/pm/appointments` |
| Milestones (dedicated) | **Partial** | Often modeled via tasks/phases |
| Gantt / Kanban | **Partial** | Task views exist; confirm per deployment |
| Resource capacity planning | **Missing** | — |

Code: `features/modules/pm/`.

## Module 5 — SCM (`/scm`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Warehouses | **Present** | `/scm/warehouses` |
| Stock levels | **Present** | `/scm/stock` |
| Inbound / outbound | **Present** | `/scm/inbound`, `/scm/outbound` |
| Stock audit | **Present** | `/scm/audit` |
| Purchase requests & PO | **Missing** | — |
| Vendors | **Missing** | — |
| Logistics / fleet | **Missing** | — |

REST: `webinocrm/v1/scm/*` aliases warehouse segments; `webinocrm/v1/warehouses` unchanged. Code: `features/modules/scm/warehouse/`.

## Module 6 — Sales & marketing (`/sales`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Quotations (pro-forma) | **Present** | `/sales/invoices` |
| Product/service catalog | **Present** | `/sales/catalog` |
| Campaigns | **Present** | `/sales/campaigns` |
| Sales orders (fulfillment) | **Missing** | — |
| Price lists & volume discounts | **Partial** | Woo/store integration where enabled |
| Campaign ROI analytics | **Partial** | Campaign UI; limited ROI |

Note: Formal accounting invoices remain under **Finance** (`/finance/invoices`). **ModirPayamak** and **Bale business bot** are sellable sub-modules under Sales (sidebar); routes stay under `/admin/integrations/*`. Code: `features/modules/sales/`.

## Module 7 — Manufacturing (`/mfg`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| All (BOM, MRP, work orders, QC, PM) | **Missing** | Placeholder only: `/mfg` — `features/modules/mfg/` (disabled by default) |

## Module 8 — Documents & contracts (`/docs`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Contracts | **Present** | `/docs/contracts` |
| File archive | **Present** | `/docs/files` |
| Policies / bylaws registry | **Missing** | — |
| Contract expiry alerts | **Partial** | Contract dates exist; dedicated alerts TBD |

Code: `features/modules/docs/`.

## Module 9 — Admin (`/admin`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Settings hub | **Present** | `/admin/settings` |
| Visitor analytics | **Present** | `/admin/analytics/visitors` (if tracking enabled) |
| System logs | **Present** | `/admin/logs` |
| Users, roles, permissions | **Partial** | WP users + CRM roles; no visual workflow builder |
| Configurable workflows | **Missing** | — |
| Public API docs | **Partial** | REST documented in `docs/rest-api.md` |

Code: `features/modules/admin/`.

## Module 10 — Distribution (`/admin/marketplace`, `/admin/licenses`)

| Submodule | Status | Route / code |
|-----------|--------|----------------|
| Marketplace products | **Present** | `/admin/marketplace/products` |
| Package server (Gitea) | **Present** | `/admin/marketplace/gitea` |
| Marketplace categories | **Present** | `/admin/marketplace/categories` |
| Marketplace orders | **Present** | `/admin/marketplace/orders` |
| Marketplace licenses | **Present** | `/admin/licenses` |

Toggle: `module_distribution_enabled`. Legacy marketplace URLs still redirect to `/admin/marketplace/*`.

Code: `features/modules/admin/marketplace/`, `features/modules/admin/licenses/`.

## Extra capabilities (kept outside ERP numbering)

- **Global dashboard & reports**: `/`, `/reports` — `features/shell/dashboard/`, `features/shell/reports/`
- **Profile**: `/profile` — `features/shell/profile/`
- **Login**: `features/shell/login/`
- **Shared UI**: `features/shared/` (CrmPageLayout, Pm*, charts)
- **Realtime chat server**: `realtime/` (infrastructure for PM chat)
- **WebinoDashboard shop plugin**: separate plugin; not part of webinocrm SPA

## Registry & toggles

- Client: `client/src/modules/registry.ts` (`ERP_MODULES`, `ERP_SUBMODULES`)
- PHP: `includes/modules/class-erp-module-registry.php`
- Settings: `module_{key}_enabled` for ERP modules and sub-modules (`modirpayamak`, `bale_business`, `distribution`)
- Legacy keys `accounting`, `projects`, `bots`, `general` still honored where mapped
- Marketplace hook: `webinocrm_submodule_entitlement_changed` (grant/revoke → toggle sub-module)

## URL migration summary

| Legacy | New |
|--------|-----|
| `/staff` | `/hrm/staff` |
| `/accounting/*` | `/finance/*` (warehouse → `/scm/*`) |
| `/leads`, `/customers`, … | `/crm/...` |
| `/projects`, `/tasks`, … | `/pm/...` |
| `/contracts`, `/documents` | `/docs/contracts`, `/docs/files` |
| `/invoices`, `/services`, `/campaigns` | `/sales/...` |
| `/settings`, `/marketplace`, `/modirpayamak`, … | `/admin/...` |

Both legacy and new paths are registered in the React router until external integrations migrate.

## Marketplace module install (customer dashboard → CRM)

Before a site can install a Gitea-sourced module (e.g. `zarinpal-gateway-module`) from **Webino Dashboard** marketplace:

1. In **webinocrm** admin → Marketplace → module → **Publish** a release (ZIP on disk or built from Gitea).
2. Confirm `POST /wp-json/webinocrm/v1/marketplace/download-token` with `domain` + `module_slug` returns `ok: true` and `download_url` (not `Package file is not available`).
3. The customer server (e.g. parisma.ir) must reach `webina.dev` for CRM API and ZIP download (outbound HTTPS), not only the browser.

If install fails at `download_token` with `no_package`, the CRM response includes `debug` (`slug`, `package_source`, `release_id`, `tag_name`, `gitea_owner`, `gitea_repo`, `archive_tags_tried`, `ensure_error`, `ensure_error_code`) for admins.

CRM resolves Gitea archives with tag fallbacks (`1.0.0` and `v1.0.0`). Ensure the release is **Published** in CRM (not only tagged in Gitea). After deploying updated `webinocrm` to webina.dev, re-publish the release once to warm the ZIP cache under `wp-content/uploads/webinocrm-packages/`.
