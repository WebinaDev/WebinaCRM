/**
 * CRM API client — REST resources under webinocrm/v1.
 */

import { apiFetch } from '@/lib/api'

type CrmUser = {
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

function getConfig() {
  const c = window.webinoDashboard
  if (!c?.restUrl) {
    throw new Error('Dashboard API: restUrl not found')
  }
  const nonce = c.nonce ?? c.ajaxNonce ?? ''
  if (!nonce) {
    throw new Error('Dashboard API: nonce not found')
  }
  return { restUrl: c.restUrl, nonce }
}

export interface AjaxResponse<T = unknown> {
  success: boolean
  data?: T
  total?: number
  message?: string
}

function getServerErrorMessage(): string {
  return window.webinoDashboard?.i18n?.server_error ?? 'Server error'
}

export function getAjaxMessage(
  json: AjaxResponse<unknown> | null | undefined,
  fallback?: string,
): string | undefined {
  if (!json) return fallback
  if (typeof json.message === 'string' && json.message.trim()) return json.message
  const data = json.data
  if (data && typeof data === 'object' && 'message' in data) {
    const msg = (data as { message?: unknown }).message
    if (typeof msg === 'string' && msg.trim()) return msg
  }
  return fallback
}

function normalizeAjaxResponse<T>(json: AjaxResponse<T>): AjaxResponse<T> {
  const message = getAjaxMessage(json)
  if (!json.success && message && !json.message) {
    return { ...json, message }
  }
  return json
}

type ParamScalar = string | number | boolean
type ParamValue = ParamScalar | ParamScalar[] | undefined | null

function serializeParam(v: ParamValue): string | undefined {
  if (v == null || v === '') return undefined
  if (Array.isArray(v)) {
    const items = v.map((item) => String(item)).filter((item) => item !== '')
    return items.length > 0 ? items.join(',') : undefined
  }
  return typeof v === 'boolean' ? (v ? '1' : '0') : String(v)
}

function filterParams(params: Record<string, ParamValue> = {}): Record<string, string> {
  const out: Record<string, string> = {}
  for (const [k, v] of Object.entries(params)) {
    const serialized = serializeParam(v)
    if (serialized !== undefined) out[k] = serialized
  }
  return out
}

function buildQuery(params?: Record<string, ParamValue>): string {
  if (!params) return ''
  const sp = new URLSearchParams(filterParams(params))
  const q = sp.toString()
  return q ? `?${q}` : ''
}

async function crmRequest<T>(
  method: string,
  path: string,
  params?: Record<string, ParamValue>,
  body?: Record<string, unknown> | FormData,
): Promise<AjaxResponse<T>> {
  const rel = path.replace(/^\//, '')
  const query = method === 'GET' || method === 'DELETE' ? buildQuery(params) : ''
  const init: RequestInit = { method }

  if (body instanceof FormData) {
    if (params) {
      for (const [k, v] of Object.entries(filterParams(params))) {
        if (!body.has(k)) body.set(k, v)
      }
    }
    init.body = body
  } else if (method !== 'GET' && method !== 'DELETE') {
    const payload = { ...filterParams(params), ...(body ?? {}) }
    init.headers = { 'Content-Type': 'application/json' }
    init.body = JSON.stringify(payload)
  }

  try {
    const raw = await apiFetch<AjaxResponse<T>>(`${rel}${query}`, init)
    if (typeof raw === 'object' && raw !== null && 'success' in raw) {
      return normalizeAjaxResponse(raw as AjaxResponse<T>)
    }
    return { success: true, data: raw as T }
  } catch (e) {
    const message = e instanceof Error ? e.message : getServerErrorMessage()
    return { success: false, message }
  }
}

export async function crmGet<T = unknown>(
  path: string,
  params?: Record<string, ParamValue>,
): Promise<AjaxResponse<T>> {
  return crmRequest<T>('GET', path, params)
}

export async function crmPost<T = unknown>(
  path: string,
  params?: Record<string, ParamValue>,
): Promise<AjaxResponse<T>> {
  return crmRequest<T>('POST', path, params)
}

export async function crmPatch<T = unknown>(
  path: string,
  params?: Record<string, ParamValue>,
): Promise<AjaxResponse<T>> {
  return crmRequest<T>('PATCH', path, params)
}

export async function crmDelete<T = unknown>(
  path: string,
  params?: Record<string, ParamValue>,
): Promise<AjaxResponse<T>> {
  return crmRequest<T>('DELETE', path, params)
}

export async function crmPostForm<T = unknown>(
  path: string,
  formData: FormData,
  params?: Record<string, ParamValue>,
  method: 'POST' | 'PATCH' = 'POST',
): Promise<AjaxResponse<T>> {
  return crmRequest<T>(method, path, params, formData)
}

/** Normalize legacy full REST paths to webinocrm/v1 relative paths. */
function normalizeRestPath(path: string): string {
  return path
    .replace(/^https?:\/\/[^/]+\/wp-json\/webinocrm\/v1\//, '')
    .replace(/^\/wp-json\/webinocrm\/v1\//, '')
    .replace(/^webinocrm\/v1\//, '')
    .replace(/^\//, '')
}

/** @deprecated Use crmPost — kept for warehouse pages during migration */
export async function apiPost<T = unknown>(
  path: string,
  params: Record<string, ParamValue> = {},
): Promise<AjaxResponse<T>> {
  return crmPost<T>(normalizeRestPath(path), params)
}

/** @deprecated Use crmGet */
export async function apiGet<T = unknown>(
  path: string,
  params: Record<string, ParamValue> = {},
): Promise<AjaxResponse<T>> {
  return crmGet<T>(normalizeRestPath(path), params)
}

export function getConfigOrNull() {
  try {
    const c = getConfig()
    const wd = window.webinocrm ?? window.webinoDashboard
    return {
      nonce: c.nonce,
      restUrl: (wd as { restUrl?: string })?.restUrl ?? c.restUrl,
      restNonce: (wd as { nonce?: string })?.nonce ?? c.nonce,
      user: (wd as { user?: CrmUser })?.user,
      isRtl: (wd as { isRtl?: boolean })?.isRtl,
    }
  } catch {
    return window.webinocrm ?? null
  }
}

export async function restGetJson<T>(rel: string): Promise<{ ok: boolean; data?: T; message?: string }> {
  try {
    const data = await apiFetch<T>(normalizeRestPath(rel))
    return { ok: true, data }
  } catch (e) {
    const message = e instanceof Error ? e.message : getServerErrorMessage()
    return { ok: false, message }
  }
}

export async function restPostJson<T>(
  rel: string,
  body?: unknown,
): Promise<{ ok: boolean; data?: T; message?: string }> {
  try {
    const data = await apiFetch<T>(normalizeRestPath(rel), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body ?? {}),
    })
    return { ok: true, data }
  } catch (e) {
    const message = e instanceof Error ? e.message : getServerErrorMessage()
    return { ok: false, message }
  }
}
