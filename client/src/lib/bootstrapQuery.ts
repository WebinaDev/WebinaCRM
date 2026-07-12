import type { QueryClient } from '@tanstack/react-query'

import type { BootstrapPayload } from '@/types/modules'

export const BOOTSTRAP_QUERY_KEY = ['bootstrap'] as const

/** Coerce wp_localize_script / REST shapes into a string capability list. */
export function normalizeCapabilities(raw: unknown): string[] {
  if (!Array.isArray(raw)) {
    return []
  }
  return raw.filter((c): c is string => typeof c === 'string' && c.length > 0)
}

export function normalizeBootstrapPayload(data: BootstrapPayload): BootstrapPayload {
  return {
    ...data,
    capabilities: normalizeCapabilities(data.capabilities),
  }
}

export function getBootstrapSnapshot(): BootstrapPayload | undefined {
  const raw = window.webinoDashboard.bootstrap
  if (!raw) {
    return undefined
  }
  return normalizeBootstrapPayload(raw)
}

export function patchBootstrapQuery(
  qc: QueryClient,
  patch: Partial<Pick<BootstrapPayload, 'uiTheme' | 'uiAccent'>>,
) {
  qc.setQueryData<BootstrapPayload>(BOOTSTRAP_QUERY_KEY, (prev) =>
    prev ? { ...prev, ...patch } : prev,
  )
}
