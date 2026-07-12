import { crmDelete, crmGet, crmPatch, crmPost, crmPostForm } from "./client"

export interface MarketplaceCategory {
  id: number
  slug: string
  name: string
  sort: number
  status: string
}

export interface MarketplaceRelease {
  id: number
  module_id: number
  version: string
  tag_name: string
  changelog?: string
  gitea_release_id?: number | null
  package_path?: string | null
  package_source?: string | null
  status: string
  published_at?: string | null
}

export interface MarketplaceModule {
  id: number
  slug: string
  name: string
  description?: string
  readme_md?: string
  icon_url?: string
  detail_url?: string
  category_id?: number | null
  parent_module_id?: number | null
  parent_slug?: string | null
  parent_name?: string | null
  package_available?: boolean
  category_slug?: string
  category_name?: string
  price: number
  currency: string
  is_free: boolean
  is_builtin: boolean
  is_core?: boolean
  version: string
  latest_version?: string
  latest_updated_at?: string | null
  settings_area: string
  settings_route?: string
  sort: number
  status: string
  package_source?: string
  gitea_owner?: string
  gitea_repo?: string
  gitea_repo_id?: number | null
  gitea_repo_url?: string
  gitea_repo_linked?: boolean
  gitea_private?: boolean
  gitea_default_branch?: string
  gitea_html_url?: string
  erp_submodule_settings_key?: string
  latest_release_id?: number | null
  releases?: MarketplaceRelease[]
}

export interface MarketplaceOrder {
  id: number
  domain: string
  module_slug?: string
  module_name?: string
  amount: number
  status: string
  authority?: string
  ref_id?: string
  created_at?: string
}

export type GiteaIpScheme = "auto" | "http" | "https"

export interface GiteaSettings {
  gitea_base_url: string
  gitea_org: string
  gitea_ip_override: string
  gitea_ip_scheme: GiteaIpScheme
  gitea_configured: boolean
  has_token: boolean
}

export interface GiteaDiagnosticStep {
  key?: string
  label?: string
  url?: string
  http?: number
  ok?: boolean
  hint?: string
  message?: string
  ms?: number
  curl_error?: string
}

export interface GiteaConnectionDiagnosticsResult {
  ok: boolean
  message?: string
  user?: string
  diag?: {
    base_url?: string
    api_base?: string
    org?: string
    owner?: string
    owner_kind?: string
    ip_override?: string
    ip_scheme?: string
    host_header?: string
    token_configured?: boolean
    resolved_url_sample?: string
    resolved_scheme?: string
    sslverify?: boolean
  }
  steps?: Record<string, GiteaDiagnosticStep>
  hints?: string[]
}

export async function getMarketplaceCategories() {
  return crmGet<{ categories: MarketplaceCategory[] }>("marketplace/categories")
}

export async function saveMarketplaceCategory(data: Partial<MarketplaceCategory>) {
  return crmPost<{ id: number; message?: string }>("marketplace/categories", {
    id: data.id ? String(data.id) : "",
    slug: data.slug ?? "",
    name: data.name ?? "",
    sort: String(data.sort ?? 0),
    status: data.status ?? "active",
  })
}

export async function deleteMarketplaceCategory(id: number) {
  return crmDelete<{ message?: string }>(`marketplace/categories/${id}`, { id: String(id) })
}

export async function getMarketplaceModules(includeCore = false) {
  const query = includeCore ? "?include_core=1" : ""
  return crmGet<{ modules: MarketplaceModule[]; categories: MarketplaceCategory[] }>(
    `marketplace/modules${query}`,
  )
}

export async function getMarketplaceModule(id: number) {
  return crmGet<{ module: MarketplaceModule; sync_warning?: string }>(`marketplace/modules/${id}`)
}

export async function saveMarketplaceModule(data: FormData) {
  return crmPostForm<{ id: number; message?: string; warnings?: Record<string, string> }>(
    "marketplace/modules",
    data,
  )
}

export async function deleteMarketplaceModule(id: number, deleteGiteaRepo = false) {
  return crmDelete<{ message?: string }>(`marketplace/modules/${id}`, {
    id: String(id),
    delete_gitea_repo: deleteGiteaRepo ? "1" : "0",
  })
}

export async function createMarketplaceModuleRepo(moduleId: number) {
  return crmPost<{ message?: string; module?: MarketplaceModule }>(
    `marketplace/modules/${moduleId}/repo`,
    { id: String(moduleId) },
  )
}

export async function syncMarketplaceModuleRepo(moduleId: number) {
  return crmPost<{ message?: string; module?: MarketplaceModule }>(
    `marketplace/modules/${moduleId}/repo/sync`,
    { id: String(moduleId) },
  )
}

export async function setMarketplaceModuleRepoVisibility(moduleId: number, isPrivate: boolean) {
  return crmPatch<{ message?: string; module?: MarketplaceModule }>(
    `marketplace/modules/${moduleId}/repo`,
    { id: String(moduleId), private: isPrivate ? "1" : "0" },
  )
}

export async function syncMarketplaceModuleReadme(moduleId: number) {
  return crmPost<{ message?: string; module?: MarketplaceModule }>(
    `marketplace/modules/${moduleId}/readme/sync`,
    { id: String(moduleId) },
  )
}

export async function saveMarketplaceRelease(moduleId: number, data: FormData) {
  return crmPostForm<{ id: number; message?: string }>(
    `marketplace/modules/${moduleId}/releases`,
    data,
  )
}

export async function publishMarketplaceRelease(releaseId: number) {
  return crmPost<{ message?: string }>(`marketplace/releases/${releaseId}/publish`, {
    release_id: String(releaseId),
  })
}

export async function deleteMarketplaceRelease(releaseId: number) {
  return crmDelete<{ message?: string }>(`marketplace/releases/${releaseId}`, {
    release_id: String(releaseId),
  })
}

export async function getMarketplaceOrders() {
  return crmGet<{ orders: MarketplaceOrder[]; entitlements: Record<string, unknown>[] }>(
    "marketplace/orders",
  )
}

export async function getGiteaSettings() {
  return crmGet<{ settings: GiteaSettings }>("marketplace/gitea/settings")
}

export async function saveGiteaSettings(data: {
  gitea_base_url: string
  gitea_org: string
  gitea_ip_override: string
  gitea_ip_scheme?: GiteaIpScheme
  gitea_api_token?: string
}) {
  return crmPost<{ message?: string }>("marketplace/gitea/settings", data)
}

export async function testGiteaConnection(data?: {
  gitea_base_url?: string
  gitea_org?: string
  gitea_ip_override?: string
  gitea_ip_scheme?: GiteaIpScheme
  gitea_api_token?: string
}) {
  return crmPost<GiteaConnectionDiagnosticsResult>("marketplace/gitea/test", data ?? {})
}
