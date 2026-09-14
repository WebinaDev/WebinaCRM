import { restGetJson, restPostJson } from '@/api/client'

export type BasalamOAuthStatus = {
  configured?: boolean
  client_id?: string
  client_secret?: string
  redirect_uri?: string
  scopes?: string
  token_url?: string
  sso_url?: string
  callback_path?: string
}

export type BasalamConnection = {
  site_url?: string
  vendor_id?: number
  status?: 'connected' | 'disconnected' | string
  connected_at?: string
  last_seen_at?: string
  disconnected_at?: string
}

export async function getBasalamOAuthStatus() {
  return restGetJson<BasalamOAuthStatus>('basalam/oauth/status')
}

export async function saveBasalamOAuthConfig(payload: {
  client_id: string
  client_secret?: string
  redirect_uri: string
  scopes: string
}) {
  return restPostJson<{ ok?: boolean; status?: BasalamOAuthStatus }>('basalam/oauth/config', payload)
}

export async function listBasalamConnections() {
  return restGetJson<{ connections: BasalamConnection[] }>('basalam/connections')
}

export async function disconnectBasalamConnection(siteUrl: string) {
  return restPostJson<{ ok?: boolean; connections?: BasalamConnection[] }>('basalam/connections/disconnect', {
    site_url: siteUrl,
  })
}
