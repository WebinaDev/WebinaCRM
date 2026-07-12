import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  accountingReportTrialBalance,
  accountingReportBalanceSheet,
  accountingReportProfitLoss,
} from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { DatePicker } from "@/components/ui/date-picker"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { Loader2 } from "lucide-react"

export function AccountingReportsPage() {
  const { t, isRtl, formatNumber } = useLocale()
  const [fiscalYearId, setFiscalYearId] = useState(0)
  const [dateFrom, setDateFrom] = useState("")
  const [dateTo, setDateTo] = useState("")
  const [asOfDate, setAsOfDate] = useState("")
  const [trialBalance, setTrialBalance] = useState<
    Array<{ code: string; title: string; balance_debit: number; balance_credit: number }>
  >([])
  const [balanceSheet, setBalanceSheet] = useState<{
    assets: Array<{ code: string; title: string; balance: number }>
    liabilities: Array<{ code: string; title: string; balance: number }>
    equity: Array<{ code: string; title: string; balance: number }>
  } | null>(null)
  const [profitLoss, setProfitLoss] = useState<{
    income: unknown[]
    expense: unknown[]
    income_total: number
    expense_total: number
    net: number
  } | null>(null)
  const [loading, setLoading] = useState(false)
  const [activeTab, setActiveTab] = useState("trial")

  const loadTrialBalance = async () => {
    if (!fiscalYearId) return
    setLoading(true)
    const res = await accountingReportTrialBalance(fiscalYearId, dateFrom || undefined, dateTo || undefined)
    setLoading(false)
    if (res.success && Array.isArray(res.data)) setTrialBalance(res.data)
    else setTrialBalance([])
  }

  const loadBalanceSheet = async () => {
    if (!fiscalYearId) return
    setLoading(true)
    const res = await accountingReportBalanceSheet(fiscalYearId, asOfDate || undefined)
    setLoading(false)
    if (res.success && res.data) setBalanceSheet(res.data as typeof balanceSheet)
    else setBalanceSheet(null)
  }

  const loadProfitLoss = async () => {
    if (!fiscalYearId) return
    setLoading(true)
    const res = await accountingReportProfitLoss(fiscalYearId, dateFrom || undefined, dateTo || undefined)
    setLoading(false)
    if (res.success && res.data) setProfitLoss(res.data as typeof profitLoss)
    else setProfitLoss(null)
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.accountingReports.عنوان_صفحه")}
      description={t("pages.accounting.accountingReports.تراز_آزمایشی،_ترازنامه،_سود_و_زیان")}
    >
      <div
        className="flex flex-wrap gap-4 items-end text-start"
        dir={isRtl ? "rtl" : "ltr"}
      >
        <div className="space-y-1">
          <Label className="text-xs">{t("pages.accounting.accountingReports.سال_مالی")}</Label>
          <FiscalYearSelect value={fiscalYearId} onChange={setFiscalYearId} />
        </div>
        <div className="space-y-1">
          <Label className="text-xs">{t("pages.accounting.accountingReports.از_تاریخ")}</Label>
          <DatePicker value={dateFrom} onChange={setDateFrom} />
        </div>
        <div className="space-y-1">
          <Label className="text-xs">{t("pages.accounting.accountingReports.تا_تاریخ")}</Label>
          <DatePicker value={dateTo} onChange={setDateTo} />
        </div>
        <div className="space-y-1">
          <Label className="text-xs">{t("pages.accounting.accountingReports.تاریخ_ترازنامه")}</Label>
          <DatePicker value={asOfDate} onChange={setAsOfDate} />
        </div>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} dir={isRtl ? "rtl" : "ltr"} className="text-start">
        <TabsList className="text-start">
          <TabsTrigger value="trial">{t("pages.accounting.accountingReports.تراز_آزمایشی")}</TabsTrigger>
          <TabsTrigger value="balance">{t("pages.accounting.accountingReports.ترازنامه")}</TabsTrigger>
          <TabsTrigger value="pl">{t("pages.accounting.accountingReports.سود_و_زیان")}</TabsTrigger>
        </TabsList>
        <TabsContent value="trial">
          <Card>
            <CardHeader
              className="flex flex-row items-center justify-between gap-4 text-start"
            >
              <CardTitle className="text-base">{t("pages.accounting.accountingReports.تراز_آزمایشی")}</CardTitle>
              <Button size="sm" onClick={() => void loadTrialBalance()} disabled={!fiscalYearId || loading}>
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                {t("pages.accounting.accountingReports.بارگذاری")}
              </Button>
            </CardHeader>
            <CardContent>
              {trialBalance.length === 0 && !loading ? (
                <PmEmptyState message={t("pages.accounting.accountingReports.فیلتر_را_تنظیم_کرده_و_بارگذاری_کنید")} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.accounting.accountingReports.کد")}</TableHead>
                      <TableHead>{t("pages.accounting.accountingReports.عنوان")}</TableHead>
                      <TableHead>{t("pages.accounting.accountingReports.مانده_بدهکار")}</TableHead>
                      <TableHead>{t("pages.accounting.accountingReports.مانده_بستانکار")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {trialBalance.map((row, i) => (
                      <TableRow key={i}>
                        <TableCell className="font-mono" dir="ltr">{row.code}</TableCell>
                        <TableCell>{row.title}</TableCell>
                        <TableCell dir="ltr">{formatNumber(row.balance_debit)}</TableCell>
                        <TableCell dir="ltr">{formatNumber(row.balance_credit)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="balance">
          <Card>
            <CardHeader
              className="flex flex-row items-center justify-between gap-4 text-start"
            >
              <CardTitle className="text-base">{t("pages.accounting.accountingReports.ترازنامه")}</CardTitle>
              <Button size="sm" onClick={() => void loadBalanceSheet()} disabled={!fiscalYearId || loading}>
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                {t("pages.accounting.accountingReports.بارگذاری")}
              </Button>
            </CardHeader>
            <CardContent>
              {!balanceSheet && !loading ? (
                <PmEmptyState message={t("pages.accounting.accountingReports.بارگذاری_کنید")} />
              ) : balanceSheet ? (
                <div className="grid gap-4 md:grid-cols-3">
                  <div>
                    <h4 className="font-medium mb-2 text-start">{t("pages.accounting.accountingReports.داراییها")}</h4>
                    {balanceSheet.assets?.map((a, i) => (
                      <p key={i} className="text-sm text-start" dir="ltr">
                        {a.code} {a.title}: {formatNumber(a.balance)}
                      </p>
                    ))}
                  </div>
                  <div>
                    <h4 className="font-medium mb-2 text-start">{t("pages.accounting.accountingReports.بدهیها")}</h4>
                    {balanceSheet.liabilities?.map((a, i) => (
                      <p key={i} className="text-sm text-start" dir="ltr">
                        {a.code} {a.title}: {formatNumber(a.balance)}
                      </p>
                    ))}
                  </div>
                  <div>
                    <h4 className="font-medium mb-2 text-start">{t("pages.accounting.accountingReports.حقوق_صاحبان_سهام")}</h4>
                    {balanceSheet.equity?.map((a, i) => (
                      <p key={i} className="text-sm text-start" dir="ltr">
                        {a.code} {a.title}: {formatNumber(a.balance)}
                      </p>
                    ))}
                  </div>
                </div>
              ) : null}
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="pl">
          <Card>
            <CardHeader
              className="flex flex-row items-center justify-between gap-4 text-start"
            >
              <CardTitle className="text-base">{t("pages.accounting.accountingReports.سود_و_زیان")}</CardTitle>
              <Button size="sm" onClick={() => void loadProfitLoss()} disabled={!fiscalYearId || loading}>
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                {t("pages.accounting.accountingReports.بارگذاری")}
              </Button>
            </CardHeader>
            <CardContent>
              {!profitLoss && !loading ? (
                <PmEmptyState message={t("pages.accounting.accountingReports.بارگذاری_کنید")} />
              ) : profitLoss ? (
                <div className="space-y-2 text-start">
                  <p className="font-medium" dir="ltr">
                    {t("pages.accounting.accountingReports.جمع_درآمد", {
                      value: formatNumber(profitLoss.income_total),
                    })}
                  </p>
                  <p className="font-medium">
                    {t("pages.accounting.accountingReports.جمع_هزینه", {
                      value: formatNumber(profitLoss.expense_total),
                    })}
                  </p>
                  <p className="font-semibold">
                    {t("pages.accounting.accountingReports.سود_خالص", { value: formatNumber(profitLoss.net) })}
                  </p>
                </div>
              ) : null}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </AccountingPageLayout>
  )
}
