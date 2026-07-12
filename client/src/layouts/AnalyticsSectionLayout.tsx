import type { ReactNode } from 'react'

export function AnalyticsSectionLayout({ children }: { children?: ReactNode }) {
  return <div className="min-w-0 space-y-4">{children}</div>
}
