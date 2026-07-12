import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

import App from '@/App.tsx'
import { i18nReady } from '@/i18n'
import { showBootError } from '@/lib/bootError'
import '@/index.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, refetchOnWindowFocus: false },
    mutations: { retry: 0 },
  },
})

function mountApp() {
  const el = document.getElementById('root')
  if (!el) {
    showBootError('Dashboard root element is missing.', '#root was not found in the page HTML.')
    return
  }

  createRoot(el).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <App />
      </QueryClientProvider>
    </StrictMode>,
  )
}

function boot() {
  if (typeof window.webinoDashboard === 'undefined') {
    showBootError(
      'Dashboard configuration failed to load.',
      'window.webinoDashboard is missing. Check that plugin assets are enqueued and the build exists on the server.',
    )
    return
  }

  // Legacy CRM pages still read window.webinocrm in places.
  if (typeof window.webinocrm === 'undefined') {
    const wd = window.webinoDashboard
    const boot = (wd.bootstrap as Record<string, unknown> | undefined) ?? {}
    window.webinocrm = {
      ajaxUrl: String(wd.restUrl ?? ''),
      restUrl: String(wd.restUrl ?? ''),
      nonce: wd.ajaxNonce ?? wd.nonce,
      user: boot.user as typeof window.webinocrm extends { user?: infer U } ? U : never,
      primaryColor: '#845adf',
      isRtl: wd.isRtl,
    }
  }

  void i18nReady
    .then(() => {
      mountApp()
    })
    .catch((err: unknown) => {
      const msg = err instanceof Error ? err.message : String(err)
      console.error('[Webino Dashboard] i18n bootstrap failed', err)
      showBootError('Dashboard failed to start.', msg)
    })
}

window.addEventListener('error', (event) => {
  if (event.defaultPrevented) {
    return
  }
  const root = document.getElementById('root')
  if (!root || root.childElementCount > 0) {
    return
  }
  const msg = event.error instanceof Error ? event.error.message : event.message
  if (!msg) {
    return
  }
  console.error('[Webino Dashboard] Uncaught boot error', event.error ?? event.message)
  showBootError('Dashboard failed to load.', msg)
})

window.addEventListener('unhandledrejection', (event) => {
  const root = document.getElementById('root')
  if (!root || root.childElementCount > 0) {
    return
  }
  const reason = event.reason
  const msg = reason instanceof Error ? reason.message : String(reason)
  console.error('[Webino Dashboard] Unhandled rejection during boot', reason)
  showBootError('Dashboard failed to load.', msg)
})

boot()
