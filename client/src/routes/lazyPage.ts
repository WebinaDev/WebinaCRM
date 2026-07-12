import { lazy, type ComponentType, type LazyExoticComponent } from 'react'

import { ChunkLoadFallback } from '@/components/RouteErrorBoundary'

type PageModule = { default: ComponentType }

function isChunkLoadError(err: unknown): boolean {
  const msg =
    err instanceof Error
      ? err.message
      : typeof err === 'string'
        ? err
        : ''
  const lower = msg.toLowerCase()
  return (
    lower.includes('export named') ||
    lower.includes('failed to fetch') ||
    lower.includes('dynamically imported module') ||
    lower.includes('loading chunk') ||
    lower.includes('404') ||
    lower.includes('importing a module script failed')
  )
}

export function lazyPage(
  loader: () => Promise<PageModule>,
): LazyExoticComponent<ComponentType> {
  return lazy(() =>
    loader().catch((err: unknown) => {
      console.error('[Webino Dashboard] Failed to load page chunk', err)
      if (isChunkLoadError(err)) {
        console.warn(
          '[Webino Dashboard] Possible stale or partial deploy — hard refresh may be required.',
        )
      }
      return { default: ChunkLoadFallback }
    }),
  )
}

/** CRM pages use named exports instead of default. */
export function lazyNamedPage(
  loader: () => Promise<Record<string, ComponentType>>,
  exportName: string,
): LazyExoticComponent<ComponentType> {
  return lazyPage(async () => {
    const mod = await loader()
    const Comp = mod[exportName]
    if (!Comp) {
      throw new Error(`Page export not found: ${exportName}`)
    }
    return { default: Comp }
  })
}
