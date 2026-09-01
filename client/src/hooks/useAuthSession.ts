import { useQuery } from '@tanstack/react-query'

import { apiFetch } from '@/lib/api'

export type AuthSession = {
  logged_in: boolean
  user?: { id: number; login: string; name: string }
}

export const AUTH_SESSION_QUERY_KEY = ['auth', 'session'] as const

export function useAuthSession() {
  const isLogged = Boolean(window.webinoDashboard?.isLogged)

  return useQuery({
    queryKey: AUTH_SESSION_QUERY_KEY,
    queryFn: () => apiFetch<AuthSession>('auth/session'),
    staleTime: 300_000,
    retry: false,
    initialData: { logged_in: isLogged },
    initialDataUpdatedAt: Date.now(),
  })
}
