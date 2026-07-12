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
import { Checkbox } from "@/components/ui/checkbox"
import { Badge } from "@/components/ui/badge"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { DatePicker } from "@/components/ui/date-picker"
import {
  accountingFiscalYears,
  accountingFiscalYearSave,
  accountingFiscalYearDelete,
} from "@/api/accounting"
import type { FiscalYear } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"

export function FiscalYearPage() {
  const { t, isRtl, formatDate } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const [items, setItems] = useState<FiscalYear[]>([])
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [form, setForm] = useState({
    name: "",
    start_date: "",
    end_date: "",
    is_active: true,
  })

  const load = useCallback(async () => {
    setLoading(true)
    const res = await accountingFiscalYears()
    if (res.success && res.data?.items) setItems(res.data.items)
    setLoading(false)
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const openCreate = () => {
    setEditingId(null)
    const y = new Date().getFullYear()
    setForm({
      name: String(y),
      start_date: `${y}-01-01`,
      end_date: `${y}-12-31`,
      is_active: true,
    })
    setDialogOpen(true)
  }

  const openEdit = (row: FiscalYear) => {
    setEditingId(row.id)
    setForm({
      name: row.name,
      start_date: row.start_date,
      end_date: row.end_date,
      is_active: row.is_active === 1,
    })
    setDialogOpen(true)
  }

  const handleSave = async () => {
    if (!form.name.trim() || !form.start_date || !form.end_date) return
    setSaving(true)
    const res = await accountingFiscalYearSave({
      id: editingId ?? undefined,
      name: form.name.trim(),
      start_date: form.start_date,
      end_date: form.end_date,
      is_active: form.is_active ? 1 : 0,
    })
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.fiscalYear.saved") })) {
      setDialogOpen(false)
      void load()
    }
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.fiscalYear.عنوان_صفحه")}
      description={t("pages.accounting.fiscalYear.تعریف_و_مدیریت_سالهای_مالی")}
      {...layoutProps}
      actions={
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.fiscalYear.new")}
        </Button>
      }
    >
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.accounting.fiscalYear.لیست_سالهای_مالی")}</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <PmEmptyState message={t("pages.accounting.fiscalYear.سال_مالی_تعریف_نشده_از_طریق_API_یا_تنظیم")} />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.accounting.fiscalYear.از_تاریخ")}</TableHead>
                  <TableHead>{t("pages.accounting.fiscalYear.تا_تاریخ")}</TableHead>
                  <TableHead>{t("common.status")}</TableHead>
                  <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell>{row.name}</TableCell>
                    <TableCell>{formatDate(row.start_date)}</TableCell>
                    <TableCell>{formatDate(row.end_date)}</TableCell>
                    <TableCell>
                      {row.is_active ? (
                        <Badge>{t("common.active")}</Badge>
                      ) : (
                        <Badge variant="secondary">{t("common.inactive")}</Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(row)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" onClick={() => setDeleteId(row.id)}>
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
        <DialogContent dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.fiscalYear.edit") : t("pages.accounting.fiscalYear.new")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="space-y-2">
              <Label>{t("common.name")}</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.fiscalYear.از_تاریخ")}</Label>
                <DatePicker value={form.start_date} onChange={(v) => setForm((f) => ({ ...f, start_date: v }))} />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.fiscalYear.تا_تاریخ")}</Label>
                <DatePicker value={form.end_date} onChange={(v) => setForm((f) => ({ ...f, end_date: v }))} />
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Checkbox
                id="fy_active"
                checked={form.is_active}
                onCheckedChange={(c) => setForm((f) => ({ ...f, is_active: c === true }))}
              />
              <Label htmlFor="fy_active" className="cursor-pointer">
                {t("common.active")}
              </Label>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button
              onClick={() => void handleSave()}
              disabled={saving || !form.name.trim() || !form.start_date || !form.end_date}
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
        title={t("pages.accounting.fiscalYear.deleteTitle")}
        description={t("pages.accounting.fiscalYear.deleteConfirm")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingFiscalYearDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.fiscalYear.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
