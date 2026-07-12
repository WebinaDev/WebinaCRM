import { DashboardSkeleton } from "@/components/skeletons"
import { useState, useEffect } from "react"
import { Link } from "react-router-dom"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { accountingFiscalYears, accountingJournalList } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import {
  BookOpen,
  FileText,
  Book,
  BarChart2,
  Calendar,
  Settings,
  Users,
  Package,
  ListOrdered,
  Wallet,
  Receipt,
  Banknote,
  Warehouse,
  Layers,
  ArrowDownToLine,
  ArrowUpFromLine,
  ClipboardList,
} from "lucide-react"
import { renderIcon } from "@/lib/react-icon"

const LINKS = [
  { to: "/finance/persons", labelKey: "layout.accountingPersons", icon: Users },
  { to: "/finance/products", labelKey: "layout.accountingProducts", icon: Package },
  { to: "/finance/invoices", labelKey: "layout.accountingInvoices", icon: ListOrdered },
  { to: "/finance/cash-accounts", labelKey: "layout.accountingCashAccounts", icon: Wallet },
  { to: "/finance/receipts", labelKey: "layout.accountingReceipts", icon: Receipt },
  { to: "/finance/checks", labelKey: "layout.accountingChecks", icon: Banknote },
  { to: "/finance/chart", labelKey: "layout.accountingChart", icon: BookOpen },
  { to: "/finance/journals", labelKey: "layout.accountingJournals", icon: FileText },
  { to: "/finance/ledger", labelKey: "layout.accountingLedger", icon: Book },
  { to: "/finance/reports", labelKey: "layout.accountingReports", icon: BarChart2 },
  { to: "/finance/fiscal-year", labelKey: "layout.accountingFiscalYear", icon: Calendar },
  { to: "/finance/settings", labelKey: "layout.accountingSettings", icon: Settings },
  { to: "/scm/warehouses", labelKey: "layout.accountingWarehouses", icon: Warehouse },
  { to: "/scm/stock", labelKey: "layout.accountingWarehouseStock", icon: Layers },
  { to: "/scm/inbound", labelKey: "layout.accountingWarehouseInbound", icon: ArrowDownToLine },
  { to: "/scm/outbound", labelKey: "layout.accountingWarehouseOutbound", icon: ArrowUpFromLine },
  { to: "/scm/audit", labelKey: "layout.accountingWarehouseAudit", icon: ClipboardList },
]

export function AccountingDashboardPage() {
  const { t, formatNumber } = useLocale()
  const [fiscalYears, setFiscalYears] = useState<{ id: number; name: string }[]>([])
  const [recentTotal, setRecentTotal] = useState<number>(0)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    async function load() {
      const res = await accountingFiscalYears()
      if (cancelled) return
      if (res.success && res.data?.items) setFiscalYears(res.data.items)
      const listRes = await accountingJournalList({ per_page: 5, page: 1 })
      if (cancelled) return
      if (listRes.success && listRes.data?.total !== undefined) setRecentTotal(listRes.data.total)
      setLoading(false)
    }
    void load()
    return () => {
      cancelled = true
    }
  }, [])

  return (
    <AccountingPageLayout
      title={t("layout.accounting")}
      description={t("pages.accounting.accountingDashboard.خلاصه_و_دسترسی_سریع_به_بخشهای_حسابداری")}
    >
      {loading ? (
        <DashboardSkeleton compact showPageHeader={false} />
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t("pages.accounting.accountingDashboard.سالهای_مالی")}</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-2xl font-semibold">{formatNumber(fiscalYears.length)}</p>
                <Button variant="outline" size="sm" className="mt-2" asChild>
                  <Link to="/accounting/fiscal-year">{t("pages.accounting.accountingDashboard.مدیریت_سال_مالی")}</Link>
                </Button>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t("pages.accounting.accountingDashboard.تعداد_اسناد")}</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-2xl font-semibold">{formatNumber(recentTotal)}</p>
                <Button variant="outline" size="sm" className="mt-2" asChild>
                  <Link to="/accounting/journals">{t("pages.accounting.accountingDashboard.مشاهده_اسناد")}</Link>
                </Button>
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pages.accounting.accountingDashboard.دسترسی_سریع")}</CardTitle>
            </CardHeader>
            <CardContent>
              {LINKS.length === 0 ? (
                <PmEmptyState message={t("common.noData")} />
              ) : (
                <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                  {LINKS.map(({ to, labelKey, icon }) => (
                    <Button
                      key={to}
                      variant="outline"
                      className="justify-start"
                      asChild
                    >
                      <Link to={to}>
                        {renderIcon(icon, "h-4 w-4 shrink-0")}
                        {t(labelKey)}
                      </Link>
                    </Button>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </AccountingPageLayout>
  )
}
