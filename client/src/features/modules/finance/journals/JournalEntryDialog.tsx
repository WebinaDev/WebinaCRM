import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useState, useEffect } from "react"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
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
import { DatePicker } from "@/components/ui/date-picker"
import {
  accountingChartList,
  accountingJournalGet,
  accountingJournalSave,
  type ChartAccount,
  type JournalLineInput,
} from "@/api/accounting"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { useLocale } from "@/hooks/use-locale"
import { Loader2, Plus, Trash2 } from "lucide-react"

type LineRow = JournalLineInput & { key: string }

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  entryId: number | null
  readonly?: boolean
  defaultFiscalYearId?: number
  onSaved: () => void
  onError: (message: string) => void
}

export function JournalEntryDialog({
  open,
  onOpenChange,
  entryId,
  readonly = false,
  defaultFiscalYearId = 0,
  onSaved,
  onError,
}: Props) {
  const { t, isRtl, formatNumber } = useLocale()
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [fiscalYearId, setFiscalYearId] = useState(defaultFiscalYearId)
  const [accounts, setAccounts] = useState<ChartAccount[]>([])
  const [voucherDate, setVoucherDate] = useState("")
  const [description, setDescription] = useState("")
  const [lines, setLines] = useState<LineRow[]>([])

  useEffect(() => {
    if (!open) return
    setFiscalYearId(defaultFiscalYearId)
    if (!entryId) {
      const today = new Date().toISOString().slice(0, 10)
      setVoucherDate(today)
      setDescription("")
      setLines([
        { key: "1", account_id: 0, debit: 0, credit: 0, description: "" },
        { key: "2", account_id: 0, debit: 0, credit: 0, description: "" },
      ])
      return
    }
    setLoading(true)
    accountingJournalGet(entryId).then((res) => {
      setLoading(false)
      if (res.success && res.data) {
        const e = res.data.entry
        setFiscalYearId(e.fiscal_year_id)
        setVoucherDate(e.voucher_date)
        setDescription(e.description ?? "")
        setLines(
          (res.data.lines ?? []).map((l, i) => ({
            key: String(l.id || i),
            account_id: l.account_id,
            debit: l.debit,
            credit: l.credit,
            description: l.description ?? "",
          })),
        )
      }
    })
  }, [open, entryId, defaultFiscalYearId])

  useEffect(() => {
    if (!open || !fiscalYearId) return
    accountingChartList(fiscalYearId).then((res) => {
      if (res.success && res.data?.items) setAccounts(res.data.items)
    })
  }, [open, fiscalYearId])

  const debitTotal = lines.reduce((s, l) => s + (Number(l.debit) || 0), 0)
  const creditTotal = lines.reduce((s, l) => s + (Number(l.credit) || 0), 0)
  const balanced = Math.abs(debitTotal - creditTotal) < 0.01 && debitTotal > 0

  const updateLine = (index: number, field: keyof JournalLineInput, value: number | string) => {
    setLines((prev) =>
      prev.map((row, i) => (i === index ? { ...row, [field]: value } : row)),
    )
  }

  const addLine = () => {
    setLines((prev) => [
      ...prev,
      { key: String(Date.now()), account_id: 0, debit: 0, credit: 0, description: "" },
    ])
  }

  const removeLine = (index: number) => {
    setLines((prev) => (prev.length <= 2 ? prev : prev.filter((_, i) => i !== index)))
  }

  const handleSave = async () => {
    if (!fiscalYearId || !voucherDate || readonly) return
    const payload = lines
      .filter((l) => l.account_id > 0 && (l.debit > 0 || l.credit > 0))
      .map(({ account_id, debit, credit, description: d }) => ({
        account_id,
        debit: Number(debit) || 0,
        credit: Number(credit) || 0,
        description: d,
      }))
    if (payload.length < 2) {
      onError(t("pages.accounting.journals.minLines"))
      return
    }
    if (!balanced) {
      onError(t("pages.accounting.journals.notBalanced"))
      return
    }
    setSaving(true)
    const res = await accountingJournalSave({
      id: entryId ?? undefined,
      fiscal_year_id: fiscalYearId,
      voucher_date: voucherDate,
      description,
      lines: payload,
    })
    setSaving(false)
    if (res.success) {
      onSaved()
      onOpenChange(false)
    } else {
      onError(res.message ?? t("pages.accounting.journals.saveFailed"))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto" dir={isRtl ? "rtl" : "ltr"}>
        <DialogHeader>
          <DialogTitle>
            {readonly
              ? t("pages.accounting.journals.view")
              : entryId
                ? t("pages.accounting.journals.edit")
                : t("pages.accounting.journals.new")}
          </DialogTitle>
        </DialogHeader>
        {loading ? (

          <TableListSkeleton rows={8} columns={5} />

        ) : (
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.accountingReports.سال_مالی")}</Label>
                <FiscalYearSelect
                  value={fiscalYearId}
                  onChange={setFiscalYearId}
                  className="w-full"
                />
              </div>
              <div className="space-y-2">
                <Label>{t("common.date")}</Label>
                <DatePicker value={voucherDate} onChange={setVoucherDate} disabled={readonly} />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.checks.شرح")}</Label>
              <Input
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                disabled={readonly}
              />
            </div>
            <div>
              <div className="flex justify-between items-center mb-2">
                <Label>{t("pages.accounting.journals.lines")}</Label>
                {!readonly && (
                  <Button type="button" variant="outline" size="sm" onClick={addLine}>
                    <Plus className="h-4 w-4 ml-1" />
                    {t("pages.accounting.shared.addLine")}
                  </Button>
                )}
              </div>
              <div className="border rounded-md overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.accounting.journals.account")}</TableHead>
                      <TableHead>{t("pages.accounting.ledger.بدهکار")}</TableHead>
                      <TableHead>{t("pages.accounting.ledger.بستانکار")}</TableHead>
                      <TableHead>{t("pages.accounting.checks.شرح")}</TableHead>
                      {!readonly && <TableHead className="w-10" />}
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {lines.map((line, idx) => (
                      <TableRow key={line.key}>
                        <TableCell>
                          <Select
                            value={String(line.account_id)}
                            onValueChange={(v) => updateLine(idx, "account_id", Number(v))}
                            disabled={readonly}
                          >
                            <SelectTrigger className="min-w-[160px]">
                              <SelectValue placeholder={t("pages.accounting.ledger.انتخاب_حساب")} />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="0">—</SelectItem>
                              {accounts.map((a) => (
                                <SelectItem key={a.id} value={String(a.id)}>
                                  {a.code} — {a.title}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            min={0}
                            step={0.01}
                            value={line.debit || ""}
                            disabled={readonly}
                            onChange={(e) => updateLine(idx, "debit", parseFloat(e.target.value) || 0)}
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            min={0}
                            step={0.01}
                            value={line.credit || ""}
                            disabled={readonly}
                            onChange={(e) => updateLine(idx, "credit", parseFloat(e.target.value) || 0)}
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            value={line.description ?? ""}
                            disabled={readonly}
                            onChange={(e) => updateLine(idx, "description", e.target.value)}
                          />
                        </TableCell>
                        {!readonly && (
                          <TableCell>
                            <Button type="button" variant="ghost" size="icon" onClick={() => removeLine(idx)}>
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </TableCell>
                        )}
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
              <p className={`text-sm mt-2 ${balanced ? "text-muted-foreground" : "text-destructive"}`}>
                {t("pages.accounting.journals.totals", {
                  debit: formatNumber(debitTotal),
                  credit: formatNumber(creditTotal),
                })}
              </p>
            </div>
          </div>
        )}
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t("common.cancel")}
          </Button>
          {!readonly && (
            <Button onClick={() => void handleSave()} disabled={saving || !balanced || !fiscalYearId}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {t("common.save")}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
