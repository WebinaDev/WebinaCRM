import { StatCardsSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { getModirPayamakDashboard, type ModirPayamakStats } from "@/api/modirpayamak"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { ModirPayamakQuickLinks } from "./components/ModirPayamakQuickLinks"

function formatCredit(credit: unknown, formatNumber: (n: number) => string): { balance: string; expiry: string } {
  let src: unknown = credit
  if (typeof src === "string") {
    try {
      src = JSON.parse(src) as unknown
    } catch {
      const raw = typeof credit === "string" ? credit.trim() : ""
      return { balance: raw || "—", expiry: "—" }
    }
  }
  if (Array.isArray(src) && src[0]) src = src[0]
  if (!src || typeof src !== "object") {
    if (typeof credit === "number") return { balance: formatNumber(credit), expiry: "—" }
    return { balance: "—", expiry: "—" }
  }
  const c = src as Record<string, unknown>
  const nested =
    c.data && typeof c.data === "object" && !Array.isArray(c.data)
      ? (c.data as Record<string, unknown>)
      : c
  const balance = nested.balance ?? nested.credit ?? nested.amount ?? nested.remaining ?? nested.reseller_credit
  const expiry = nested.expire_at ?? nested.expires_at ?? nested.expiry ?? nested.expire
  const balanceNum =
    typeof balance === "string" ? Number(balance.replace(/,/g, "")) : Number(balance as number)
  return {
    balance: Number.isFinite(balanceNum) ? formatNumber(balanceNum) : balance != null ? String(balance) : "—",
    expiry: expiry != null ? String(expiry) : "—",
  }
}

export function ModirPayamakDashboardPage() {
  const { t, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const [stats, setStats] = useState<ModirPayamakStats | null>(null)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getModirPayamakDashboard()
    if (res.success && res.data?.stats) {
      setStats(res.data.stats)
    } else {
      setStats(null)
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.loadError"))
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const settingsButton = (
    <Button variant="outline" asChild>
      <Link to="/admin/integrations/modirpayamak/settings">{t("pages.modirpayamak.settings")}</Link>
    </Button>
  )

  const credit = formatCredit(stats?.reseller_credit, formatNumber)

  if (loading) {
    return (
      <CrmPageLayout
        title={t("pages.modirpayamak.dashboardTitle")}
        description={t("pages.modirpayamak.dashboardDesc")}
        actions={settingsButton}
        {...layoutProps}
      >
        <StatCardsSkeleton count={4} />
      </CrmPageLayout>
    )
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.dashboardTitle")}
      description={t("pages.modirpayamak.dashboardDesc")}
      actions={settingsButton}
      {...layoutProps}
    >
      {!stats?.configured ? (
        <Alert>
          <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
            <span>{t("pages.modirpayamak.notConfigured")}</span>
            <Button variant="outline" size="sm" asChild>
              <Link to="/admin/integrations/modirpayamak/settings">{t("pages.modirpayamak.settings")}</Link>
            </Button>
          </AlertDescription>
        </Alert>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">{t("pages.modirpayamak.customers")}</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-bold">{formatNumber(stats?.total_customers ?? 0)}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">{t("pages.modirpayamak.sentToday")}</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-bold">{formatNumber(stats?.sent_today ?? 0)}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">{t("pages.modirpayamak.pendingOrders")}</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-bold">{formatNumber(stats?.pending_orders ?? 0)}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">{t("pages.modirpayamak.pricePerUnit")}</CardTitle>
          </CardHeader>
          <CardContent className="text-2xl font-bold">{formatNumber(stats?.price_per_unit ?? 0)}</CardContent>
        </Card>
      </div>

      {stats?.reseller_credit ? (
        <Card>
          <CardHeader>
            <CardTitle>{t("pages.modirpayamak.resellerCredit")}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <div>
              <p className="text-sm text-muted-foreground">{t("pages.modirpayamak.balance")}</p>
              <p className="text-2xl font-semibold">{credit.balance}</p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">{t("pages.modirpayamak.creditExpiry")}</p>
              <p className="text-lg font-medium">{credit.expiry}</p>
            </div>
          </CardContent>
        </Card>
      ) : null}

      <ModirPayamakQuickLinks />
    </CrmPageLayout>
  )
}
