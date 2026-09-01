export type SsrPagePayload = {
  path?: string
  route?: string
  generated?: number
}

export function getSsrPage(): SsrPagePayload | undefined {
  const page = window.webinoDashboard?.page
  if (!page || typeof page !== 'object') {
    return undefined
  }
  return page as SsrPagePayload
}
