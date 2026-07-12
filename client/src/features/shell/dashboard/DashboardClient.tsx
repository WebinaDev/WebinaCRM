import { StatCardsSkeleton } from "@/components/skeletons"
import { useEffect, useState } from "react"
import { crmGet, getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { DashboardStatCard } from "./components/DashboardStatCard"
import type { ClientStats } from "./types"
import { FolderOpen, CheckCircle, ListTodo, Headphones, TrendingUp } from "lucide-react"

type Props = {
  onError?: (message: string | null) => void
}

export function DashboardClient({ onError }: Props) {
  const { t, isRtl, formatNumber } = useLocale()
  const [stats, setStats] = useState<ClientStats | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    onError?.(null)
    crmGet<ClientStats>("dashboard/client-stats")
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
    total_projects: 0,
    active_projects: 0,
    completed_projects: 0,
    total_tickets: 0,
    open_tickets: 0,
    total_contracts: 0,
    total_value: 0,
    paid_amount: 0,
    pending_amount: 0,
  }

  if (loading) {
    return <StatCardsSkeleton count={3} />
  }

  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3" dir={isRtl ? "rtl" : "ltr"}>
      <DashboardStatCard
        title={t("pages.dashboard.پروژههای_من")}
        value={formatNumber(s.total_projects)}
        description={
          s.active_projects != null
            ? `${formatNumber(s.active_projects)} ${t("common.active")}`
            : undefined
        }
        icon={FolderOpen}
      />
      <DashboardStatCard
        title={t("pages.dashboard.قراردادها")}
        value={formatNumber(s.total_contracts)}
        icon={CheckCircle}
      />
      <DashboardStatCard
        title={t("pages.dashboard.تیکتهای_باز")}
        value={formatNumber(s.open_tickets)}
        icon={Headphones}
      />
      <DashboardStatCard
        title={t("pages.dashboard.مبلغ_کل_قراردادها")}
        value={formatNumber(s.total_value)}
        icon={TrendingUp}
      />
      <DashboardStatCard
        title={t("pages.dashboard.پرداختشده")}
        value={formatNumber(s.paid_amount)}
        icon={CheckCircle}
      />
      <DashboardStatCard
        title={t("pages.dashboard.مانده_پرداخت")}
        value={formatNumber(s.pending_amount)}
        icon={ListTodo}
      />
    </div>
  )
}
