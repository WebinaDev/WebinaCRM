import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useState, useEffect, useCallback } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
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
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  accountingChartList,
  accountingChartSave,
  accountingChartDelete,
} from "@/api/accounting"
import type { ChartAccount } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"
import { Link } from "react-router-dom"

const ACCOUNT_TYPES = ["asset", "liability", "equity", "income", "expense"] as const

export function ChartOfAccountsPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const [items, setItems] = useState<ChartAccount[]>([])
  const [fiscalYearId, setFiscalYearId] = useState(0)
  const [fiscalYearReady, setFiscalYearReady] = useState(false)
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editingSystem, setEditingSystem] = useState(false)
  const [saving, setSaving] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [form, setForm] = useState({
    code: "",
    title: "",
    level: 1,
    parent_id: 0,
    account_type: "asset" as string,
    sort_order: 0,
  })

  const load = useCallback(async () => {
    if (!fiscalYearId) {
      setLoading(false)
      return
    }
    setLoading(true)
    const res = await accountingChartList(fiscalYearId)
    if (res.success && res.data?.items) setItems(res.data.items)
    else setItems([])
    setLoading(false)
  }, [fiscalYearId])

  useEffect(() => {
    void load()
  }, [load])

  const parentOptions = items.filter((a) => a.level < form.level)

  const openCreate = () => {
    setEditingId(null)
    setEditingSystem(false)
    setForm({
      code: "",
      title: "",
      level: 1,
      parent_id: 0,
      account_type: "asset",
      sort_order: 0,
    })
    setDialogOpen(true)
  }

  const openEdit = (row: ChartAccount) => {
    setEditingId(row.id)
    setEditingSystem(row.is_system === 1)
    setForm({
      code: row.code,
      title: row.title,
      level: row.level,
      parent_id: row.parent_id,
      account_type: row.account_type,
      sort_order: row.sort_order,
    })
    setDialogOpen(true)
  }

  const handleSave = async () => {
    if (!fiscalYearId || !form.code.trim() || !form.title.trim()) return
    setSaving(true)
    const res = await accountingChartSave({
      id: editingId ?? undefined,
      code: form.code.trim(),
      title: form.title.trim(),
      level: form.level,
      parent_id: form.parent_id,
      account_type: form.account_type,
      fiscal_year_id: fiscalYearId,
      sort_order: form.sort_order,
    })
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.chartOfAccounts.saved") })) {
      setDialogOpen(false)
      void load()
    }
  }

  const TYPE_LABELS: Record<string, string> = {
    asset: t("pages.accounting.chartOfAccounts.type_asset"),
    liability: t("pages.accounting.chartOfAccounts.type_liability"),
    equity: t("pages.accounting.chartOfAccounts.type_equity"),
    income: t("pages.accounting.chartOfAccounts.type_income"),
    expense: t("pages.accounting.chartOfAccounts.type_expense"),
  }
  const typeLabel = (type: string) => TYPE_LABELS[type] ?? type

  return (
    <AccountingPageLayout
      title={t("pages.accounting.chartOfAccounts.عنوان_صفحه")}
      description={t("pages.accounting.chartOfAccounts.مدیریت_حسابهای_کل_و_معین_مطابق_استاندارد")}
      {...layoutProps}
      actions={
        <Button onClick={openCreate} disabled={!fiscalYearId}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.chartOfAccounts.new")}
        </Button>
      }
    >
      <div className="flex items-center gap-2">
        <span className="text-sm text-muted-foreground">{t("pages.accounting.accountingReports.سال_مالی")}</span>
        <FiscalYearSelect
          value={fiscalYearId}
          onChange={setFiscalYearId}
          onReady={() => setFiscalYearReady(true)}
        />
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.accounting.chartOfAccounts.لیست_حسابها")}</CardTitle>
        </CardHeader>
        <CardContent>
          {!fiscalYearReady ? (
            <div className="flex items-center gap-2 text-muted-foreground py-4">
              <Loader2 className="h-4 w-4 animate-spin" />
              {t("common.loading")}
            </div>
          ) : !fiscalYearId ? (
            <div className="space-y-3">
              <PmEmptyState
                message={t("pages.accounting.fiscalYear.سال_مالی_تعریف_نشده_از_طریق_API_یا_تنظیم")}
              />
              <div className="flex justify-center">
                <Button variant="outline" asChild>
                  <Link to="/finance/fiscal-year">{t("nav.erp.finance.fiscalYear")}</Link>
                </Button>
              </div>
            </div>
          ) : loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <PmEmptyState message={t("pages.accounting.chartOfAccounts.حسابی_تعریف_نشده_از_تنظیمات_حسابداری_میت")} />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.accounting.accountingReports.کد")}</TableHead>
                  <TableHead>{t("pages.accounting.accountingReports.عنوان")}</TableHead>
                  <TableHead>{t("pages.accounting.chartOfAccounts.سطح")}</TableHead>
                  <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                  <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-mono">{row.code}</TableCell>
                    <TableCell style={{ paddingInlineStart: row.level * 12 }}>{row.title}</TableCell>
                    <TableCell>{row.level}</TableCell>
                    <TableCell>{typeLabel(row.account_type)}</TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(row)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          disabled={row.is_system === 1}
                          onClick={() => setDeleteId(row.id)}
                        >
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-md" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.chartOfAccounts.edit") : t("pages.accounting.chartOfAccounts.new")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.accountingReports.کد")}</Label>
                <Input
                  value={form.code}
                  disabled={editingSystem}
                  onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.chartOfAccounts.سطح")}</Label>
                <Input
                  type="number"
                  min={1}
                  max={5}
                  value={form.level}
                  onChange={(e) =>
                    setForm((f) => ({ ...f, level: parseInt(e.target.value, 10) || 1, parent_id: 0 }))
                  }
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.accountingReports.عنوان")}</Label>
              <Input value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.chartOfAccounts.parent")}</Label>
              <Select
                value={String(form.parent_id)}
                onValueChange={(v) => setForm((f) => ({ ...f, parent_id: Number(v) }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="0">—</SelectItem>
                  {parentOptions.map((a) => (
                    <SelectItem key={a.id} value={String(a.id)}>
                      {a.code} — {a.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.cashAccounts.نوع")}</Label>
              <Select value={form.account_type} onValueChange={(v) => setForm((f) => ({ ...f, account_type: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {ACCOUNT_TYPES.map((type) => (
                    <SelectItem key={type} value={type}>
                      {typeLabel(type)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.chartOfAccounts.sortOrder")}</Label>
              <Input
                type="number"
                value={form.sort_order}
                onChange={(e) => setForm((f) => ({ ...f, sort_order: parseInt(e.target.value, 10) || 0 }))}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button
              onClick={() => void handleSave()}
              disabled={saving || !form.code.trim() || !form.title.trim() || !fiscalYearId}
            >
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={!!deleteId}
        onOpenChange={() => setDeleteId(null)}
        title={t("pages.accounting.chartOfAccounts.deleteTitle")}
        description={t("pages.accounting.chartOfAccounts.deleteConfirm")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingChartDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.chartOfAccounts.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
