/* Webino Dashboard — cache only non-JS assets; JS/CSS always network (hashed chunk names). */
const swUrl = new URL(self.location.href)
const BUILD_ID = swUrl.searchParams.get('v') || '1'
const CACHE = 'webino-dashboard-' + BUILD_ID

self.addEventListener('install', (event) => {
  self.skipWaiting()
  event.waitUntil(caches.open(CACHE).then(() => Promise.resolve()))
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.map((key) => {
            if (key === CACHE) return Promise.resolve()
            return caches.delete(key)
          }),
        ),
      )
      .then(() => self.clients.claim()),
  )
})

function isBuildAsset(url) {
  return url.pathname.includes('/assets/dashboard-build/')
}

function isJsOrCss(url) {
  const p = url.pathname
  return p.endsWith('.js') || p.endsWith('.css')
}

self.addEventListener('fetch', (event) => {
  const req = event.request
  if (req.method !== 'GET') return
  const url = new URL(req.url)
  if (!isBuildAsset(url)) return

  if (isJsOrCss(url)) {
    event.respondWith(
      fetch(req, { cache: 'reload' }).catch(() => fetch(req)),
    )
    return
  }

  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached
      return fetch(req).then((res) => {
        const copy = res.clone()
        if (res.ok) {
          caches.open(CACHE).then((cache) => cache.put(req, copy))
        }
        return res
      })
    }),
  )
})
