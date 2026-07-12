import type { ReactNode } from 'react'
import { Suspense, useEffect } from 'react'
import { BrowserRouter, Navigate, Outlet, Route, Routes } from 'react-router-dom'

import { PermissionGate } from '@/components/PermissionGate'
import { RouteErrorBoundary } from '@/components/RouteErrorBoundary'
import { DashboardSkeleton, RoutePageSkeleton } from '@/components/skeletons'
import { useAuthSession } from '@/hooks/useAuthSession'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import NotFoundPage from '@/pages/NotFoundPage'
import { Toaster } from '@/components/ui/sonner'
import { lazyNamedPage, lazyPage } from '@/routes/lazyPage'
import { dashboardRoutes } from '@/routes/routes.config'
import { ThemeProvider } from '@/theme/ThemeProvider'

const CrmHomePage = lazyNamedPage(() => import('@/pages/crm/dashboard-page'), 'DashboardPage')
const LoginPage = lazyPage(() =>
  import('@/pages/LoginPage').then((m) => ({ default: m.LoginPage })),
)

function dashboardBasename(): string {
  try {
    const u = new URL(window.webinoDashboard.baseUrl)
    const p = u.pathname.replace(/\/$/, '')
    return p || '/'
  } catch {
    return '/dashboard'
  }
}

function AuthLoading() {
  return <DashboardSkeleton compact showPageHeader={false} />
}

function RouteFallback() {
  return <RoutePageSkeleton />
}

function ProtectedRoute() {
  const session = useAuthSession()

  if (session.isPending && !session.data) {
    return <AuthLoading />
  }

  if (!session.data?.logged_in) {
    return <Navigate to="/login" replace />
  }

  return <Outlet />
}

function GuardLogin({ children }: { children: ReactNode }) {
  const session = useAuthSession()

  if (session.isPending && !session.data) {
    return <AuthLoading />
  }

  if (session.data?.logged_in) {
    return <Navigate to="/" replace />
  }

  return <>{children}</>
}

export default function App() {
  const base = dashboardBasename()

  useEffect(() => {
    const loader = document.getElementById('wd-shell-loader')
    if (loader) loader.remove()
  }, [])

  return (
    <ThemeProvider>
      <BrowserRouter basename={base}>
        <Routes>
          <Route
            path="/login"
            element={
              <GuardLogin>
                <Suspense fallback={<AuthLoading />}>
                  <LoginPage />
                </Suspense>
              </GuardLogin>
            }
          />
          <Route element={<ProtectedRoute />}>
            <Route element={<DashboardLayout />}>
              <Route
                index
                element={
                  <RouteErrorBoundary>
                    <PermissionGate capability="webinocrm_route_home">
                      <Suspense fallback={<RouteFallback />}>
                        <CrmHomePage />
                      </Suspense>
                    </PermissionGate>
                  </RouteErrorBoundary>
                }
              />
              {dashboardRoutes.map(({ path, capability, Component }) => (
                <Route
                  key={path}
                  path={path}
                  element={
                    <RouteErrorBoundary key={path}>
                      <PermissionGate capability={capability}>
                        <Suspense fallback={<RouteFallback />}>
                          <Component />
                        </Suspense>
                      </PermissionGate>
                    </RouteErrorBoundary>
                  }
                />
              ))}
              <Route path="settings/authentication" element={<Navigate to="admin/settings/general/authentication" replace />} />
              <Route path="settings/style" element={<Navigate to="admin/settings/general/style" replace />} />
              <Route path="settings/visitor_statistics" element={<Navigate to="admin/settings/general/visitor-tracking" replace />} />
              <Route path="settings/workflow" element={<Navigate to="admin/settings/projects/workflow" replace />} />
              <Route path="settings/positions" element={<Navigate to="admin/settings/projects/positions" replace />} />
              <Route path="settings/task_categories" element={<Navigate to="admin/settings/projects/task_categories" replace />} />
              <Route path="settings/automations" element={<Navigate to="admin/settings/projects/automations" replace />} />
              <Route path="settings/sms" element={<Navigate to="admin/settings/crm/sms" replace />} />
              <Route path="settings/notifications" element={<Navigate to="admin/settings/crm/notifications" replace />} />
              <Route path="settings/canned_responses" element={<Navigate to="admin/settings/crm/canned_responses" replace />} />
              <Route path="settings/forms" element={<Navigate to="admin/settings/crm/forms" replace />} />
              <Route path="settings/leads" element={<Navigate to="admin/settings/crm/leads" replace />} />
              <Route path="settings/payment" element={<Navigate to="admin/settings/accounting/payment" replace />} />
              <Route path="bots/business" element={<Navigate to="admin/integrations/bale" replace />} />
              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Route>
        </Routes>
      </BrowserRouter>
      <Toaster position="top-center" />
    </ThemeProvider>
  )
}
