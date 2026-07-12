import { ChevronDown } from 'lucide-react'
import type { ReactNode } from 'react'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { cn } from '@/lib/utils'

type OrderSidebarPanelProps = {
  title: string
  defaultOpen?: boolean
  children: ReactNode
  className?: string
}

export function OrderSidebarPanel({ title, defaultOpen = true, children, className }: OrderSidebarPanelProps) {
  return (
    <Collapsible defaultOpen={defaultOpen}>
      <Card className={cn('shadow-sm', className)}>
        <CollapsibleTrigger asChild>
          <CardHeader className="flex cursor-pointer flex-row items-center justify-between space-y-0 pb-3 text-start">
            <CardTitle className="text-sm font-semibold">{title}</CardTitle>
            <ChevronDown className="text-muted-foreground size-4 shrink-0 transition-transform [[data-state=open]_&]:rotate-180" aria-hidden />
          </CardHeader>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <CardContent className="pt-0 text-start">{children}</CardContent>
        </CollapsibleContent>
      </Card>
    </Collapsible>
  )
}
