import { StatCardsSkeleton } from "@/components/skeletons"
import { useEffect, useState } from "react"
import { crmGet, getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { DashboardStatCard } from "./components/DashboardStatCard"
import type { TeamMemberStats } from "./types"
import { FolderOpen, CheckCircle, ListTodo, Headphones } from "lucide-react"

type Props = {
  onError?: (message: string | null) => void
}

export function DashboardTeamMember({ onError }: Props) {
  const { t, isRtl, formatNumber } = useLocale()
  const [stats, setStats] = useState<TeamMemberStats | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    onError?.(null)
    crmGet<TeamMemberStats>("dashboard/team-stats")
      .then((res) => {
        if (cancelled) return
        if (res.success && res.data) {
          setStats(res.data)
        } else {
          setStats(null)
          onError?.(getAjaxMessage(res) ?? t("pages.dashboard.load_error"))
        }
      })
      .catch(() => {
        if (!cancelled) onError?.(t("pages.dashboard.load_error"))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [onError])

  const s = stats ?? {
    total_tasks: 0,
    completed_tasks: 0,
    in_progress_tasks: 0,
    overdue_tasks: 0,
    today_tasks: 0,
    total_projects: 0,
    completion_rate: 0,
    total_tickets: 0,
    open_tickets: 0,
  }

  if (loading) {
    return <StatCardsSkeleton count={3} />
  }

  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3" dir={isRtl ? "rtl" : "ltr"}>
      <DashboardStatCard
        title={t("pages.dashboard.وظایف_من")}
        value={formatNumber(s.total_tasks)}
        description={
          s.completion_rate != null
            ? `${formatNumber(Math.round(s.completion_rate))}% ${t("pages.dashboard.انجام_شده")}`
            : undefined
        }
        icon={ListTodo}
      />
      <DashboardStatCard
        title={t("pages.dashboard.وظایف_انجامشده")}
        value={formatNumber(s.completed_tasks)}
        icon={CheckCircle}
      />
      <DashboardStatCard
        title={t("pages.dashboard.وظایف_در_حال_انجام")}
        value={formatNumber(s.in_progress_tasks)}
        icon={FolderOpen}
      />
      <DashboardStatCard
        title={t("pages.dashboard.وظایف_معوق")}
        value={formatNumber(s.overdue_tasks)}
        icon={ListTodo}
      />
      <DashboardStatCard
        title={t("pages.dashboard.پروژههای_من")}
        value={formatNumber(s.total_projects)}
        icon={FolderOpen}
      />
      <DashboardStatCard
        title={t("pages.dashboard.تیکتهای_باز")}
        value={formatNumber(s.open_tickets)}
        icon={Headphones}
      />
    </div>
  )
}
