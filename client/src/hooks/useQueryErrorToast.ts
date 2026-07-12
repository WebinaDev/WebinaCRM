import type { UseQueryResult } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { toast } from 'sonner'

/** Shows one toast per failed fetch (avoids duplicate toasts on strict mode / re-renders). */
export function useQueryErrorToast(q: Pick<UseQueryResult<unknown, Error>, 'isError' | 'error' | 'fetchStatus'>) {
  const shown = useRef(false)
  useEffect(() => {
    if (q.isError && q.error) {
      if (!shown.current) {
        shown.current = true
        toast.error(q.error.message)
      }
    } else {
      shown.current = false
    }
  }, [q.isError, q.error, q.fetchStatus])
}
