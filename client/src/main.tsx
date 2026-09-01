import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

import App from '@/App.tsx'
import { i18nReady } from '@/i18n'
import { bootI18n, showBootError } from '@/lib/bootError'
import '@/index.css'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, refetchOnWindowFocus: false },
    mutations: { retry: 0 },
  },
})

function appTree() {
  return (
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <App />
      </QueryClientProvider>
    </StrictMode>
  )
}

function mountApp() {
  const el = document.getElementById('root')
  if (!el) {
    showBootError(bootI18n('errors.boot.missingRoot'), bootI18n('errors.boot.missingRootDetail'))
    return
  }

  const tree = appTree()
  const ssrChrome = el.querySelector('[data-wd-ssr]')
  if (ssrChrome) {
    createRoot(el).render(tree)
    return
  }

  createRoot(el).render(tree)
}

function boot() {
  if (typeof window.webinoDashboard === 'undefined') {
    showBootError(bootI18n('errors.boot.missingConfig'), bootI18n('errors.boot.missingConfigDetail'))
    return
  }

  if (typeof window.webinocrm === 'undefined') {
    const wd = window.webinoDashboard
    const bootSnap = (wd.bootstrap as Record<string, unknown> | undefined) ?? {}
    window.webinocrm = {
      ajaxUrl: String(wd.restUrl ?? ''),
      restUrl: String(wd.restUrl ?? ''),
      nonce: wd.ajaxNonce ?? wd.nonce,
      user: bootSnap.user as typeof window.webinocrm extends { user?: infer U } ? U : never,
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
      showBootError(bootI18n('errors.boot.i18nFailed'), msg)
    })
}

function isDashboardBooted(): boolean {
  const root = document.getElementById('root')
  if (!root) {
    return false
  }
  if (document.getElementById('wd-shell-loader')) {
    return false
  }
  if (root.querySelector('[data-wd-ssr]')) {
    return false
  }
  if (root.querySelector('[role="alert"]')) {
    return true
  }
  return root.childElementCount > 0
}

window.addEventListener('error', (event) => {
  if (event.defaultPrevented || isDashboardBooted()) {
    return
  }
  const msg = event.error instanceof Error ? event.error.message : event.message
  if (!msg) {
    return
  }
  console.error('[Webino Dashboard] Uncaught boot error', event.error ?? event.message)
  showBootError(bootI18n('errors.boot.loadFailed'), msg)
})

window.addEventListener('unhandledrejection', (event) => {
  if (isDashboardBooted()) {
    return
  }
  const reason = event.reason
  const msg = reason instanceof Error ? reason.message : String(reason)
  console.error('[Webino Dashboard] Unhandled rejection during boot', reason)
  showBootError(bootI18n('errors.boot.loadFailed'), msg)
})

boot()
