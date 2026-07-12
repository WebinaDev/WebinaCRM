import type { ReactNode } from "react"
import { useLocale } from "@/hooks/use-locale"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { cn } from "@/lib/utils"

type Props = {
  title: string
  description?: string
  actions?: ReactNode
  error?: string | null
  success?: string | null
  onDismissError?: () => void
  onDismissSuccess?: () => void
  children: ReactNode
  className?: string
}

export function AccountingPageLayout({
  title,
  description,
  actions,
  error,
  success,
  onDismissError,
  onDismissSuccess,
  children,
  className,
}: Props) {
  const { isRtl } = useLocale()

  return (
    <div className={cn("space-y-6", className)} dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader title={title} description={description} actions={actions} isRtl={isRtl} />
      <PmAlerts
        error={error}
        success={success}
        onDismissError={onDismissError}
        onDismissSuccess={onDismissSuccess}
      />
      {children}
    </div>
  )
}
