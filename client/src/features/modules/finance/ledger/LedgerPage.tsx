import { useState, useEffect } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { accountingLedger, accountingChartList } from "@/api/accounting"
import { DatePicker } from "@/components/ui/date-picker"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { Loader2 } from "lucide-react"

export function LedgerPage() {
  const { t, formatDate, formatNumber } = useLocale()
  const [accounts, setAccounts] = useState<{ id: number; code: string; title: string }[]>([])
  const [fiscalYearId, setFiscalYearId] = useState(0)
  const [accountId, setAccountId] = useState(0)
  const [dateFrom, setDateFrom] = useState("")
  const [dateTo, setDateTo] = useState("")
  const [data, setData] = useState<{
    rows: Array<{
      voucher_no: string
      voucher_date: string
      debit: number
      credit: number
      line_description?: string
    }>
    debit_total: number
    credit_total: number
    balance_debit: number
    balance_credit: number
  } | null>(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!fiscalYearId) return
    accountingChartList(fiscalYearId).then((res) => {
      if (res.success && res.data?.items) setAccounts(res.data.items)
    })
  }, [fiscalYearId])

  const runReport = async () => {
    if (!accountId || !fiscalYearId) return
    setLoading(true)
    const res = await accountingLedger(accountId, fiscalYearId, dateFrom || undefined, dateTo || undefined)
    setLoading(false)
    if (res.success && res.data) setData(res.data as typeof data)
    else setData(null)
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.ledger.عنوان_صفحه")}
      description={t("pages.accounting.ledger.گردش_و_مانده_حساب")}
    >
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.accounting.ledger.فیلتر")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-2">
              <Label>{t("pages.accounting.accountingDashboard.سال_مالی")}</Label>
              <FiscalYearSelect value={fiscalYearId} onChange={setFiscalYearId} className="w-full" />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.ledger.حساب")}</Label>
              <Select value={String(accountId)} onValueChange={(v) => setAccountId(Number(v))}>
                <SelectTrigger>
                  <SelectValue placeholder={t("pages.accounting.ledger.انتخاب_حساب")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="0">{t("pages.accounting.ledger.انتخاب_حساب")}</SelectItem>
                  {accounts.map((a) => (
                    <SelectItem key={a.id} value={String(a.id)}>
                      {a.code} — {a.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.fiscalYear.از_تاریخ")}</Label>
              <DatePicker value={dateFrom} onChange={setDateFrom} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.fiscalYear.تا_تاریخ")}</Label>
              <DatePicker value={dateTo} onChange={setDateTo} />
            </div>
          </div>
          <Button onClick={() => void runReport()} disabled={!accountId || !fiscalYearId || loading}>
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
            {t("pages.accounting.ledger.نمایش_گردش")}
          </Button>
        </CardContent>
      </Card>

      {data && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("pages.accounting.ledger.گردش_حساب")}</CardTitle>
            <p className="text-sm text-muted-foreground">
              {t("pages.accounting.ledger.جمع_خلاصه", {
                debit: formatNumber(data.debit_total),
                credit: formatNumber(data.credit_total),
                balanceDebit: formatNumber(data.balance_debit),
                balanceCredit: formatNumber(data.balance_credit),
              })}
            </p>
          </CardHeader>
          <CardContent>
            {data.rows.length === 0 ? (
              <PmEmptyState message={t("common.noData")} />
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.accounting.journals.شماره_سند")}</TableHead>
                    <TableHead>{t("common.date")}</TableHead>
                    <TableHead>{t("pages.accounting.ledger.بدهکار")}</TableHead>
                    <TableHead>{t("pages.accounting.ledger.بستانکار")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.شرح")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.rows.map((row, i) => (
                    <TableRow key={i}>
                      <TableCell className="font-mono">{row.voucher_no}</TableCell>
                      <TableCell>{formatDate(row.voucher_date)}</TableCell>
                      <TableCell>{formatNumber(row.debit)}</TableCell>
                      <TableCell>{formatNumber(row.credit)}</TableCell>
                      <TableCell>{row.line_description || "—"}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      )}
    </AccountingPageLayout>
  )
}
