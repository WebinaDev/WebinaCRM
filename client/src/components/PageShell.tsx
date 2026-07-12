import type { ReactNode } from 'react'

import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function PageShell({ title, description, children }: { title: string; description?: string; children?: ReactNode }) {
  return (
    <div className="space-y-4">
      <Card className="shadow-sm">
        <CardHeader className="pb-4">
          <CardTitle className="text-xl">{title}</CardTitle>
          {description ? <CardDescription>{description}</CardDescription> : null}
        </CardHeader>
      </Card>
      {children}
    </div>
  )
}
