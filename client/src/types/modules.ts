export interface DashboardModule {
  id: string
  title: string
  path: string
  capability: string
  icon?: string
  children?: DashboardModule[]
}

export interface BootstrapPayload {
  modules: DashboardModule[]
  locale: string
  uiTheme?: string
  uiAccent?: string
  /** User preference: prefer fullscreen when opening dashboard. */
  uiFullscreen?: boolean
  capabilities?: string[]
  user?: { name: string; email: string; avatar?: string; role?: string }
  site: { name: string; url: string; icon?: string; currency?: string; currency_symbol?: string }
  flags: { woocommerce?: boolean; wfcp?: boolean; crm?: boolean; accounting?: boolean }
  license?: {
    active: boolean
    status: string
    message?: string
    expiry?: string | null
    demo?: boolean
    domain?: string
  }
  marketplaceSettingsSections?: {
    slug: string
    title: string
    area: 'site' | 'shop'
    route: string
  }[]
}
