/// <reference types="vite/client" />

import type { BootstrapPayload } from './types/modules'

export interface WebinoDashboardConfig {
  version: string
  /** Max filemtime of built entry assets; use for SW / cache bust when chunk names are stable. */
  assetVersion?: string
  baseUrl: string
  assetBase?: string
  homeUrl: string
  restUrl: string
  nonce: string
  ajaxNonce?: string
  /** CSRF token for POST /auth/login (guest-safe). */
  loginNonce: string
  locale: string
  isLogged: boolean
  isRtl?: boolean
  userId: number
  /** Public site title from the CMS (login + SEO). */
  siteName?: string
  /** Site icon or bundled favicon for og:image. */
  siteIconUrl?: string
  /** Outbound CRM license snapshot (no secrets). */
  license?: {
    active: boolean
    status: string
    message?: string
    expiry?: string | null
    demo?: boolean
    domain?: string
  }
  /** Optional server-injected bootstrap snapshot for first paint / placeholderData. */
  bootstrap?: BootstrapPayload
  /** Route-scoped SSR payload (path, route id). */
  page?: {
    path?: string
    route?: string
    generated?: number
  }
  i18n?: {
    server_error?: string
  }
  marketplaceSettingsSections?: BootstrapPayload['marketplaceSettingsSections']
  /** Mirrors bootstrap `flags` (WooCommerce / WFCP loaded). */
  flags?: {
    woocommerce?: boolean
    wfcp?: boolean
    baleBot?: boolean
    telegramBot?: boolean
    elementor?: boolean
    /** When true, do not register dashboard-sw.js (avoids stale SW after deploy). */
    disableServiceWorker?: boolean
    crm?: boolean
  }
  wsUrl?: string
  wsEnabled?: boolean
}

export interface WebinoCrmUser {
  id?: number
  roleSlug?: string
  name?: string
  first_name?: string
  last_name?: string
  email?: string
  avatar?: string
  avatarLarge?: string
  webino_mobile_phone?: string
}

export interface WebinoCrmLegacyConfig {
  ajaxUrl?: string
  restUrl?: string
  nonce?: string
  user?: WebinoCrmUser
  primaryColor?: string
  dashboardBasePath?: string
  isRtl?: boolean
}

export interface WebinoLoginConfig {
  restUrl?: string
  ajaxUrl?: string
  dir?: 'rtl' | 'ltr'
  siteName?: string
  logoUrl?: string
}

declare global {
  interface Window {
    webinoDashboard: WebinoDashboardConfig
    webinocrm?: WebinoCrmLegacyConfig
    WebinoLoginConfig?: WebinoLoginConfig
  }
}

export {}
