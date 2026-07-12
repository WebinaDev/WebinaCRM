# WebinoDashboard vs WebinoCRM client

The **webinocrm** WordPress plugin ships a single React SPA at `/dashboard` for CRM operations.

## In this repository

- `client/src/pages/crm/**` — active routes (`client/src/routes/routes.config.tsx`)
- `client/src/pages/LicensePage.tsx` — plugin license activation (`/dashboard/license`)
- `client/src/pages/LoginPage.tsx`, `NotFoundPage.tsx`

## Moved out of scope (removed from client build)

Store, marketing, magazine, orders, CMS, and related components lived under:

- `client/src/pages/shop/`, `marketing/`, `magazine/`, `orders/`, `cms/`, `users/`, `wfcp/`, `bots/`, `settings/shop/`, etc.

Those UIs belong to the separate **WebinoDashboard** product and are not registered in this plugin's router.

## Collaboration modules (SPA + REST)

These legacy PHP classes remain under `includes/`; domain logic is exposed via services and REST (no wp-admin enqueue):

| Module | PHP class | Service | SPA route |
|--------|-----------|---------|-----------|
| Team chat | `WebinoCRM_Team_Chat` | `WebinoCRM_Chat_Service` | `/dashboard/chat` |
| Documents | `WebinoCRM_Document_Management` | `WebinoCRM_Documents_Service` | `/dashboard/documents` |
| Time tracking | `WebinoCRM_Time_Tracking` | `WebinoCRM_Time_Tracking_Service` | `/dashboard/time-tracking` |

CRM analytics use **`/dashboard/reports`** (`WebinoCRM_Reports_Service` + restored `WebinoCRM_Advanced_Analytics` static methods). Tabs: overview, sales, team, customers, finance, tasks, tickets, agile. Site visitor stats remain at `/dashboard/visitor-statistics`.

## Legacy task UI

Kanban-specific JS (`kanban-board.js`, `tasks-management.js`, HTML modal renderer) was removed. Task management uses:

- `GET /webinocrm/v1/tasks/{id}` (JSON)
- `TaskDetailSheet` in the React tasks page
