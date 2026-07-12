# WebinoCRM REST API (`webinocrm/v1`)

CRM dashboard resources use standard HTTP verbs. Responses follow the legacy Ajax shape during UI migration:

```json
{ "success": true, "data": { ... } }
```

Authentication: `X-WP-Nonce` header (`window.webinoDashboard.nonce`). Permissions use `WebinoCRM_REST_Base::can_access_route()`.

## Core (no legacy AJAX action)

| Method | Route | Notes |
|--------|-------|-------|
| GET | `/bootstrap` | SPA bootstrap |
| GET/POST | `/settings` | UI theme/locale preferences |
| GET | `/auth/session` | Session probe |
| POST | `/auth/logout` | Logout |

## Dashboard

| REST | Service method |
|------|----------------|
| GET `/dashboard/summary` | `WebinoCRM_Dashboard_Service::summary` |
| GET `/dashboard/full` | `WebinoCRM_Dashboard_Service::full` |
| GET `/dashboard/team-stats` | `WebinoCRM_Dashboard_Service::team_stats` |
| GET `/dashboard/client-stats` | `WebinoCRM_Dashboard_Service::client_stats` |

## Settings

| REST | Legacy action |
|------|----------------|
| GET `/settings/crm` | `webinocrm_get_settings` |
| POST `/settings/crm` | `webino_save_settings` |
| POST `/settings/crm/auth` | `save_auth_settings` |
| GET `/settings/hub` | `webinocrm_get_settings_hub` |
| POST `/settings/hub/toggle` | `webinocrm_save_settings_hub_toggle` |
| POST `/settings/canned-responses` | `webino_manage_canned_response` |
| DELETE `/settings/canned-responses/{id}` | `webino_delete_canned_response` |
| POST `/settings/positions` | `webino_manage_position` |
| DELETE `/settings/positions/{id}` | `webino_delete_position` |
| POST `/settings/task-categories` | `webino_manage_task_category` |
| DELETE `/settings/task-categories/{id}` | `webino_delete_task_category` |

## Projects & contracts

| REST | Service method |
|------|----------------|
| GET `/projects` | `WebinoCRM_Project_Service::list` |
| GET `/projects/{id}` | `WebinoCRM_Project_Service::get` |
| POST/PATCH `/projects` | `WebinoCRM_Project_Service::create_or_update` |
| DELETE `/projects/{id}` | `WebinoCRM_Project_Service::delete` |
| GET `/contracts` | `WebinoCRM_Contract_Service::list` |
| GET `/contracts/{id}` | `WebinoCRM_Contract_Service::get` |
| POST/PATCH `/contracts` | `WebinoCRM_Contract_Service::create_or_update` |
| DELETE `/contracts/{id}` | `WebinoCRM_Contract_Service::delete` |
| POST `/contracts/{id}/cancel` | `WebinoCRM_Contract_Service::cancel` |

## CRM modules (service layer)

| Resource | Service class |
|----------|---------------|
| `/tasks` | `WebinoCRM_Tasks_Service` (list, quick create, status, delete, calendar, gantt, views, extended create, content, comments, checklist, time-log, assignee, search, links, bulk, templates) |
| `GET /tasks/{id}` | `WebinoCRM_Tasks_Service::get` (JSON detail for SPA task sheet) |
| `POST /tasks/{id}/attachments` | `WebinoCRM_Tasks_Service::upload_attachment` (multipart `file`) |
| `DELETE /tasks/{id}/attachments/{attachment_id}` | `WebinoCRM_Tasks_Service::delete_attachment` |
| Task attachments (legacy) | admin-ajax `webino_upload_task_attachment` / `webino_delete_task_attachment` delegate to the same service methods |
| `/tickets` | `WebinoCRM_Tickets_Service` |
| `/leads` | `WebinoCRM_Leads_Service` |
| `/customers` | `WebinoCRM_Customers_Service` |
| `/appointments` | `WebinoCRM_Appointments_Service` |
| `/invoices` | `WebinoCRM_Invoices_Service` |
| `/consultations` | `WebinoCRM_Consultations_Service` |
| `/campaigns` | `WebinoCRM_Campaigns_Service` |
| `/services` | `WebinoCRM_Crm_Services_Module_Service` |
| `/reports` | `WebinoCRM_Reports_Service::get` — query `tab` (`overview`, `sales`, `team`, `customers`, `finance`, `tasks`, `tickets`, `agile`), `date_from`, `date_to`; merges `WebinoCRM_Reports_Dashboard` + `WebinoCRM_Advanced_Analytics` |
| `GET /reports/export` | `WebinoCRM_Reports_Service::export` (CSV contracts/tasks; `_wpnonce`) |
| `GET /reports/analytics/export` | `WebinoCRM_Reports_Service::export_analytics` (CSV/PDF stream; `type`, `format`, `tab`, dates) |
| `/logs` | `WebinoCRM_Logs_Service` |
| `/visitor-statistics` | `WebinoCRM_Visitor_Service` |
| `/chat/*` | `WebinoCRM_Chat_Service` (channels, direct, messages, read, unread-count, search) |
| `/documents` | `WebinoCRM_Documents_Service` (list, upload multipart `file`, folders, PATCH rename/move, share, versions) |
| `GET /documents/{id}/download` | `WebinoCRM_Documents_Service::download` (file stream; `_wpnonce` query) |
| `/time-tracking/*` | `WebinoCRM_Time_Tracking_Service` (active, start/stop/pause/resume, entries, report) |
| `/licenses` | `WebinoCRM_Licenses_Service` |
| `/marketplace/*` | `WebinoCRM_Marketplace_Service` |
| `/modirpayamak/admin/*` | `WebinoCRM_Modirpayamak_Service` |
| `POST /profile` | `WebinoCRM_Profile_Service` |

Routes register in `includes/rest/controllers/class-rest-crm-controllers.php`.

## Accounting

`GET|POST|PATCH|DELETE /accounting/{segment}` → `WebinoCRM_Accounting_Service::{segment}` (e.g. `fiscal_years`, `journal_list`).

Dispatcher: `WebinoCRM_Accounting_Service::by_segment`.

## Auth (login page)

| REST | Service method |
|------|----------------|
| POST `/auth/login/otp/send` | `WebinoCRM_Auth_Service::send_login_otp` |
| POST `/auth/login/otp/verify` | `WebinoCRM_Auth_Service::verify_login_otp` |
| POST `/auth/login/password` | `WebinoCRM_Auth_Service::login_password` |
| POST `/auth/register` | `WebinoCRM_Auth_Service::register` |

## Client

TypeScript modules under `client/src/api/` call `crmGet`, `crmPost`, `crmPatch`, `crmDelete`, and `crmPostForm` from `client/src/api/client.ts`.

The React SPA at `/dashboard` uses the same REST routes via `client/src/api/client.ts` (`crmGet`, `crmPost`, `crmPostForm`, etc.). See `docs/WEBINODASHBOARD-SPLIT.md` for scope.

The legacy `POST /ajax` bridge has been removed.

## Service layer

REST routes call `WebinoCRM_*_Service` classes under `includes/services/`. Each service method returns `{ success, data }` without calling `wp_send_json`. Thin `wp_ajax_*` handlers use `WebinoCRM_Ajax_Service_Delegate::emit_service()` (or `emit_stream()` for CSV downloads). Stream routes use `WebinoCRM_REST_Registry::register_stream_route()` because the handler calls `exit` after sending the file.

| Example REST | Service method |
|--------------|----------------|
| `GET /projects` | `WebinoCRM_Project_Service::list()` |
| `GET /projects/{id}` | `WebinoCRM_Project_Service::get()` |
| `POST/PATCH /projects` | `WebinoCRM_Project_Service::create_or_update()` |
| `DELETE /projects/{id}` | `WebinoCRM_Project_Service::delete()` |
| `GET /projects/meta/assignees` | `WebinoCRM_Project_Service::assignees()` |
| `GET /project-templates` | `WebinoCRM_Project_Service::templates()` |
| `GET /accounting/fiscal-years` | `WebinoCRM_Accounting_Service::fiscal_years()` |

Action map: `WebinoCRM_Service_Action_Map` registers `wp_ajax` action names to service callables at bootstrap.
