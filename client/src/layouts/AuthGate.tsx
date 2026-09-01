import type { ReactNode } from 'react'
import { Navigate, Outlet } from 'react-router-dom'

import { Skeleton } from '@/components/ui/skeleton'
import { useAuthSession } from '@/hooks/useAuthSession'

function AuthLoading() {
  return (
    <div className="flex min-h-[40vh] flex-col gap-3 p-6" aria-busy="true">
      <Skeleton className="h-8 w-48 max-w-full" />
      <Skeleton className="h-32 w-full max-w-full" />
    </div>
  )
}

type AuthGateMode = 'protected' | 'guest'

/**
 * Single auth gate for protected routes and guest-only login.
 */
export function AuthGate({ mode, children }: { mode: AuthGateMode; children?: ReactNode }) {
  const session = useAuthSession()

  if (session.isPending || session.data === undefined) {
    return <AuthLoading />
  }

  const loggedIn = Boolean(session.data?.logged_in)

  if (mode === 'protected' && !loggedIn) {
    return <Navigate to="/login" replace />
  }
  if (mode === 'guest' && loggedIn) {
    return <Navigate to="/" replace />
  }

  return children ? <>{children}</> : <Outlet />
}
