import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { ListPageSkeleton } from '@/components/skeletons'
import { useBootstrapQuery } from '@/hooks/useBootstrapQuery'
import { getBootstrapSnapshot, normalizeCapabilities } from '@/lib/bootstrapQuery'

/**
 * Client-side capability gate for navigation UX only.
 * REST endpoints enforce permissions server-side.
 */
export function PermissionGate({ capability, children }: { capability: string | string[]; children: ReactNode }) {
  const { t } = useTranslation()
  const q = useBootstrapQuery()
  const boot = q.data ?? getBootstrapSnapshot()

  if ((q.isPending || q.isLoading) && !boot) {
    return (
      <div className="py-6">
        <ListPageSkeleton />
      </div>
    )
  }

  if (q.isError && !boot) {
    return (
      <div className="p-6">
        <h1 className="text-lg font-semibold">{t('errors.bootstrapTitle')}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{t('errors.bootstrapBody')}</p>
      </div>
    )
  }

  const capsKnown = boot != null && Array.isArray(boot.capabilities)
  if (capsKnown) {
    const caps = normalizeCapabilities(boot.capabilities)
    const needed = Array.isArray(capability) ? capability : [capability]
    const allowed = needed.some((c) => caps.includes(c))
    if (!caps.length || !allowed) {
      return (
        <div className="p-6">
          <h1 className="text-lg font-semibold">{t('errors.forbiddenTitle')}</h1>
          <p className="mt-2 text-sm text-muted-foreground">{t('errors.forbiddenBody')}</p>
        </div>
      )
    }
  }

  return <>{children}</>
}
