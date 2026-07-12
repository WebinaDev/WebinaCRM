import { useQuery } from '@tanstack/react-query'

import { apiFetch } from '@/lib/api'

export type AuthSession = {
  logged_in: boolean
  user?: { id: number; login: string; name: string }
}

export function useAuthSession() {
  return useQuery({
    queryKey: ['auth', 'session'],
    queryFn: () => apiFetch<AuthSession>('auth/session'),
    staleTime: 300_000,
    retry: false,
    placeholderData: () => ({
      logged_in: window.webinoDashboard.isLogged,
    }),
  })
}
