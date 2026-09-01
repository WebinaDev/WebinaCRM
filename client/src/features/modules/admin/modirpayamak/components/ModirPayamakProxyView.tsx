/**
 * Legacy raw Edge proxy playground — gated off; use dedicated ModirPayamak pages instead.
 */
export function ModirPayamakProxyView(_props: {
  title: string
  description?: string
  method: string
  path: string
  body?: unknown
  query?: Record<string, string>
}) {
  if (!import.meta.env.DEV) return null
  return null
}
