import { useState, useCallback, useMemo } from "react"
import { getAjaxMessage, type AjaxResponse } from "@/api/client"

export function useCrmFeedback() {
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const dismissError = useCallback(() => setError(null), [])
  const dismissSuccess = useCallback(() => setSuccess(null), [])

  const applyResponse = useCallback(
    (res: AjaxResponse<unknown>, options?: { successMessage?: string; errorFallback?: string }) => {
      if (res.success) {
        setError(null)
        if (options?.successMessage) setSuccess(options.successMessage)
        return true
      }
      setSuccess(null)
      setError(getAjaxMessage(res, options?.errorFallback) ?? options?.errorFallback ?? "")
      return false
    },
    [],
  )

  const layoutProps = useMemo(
    () => ({
      error,
      success,
      onDismissError: dismissError,
      onDismissSuccess: dismissSuccess,
    }),
    [error, success, dismissError, dismissSuccess],
  )

  return {
    error,
    success,
    setError,
    setSuccess,
    dismissError,
    dismissSuccess,
    applyResponse,
    layoutProps,
  }
}
