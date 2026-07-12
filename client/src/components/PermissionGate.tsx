import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { useBootstrapQuery } from '@/hooks/useBootstrapQuery'
import { normalizeCapabilities } from '@/lib/bootstrapQuery'

export function PermissionGate({ capability, children }: { capability: string; children: ReactNode }) {
  const { t } = useTranslation()
  const q = useBootstrapQuery()

  if (q.isLoading) {
    return <div className="p-6 text-sm text-muted-foreground">{t('common.loading')}</div>
  }

  if (q.isError) {
    return (
      <div className="p-6">
        <h1 className="text-lg font-semibold">{t('errors.bootstrapTitle')}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{t('errors.bootstrapBody')}</p>
      </div>
    )
  }

  const caps = normalizeCapabilities(q.data?.capabilities)
  if (!caps.length || !caps.includes(capability)) {
    return (
      <div className="p-6">
        <h1 className="text-lg font-semibold">{t('errors.forbiddenTitle')}</h1>
        <p className="mt-2 text-sm text-muted-foreground">{t('errors.forbiddenBody')}</p>
      </div>
    )
  }

  return <>{children}</>
}
