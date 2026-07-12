import type { ReactNode } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type Props = {
  children: ReactNode
  onApply?: () => void
  applyLabel: string
  isRtl?: boolean
  className?: string
}

export function PmFilterBar({ children, onApply, applyLabel, isRtl, className }: Props) {
  return (
    <Card dir={isRtl ? "rtl" : "ltr"}>
      <CardContent className={cn("pt-6 text-start", className)}>
        <div className="flex flex-wrap items-end gap-3 text-start">
          {children}
          {onApply ? (
            <Button type="button" onClick={onApply}>
              {applyLabel}
            </Button>
          ) : null}
        </div>
      </CardContent>
    </Card>
  )
}
