import { useQuery } from '@tanstack/react-query'

import { apiFetch } from '@/lib/api'
import {
  BOOTSTRAP_QUERY_KEY,
  getBootstrapSnapshot,
  normalizeBootstrapPayload,
} from '@/lib/bootstrapQuery'
import type { BootstrapPayload } from '@/types/modules'

export function useBootstrapQuery() {
  const initial = getBootstrapSnapshot()

  return useQuery({
    queryKey: BOOTSTRAP_QUERY_KEY,
    queryFn: async () => {
      const data = await apiFetch<BootstrapPayload>('bootstrap')
      return normalizeBootstrapPayload(data)
    },
    initialData: initial,
    staleTime: 300_000,
    gcTime: 600_000,
    placeholderData: (prev) => prev ?? initial,
    refetchOnMount: initial ? false : true,
  })
}
