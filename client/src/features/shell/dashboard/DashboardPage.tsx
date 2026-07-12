import { useCallback } from "react"
import { getConfigOrNull } from "@/api/client"
import { useBootstrapQuery } from "@/hooks/useBootstrapQuery"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { DashboardSystemManager } from "./DashboardSystemManager"
import { DashboardTeamMember } from "./DashboardTeamMember"
import { DashboardClient } from "./DashboardClient"

export type { DashboardStats, ClientStats, TeamMemberStats } from "./types"

export function DashboardPage() {
  const { t } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const bq = useBootstrapQuery()
  const roleSlug = bq.data?.user?.role ?? getConfigOrNull()?.user?.roleSlug ?? "client"

  const handleChildError = useCallback(
    (message: string | null) => {
      if (message) setError(message)
      else setError(null)
    },
    [setError],
  )

  return (
    <CrmPageLayout title={t("pages.dashboard.داشبورد")} {...layoutProps}>
      {(roleSlug === "system_manager" ||
        roleSlug === "finance_manager" ||
        roleSlug === "administrator") && (
        <DashboardSystemManager onError={handleChildError} />
      )}
      {roleSlug === "team_member" && <DashboardTeamMember onError={handleChildError} />}
      {(roleSlug === "client" || roleSlug === "customer") && (
        <DashboardClient onError={handleChildError} />
      )}
    </CrmPageLayout>
  )
}
