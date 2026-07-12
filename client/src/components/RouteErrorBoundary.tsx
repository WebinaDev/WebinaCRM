import { Component, type ErrorInfo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { Button } from '@/components/ui/button'
import { normalizeCapabilities } from '@/lib/bootstrapQuery'

const ROUTE_ERROR_STORAGE_KEY = 'webino_dashboard_last_route_error'

type Props = {
  children: ReactNode
}

type State = {
  error: Error | null
}

function shouldShowRouteErrorDetails(): boolean {
  if (import.meta.env.DEV) {
    return true
  }
  try {
    if (new URLSearchParams(window.location.search).has('wd_debug')) {
      return true
    }
  } catch {
    /* ignore */
  }
  const caps = normalizeCapabilities(window.webinoDashboard?.bootstrap?.capabilities)
  return caps.includes('manage_options')
}

function persistRouteError(error: Error, componentStack: string | null | undefined) {
  try {
    const payload = {
      message: error.message,
      stack: error.stack ?? '',
      componentStack: componentStack ?? '',
      version: window.webinoDashboard?.version ?? '',
      assetVersion: window.webinoDashboard?.assetVersion ?? '',
      path: window.location.pathname,
      at: new Date().toISOString(),
    }
    sessionStorage.setItem(ROUTE_ERROR_STORAGE_KEY, JSON.stringify(payload))
  } catch {
    /* ignore quota / private mode */
  }
}

function RouteErrorFallback({ error, onRetry }: { error: Error | null; onRetry: () => void }) {
  const { t } = useTranslation()
  const showDetails = shouldShowRouteErrorDetails()

  return (
    <div className="flex flex-1 flex-col gap-3 p-6">
      <h1 className="text-lg font-semibold">{t('errors.routeTitle')}</h1>
      <p className="text-sm text-muted-foreground">{t('errors.routeBody')}</p>
      {error && showDetails ? (
        <pre className="max-h-40 overflow-auto rounded-md bg-muted p-3 text-xs break-words whitespace-pre-wrap">
          {error.message}
        </pre>
      ) : null}
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" size="sm" onClick={onRetry}>
          {t('errors.reloadPage')}
        </Button>
        <Button type="button" variant="ghost" size="sm" asChild>
          <Link to="/">{t('errors.backHome')}</Link>
        </Button>
      </div>
    </div>
  )
}

export class RouteErrorBoundary extends Component<Props, State> {
  state: State = { error: null }

  static getDerivedStateFromError(error: Error): State {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('[Webino Dashboard] Route render failed', error, info.componentStack)
    persistRouteError(error, info.componentStack)
  }

  handleRetry = () => {
    window.location.reload()
  }

  render() {
    if (this.state.error) {
      return <RouteErrorFallback error={this.state.error} onRetry={this.handleRetry} />
    }
    return this.props.children
  }
}

export function ChunkLoadFallback() {
  const { t } = useTranslation()

  return (
    <div className="flex flex-1 flex-col gap-3 p-6">
      <h1 className="text-lg font-semibold">{t('errors.chunkTitle')}</h1>
      <p className="text-sm text-muted-foreground">{t('errors.chunkBody')}</p>
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" size="sm" onClick={() => window.location.reload()}>
          {t('errors.reloadPage')}
        </Button>
        <Button type="button" variant="ghost" size="sm" asChild>
          <Link to="/">{t('errors.backHome')}</Link>
        </Button>
      </div>
    </div>
  )
}
