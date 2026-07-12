function cfg() {
  return window.webinoDashboard
}

/** POST multipart (e.g. CSV); do not set Content-Type so the browser sets the boundary. */
export async function apiPostFormData<T>(path: string, form: FormData): Promise<T> {
  const c = cfg()
  const url = path.startsWith('http') ? path : c.restUrl + path.replace(/^\//, '')
  const headers: HeadersInit = {}
  if (c.nonce) {
    ;(headers as Record<string, string>)['X-WP-Nonce'] = c.nonce
  }
  const res = await fetch(url, { method: 'POST', credentials: 'same-origin', body: form, headers })
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
