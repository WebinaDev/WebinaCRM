function cfg() {
  return window.webinoDashboard
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const c = cfg()
  const url = path.startsWith('http') ? path : c.restUrl + path.replace(/^\//, '')
  const headers: Record<string, string> = {
    ...(init.headers as Record<string, string>),
  }
  const hasNonceHeader = Object.keys(headers).some((k) => k.toLowerCase() === 'x-wp-nonce')
  if (c.nonce && !hasNonceHeader) {
    headers['X-WP-Nonce'] = c.nonce
  }
  const res = await fetch(url, {
    ...init,
    credentials: 'same-origin',
    headers,
  })
  const data = (await res.json().catch(() => ({}))) as T & { message?: string; error?: string }
  if (!res.ok) {
    const errBody = data as { message?: string; error?: string; code?: string }
    const msg =
      typeof errBody.message === 'string'
        ? errBody.message
        : typeof errBody.error === 'string'
          ? errBody.error
          : errBody.code || res.statusText
    throw new Error(msg)
  }
  return data as T
}

export async function apiUploadFile(
  path: string,
  file: File,
  fields?: Record<string, string>,
): Promise<{ id: number; url: string }> {
  const c = cfg()
  const url = path.startsWith('http') ? path : c.restUrl + path.replace(/^\//, '')
  const body = new FormData()
  body.append('file', file)
  if (fields) {
    for (const [key, value] of Object.entries(fields)) {
      body.append(key, value)
    }
  }
  const headers: HeadersInit = {}
  if (c.nonce) {
    ;(headers as Record<string, string>)['X-WP-Nonce'] = c.nonce
  }
  const res = await fetch(url, { method: 'POST', credentials: 'same-origin', body, headers })
  const data = (await res.json().catch(() => ({}))) as { id?: number; url?: string; message?: string }
  if (!res.ok) {
    const msg = typeof data.message === 'string' ? data.message : res.statusText
    throw new Error(msg)
  }
  return { id: data.id ?? 0, url: data.url ?? '' }
}
