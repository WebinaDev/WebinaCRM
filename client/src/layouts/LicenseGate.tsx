import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'

import { Skeleton } from '@/components/ui/skeleton'
import { useBootstrapQuery } from '@/hooks/useBootstrapQuery'

function LicenseLoading() {
  return (
    <div className="flex min-h-[40vh] flex-col gap-3 p-6" aria-busy="true">
      <Skeleton className="h-8 w-48 max-w-full" />
      <Skeleton className="h-32 w-full max-w-full" />
    </div>
  )
}

/**
 * Blocks dashboard chrome until bootstrap license state is known; redirects inactive licenses.
 */
export function LicenseGate({ children }: { children?: ReactNode }) {
  const loc = useLocation()
  const bq = useBootstrapQuery()

  const path = (loc.pathname.replace(/\/$/, '') || '/').replace(/^\/+/, '')
  if (path === 'license' || path.startsWith('license/')) {
    return children ? <>{children}</> : <Outlet />
  }

  if (bq.isPending || bq.data === undefined) {
    return <LicenseLoading />
  }

  const lic = bq.data?.license ?? window.webinoDashboard?.license
  if (lic && !lic.active && !lic.demo) {
    return <Navigate to="/license" replace />
  }

  return children ? <>{children}</> : <Outlet />
}
