# Gitea marketplace operations

This document covers server setup for `package.webina.dev` (Gitea) with WebinoCRM marketplace module delivery.

## Gitea server

1. Choose an **owner namespace** for module repositories (see [Owner: Organization vs User](#owner-organization-vs-user)).
2. Create a service account user and generate an API token with scopes:
   - `read:repository`, `write:repository`, and `read:organization` (when using an org owner)
3. All module repositories should be **private**.
4. Default URL: `https://package.webina.dev` (IP may change; use CRM setting `gitea_ip_override` if DNS routing requires direct IP, e.g. `185.164.73.225`).

## Owner: Organization vs User

Gitea has two namespace types for repositories:

| Type | Web URL | Detect API | Create repo API |
|------|---------|------------|-----------------|
| **User** | `/{username}` e.g. `/webina` | `GET /api/v1/users/webina` | `POST /api/v1/user/repos` |
| **Organization** | `/{org}` (separate entity) | `GET /api/v1/orgs/webina` | `POST /api/v1/orgs/webina/repos` |

In CRM settings the field is labeled **Owner (organization or username)**. The stored setting key remains `gitea_org` for backward compatibility.

### User owner (e.g. `webina`)

- Repositories appear as `webina/{slug}` under the user account.
- Suitable when your Gitea instance uses a single admin user namespace (common on small setups).
- The API token must belong to that user — CRM creates repos via `POST /user/repos`.
- Diagnostics show `owner_kind=user` and hint `owner_is_user` when org probe returns 404 but user probe succeeds.

### Organization owner

- Use when multiple admins manage module repos or you want repos separate from a personal user account.
- Create the org in Gitea: **Site Administration → Organizations → Create Organization** (or equivalent admin UI).
- CRM creates repos via `POST /orgs/{owner}/repos`.
- Diagnostics show `owner_kind=org`.

### Limitation

CRM cannot create repositories under **another user's** namespace without an organization. If owner is a user name that does not match the API token login, create a Gitea organization or use a token for that user.

## WebinoCRM settings

In CRM → **بازارچه** → **سرور پکیج (Gitea)**:

| Field | Example |
|-------|---------|
| Base URL | `https://package.webina.dev` |
| Owner | `webina` (org or username) |
| Direct IP | `185.164.73.225` (optional) |
| Direct IP protocol | `auto` (default), `http`, or `https` — use `http` or `auto` when the IP serves plain HTTP |
| API token | (service account token) |

Use **Test connection** with the current form values (token can be tested before saving). The diagnostics panel shows step-by-step probes (version, auth, owner, owner repos) with HTTP codes, `owner_kind`, and hints.

### SSL / wrong version number (cURL error 35)

If CRM connects via **HTTPS to the direct IP** but Gitea on that IP only listens on **HTTP**, you will see `SSL routines::wrong version number`. Fix:

1. Set **Direct IP protocol** to `http`, or leave `auto` (CRM retries once with HTTP after SSL error 35).
2. Keep **Base URL** as the public hostname (`https://package.webina.dev`); CRM sends the correct `Host` header when using IP override.

### Troubleshooting diagnostics

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| auth=200, owner=404 (GetOrgByName) | Owner is a **user**, not an org | Keep owner name as-is; CRM should detect `owner_kind=user` after update |
| auth=401 | Invalid token or scopes | Regenerate token with repo (and org) scopes |
| version fails, SSL error 35 | HTTPS to HTTP-only IP | Set Direct IP protocol to `http` or `auto` |
| owner_not_found | Name typo or deleted account | Verify owner exists at `/{name}` on Gitea |
| owner_is_user + connection OK | Expected for user namespace | No action needed |

Example diagnostic hints: `ssl_wrong_version`, `token_missing`, `owner_not_found`, `owner_is_user`.

## CRM server requirements

- Outbound HTTPS to Gitea (or HTTP to IP with `Host` header when using IP override).
- For hybrid ZIP fallback when no release asset exists:
  - `git` CLI installed and on PATH, **or**
  - Gitea archive API reachable (`/api/v1/repos/{owner}/{repo}/archive/{tag}.zip`)

Cached packages are stored under:

- `wp-content/uploads/webinocrm-packages/` (release builds)
- `wp-content/uploads/webinocrm-marketplace/` (manual ZIP uploads)

## Module workflow

1. Create module in CRM marketplace products (enable **Gitea repository** or open module detail → **Create Gitea repository**).
2. Push code to `webina/{slug}` (each module = one repo named by slug).
3. Open **Manage / releases** — edit product settings, README, price, and repository visibility from the module detail page.
4. Add a **release** in CRM (version + tag + changelog). **No ZIP upload** is required for Gitea-backed modules; CRM creates the Gitea release automatically.
5. **Publish** release — CRM resolves the package ZIP from Gitea archive (or release asset / git clone fallback).
6. Customer dashboards with active license + entitlement install via existing marketplace flow (`download-token`).

Admin product list hides core/internal products by default; enable **Show core/internal products** to manage `webinocrm` and `webino-dashboard` seeds.

## Dashboard core releases

The CRM seeds a hidden marketplace product **`webino-dashboard`** (`is_core`) linked to Gitea repo `webina/webino-dashboard`.

**Build ZIP** (monorepo root):

```bash
bash WebinoDashboard/scripts/build-all-modules.sh
cd WebinoDashboard/client && npm run build
bash WebinoDashboard/scripts/build-release-zip.sh
```

Upload/publish that ZIP as a CRM release for the core product. Archive layout must be:

```text
WebinoDashboard/     # plugin files + assets/dashboard-build
Modules/.gitkeep     # empty placeholder (do not ship builtin modules)
```

**Customer site API** (licensed domain only):

- `GET /wp-json/webinocrm/v1/marketplace/core/check?domain=…&current_version=…`
- `POST /wp-json/webinocrm/v1/marketplace/core/download-token` → same download endpoint as modules

**Dashboard UI:** Settings → Site → Dashboard → **Dashboard core update**. Updates replace only `wp-content/plugins/WebinoDashboard/`; installed `Modules/{slug}/` folders are preserved.

## CRM core releases

The CRM also seeds a hidden core product **`webinocrm`** (`is_core`) linked to Gitea repo `webina/webinocrm`.

Package layout must contain:

```text
webinocrm.php
includes/
client/
```

**CRM surfaces:**

- UI: Settings → CRM → **CRM core update**
- REST:
  - `GET /wp-json/webinocrm/v1/core/update-status`
  - `POST /wp-json/webinocrm/v1/core/update`
- Service actions:
  - `check_crm_core_update`
  - `run_crm_core_update`

**Safety behavior:** updater uses lock + backup + rollback; update applies only inside `wp-content/plugins/webinocrm/`.

## Security

- Gitea token is stored only in CRM `webinocrm_settings` — never sent to customer sites.
- Customer sites download packages only through CRM `webinocrm/v1/marketplace/download` with short-lived tokens.
- Restrict Gitea admin/API access; firewall API to CRM server IP where possible.

## Firewall checklist

- [ ] Owner namespace configured (org or user)
- [ ] Service token issued and saved in CRM
- [ ] CRM host can reach Gitea API
- [ ] `git` installed on CRM host (recommended)
- [ ] Private repos only
- [ ] Optional: limit Gitea API source IPs to CRM
