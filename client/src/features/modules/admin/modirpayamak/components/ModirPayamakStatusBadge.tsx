import { useTranslation } from "react-i18next"

import { Badge } from "@/components/ui/badge"
import { translateModirPayamakStatus } from "@/lib/enumLabels"
import { cn } from "@/lib/utils"

type Props = {
  status: string
  className?: string
}

function tone(status: string): string {
  const s = status.toLowerCase()
  if (["active", "approved", "delivered", "sent", "paid", "success", "done", "closed", "synced"].some((k) => s.includes(k))) {
    return "bg-success/15 text-success border-success/30"
  }
  if (["pending", "waiting", "open", "processing", "draft"].some((k) => s.includes(k))) {
    return "bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30"
  }
  if (["failed", "rejected", "cancelled", "inactive", "expired", "error"].some((k) => s.includes(k))) {
    return "bg-destructive/15 text-destructive border-destructive/30"
  }
  return "bg-muted text-muted-foreground"
}

export function ModirPayamakStatusBadge({ status, className }: Props) {
  const { t } = useTranslation()
  const label = translateModirPayamakStatus(t, status)
  return (
    <Badge variant="outline" className={cn("font-normal", tone(status), className)}>
      {label}
    </Badge>
  )
}
