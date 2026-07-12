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
  accountingCashAccountsList,
  accountingCashAccountGet,
  accountingCashAccountSave,
  accountingCashAccountDelete,
} from "@/api/accounting"
import type { CashAccount } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"

export function CashAccountsPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const TYPE_LABELS: Record<string, string> = {
    bank: t("pages.accounting.cashAccounts.بانک"),
    cash: t("pages.accounting.cashAccounts.صندوق"),
    petty: t("pages.accounting.cashAccounts.تنخواه"),
  }
      const [items, setItems] = useState<CashAccount[]>([])
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<Partial<CashAccount> & { name: string }>({
    name: "",
    type: "bank",
    code: "",
    description: "",
    card_no: "",
    sheba: "",
    is_active: 1,
    sort_order: 0,
  })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const load = useCallback(() => {
    setLoading(true)
    accountingCashAccountsList().then((res) => {
      if (res.success && res.data?.items) setItems(res.data.items)
      setLoading(false)
    })
  }, [])

  useEffect(() => {
    queueMicrotask(() => {
      load()
    })
  }, [load])

  const openCreate = () => {
    setEditingId(null)
    setForm({
      name: "",
      type: "bank",
      code: "",
      description: "",
      card_no: "",
      sheba: "",
      is_active: 1,
      sort_order: 0,
    })
    setDialogOpen(true)
  }

  const openEdit = (id: number) => {
    accountingCashAccountGet(id).then((res) => {
      if (res.success && res.data?.cash_account) {
        const c = res.data.cash_account
        setForm({
          id: c.id,
          name: c.name,
          type: c.type,
          code: c.code ?? "",
          description: c.description ?? "",
          card_no: c.card_no ?? "",
          sheba: c.sheba ?? "",
          chart_account_id: c.chart_account_id ?? undefined,
          is_active: c.is_active,
          sort_order: c.sort_order,
        })
        setEditingId(id)
        setDialogOpen(true)
      }
    })
  }

  const handleSave = async () => {
    if (!form.name?.trim()) return
    setSaving(true)
    const res = await accountingCashAccountSave(form)
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
      setDialogOpen(false)
      load()
    }
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.cashAccounts.عنوان_صفحه")}
      {...layoutProps}
      actions={
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.cashAccounts.حساب_جدید")}
        </Button>
      }
    >
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.accounting.cashAccounts.عنوان_صفحه")}</CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <p className="text-muted-foreground py-4">{t("pages.accounting.cashAccounts.حسابی_تعریف_نشده_است")}</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                  <TableHead>{t("pages.accounting.accountingReports.کد")}</TableHead>
                  <TableHead>{t("pages.accounting.cashAccounts.شبا_کارت")}</TableHead>
                  <TableHead>{t("common.status")}</TableHead>
                  <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-medium">{row.name}</TableCell>
                    <TableCell>{TYPE_LABELS[row.type] ?? row.type}</TableCell>
                    <TableCell>{row.code ?? "—"}</TableCell>
                    <TableCell className="text-muted-foreground text-sm">
                      {row.sheba || row.card_no || "—"}
                    </TableCell>
<TableCell>{row.is_active ? t("common.active") : t("common.inactive")}</TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button variant="ghost" size="icon" onClick={() => openEdit(row.id)} title={t("common.edit")}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" onClick={() => setDeleteId(row.id)} title={t("common.delete")}>
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
<DialogTitle>{editingId ? t("pages.accounting.cashAccounts.ویرایش_حساب") : t("pages.accounting.cashAccounts.حساب_جدید")}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.cashAccounts.نام_1")}</Label>
                <Input
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                  placeholder={t("pages.accounting.cashAccounts.مثلاً_صندوق_اصلی")}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.cashAccounts.نوع")}</Label>
                <Select
                  value={form.type}
                  onValueChange={(v) => setForm((f) => ({ ...f, type: v as CashAccount["type"] }))}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="bank">{t("pages.accounting.cashAccounts.بانک")}</SelectItem>
                    <SelectItem value="cash">{t("pages.accounting.cashAccounts.صندوق")}</SelectItem>
                    <SelectItem value="petty">{t("pages.accounting.cashAccounts.تنخواه")}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.accountingReports.کد")}</Label>
              <Input
                value={form.code ?? ""}
                onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                placeholder={t("pages.accounting.cashAccounts.اختیاری")}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.cashAccounts.شماره_شبا")}</Label>
              <Input
                value={form.sheba ?? ""}
                onChange={(e) => setForm((f) => ({ ...f, sheba: e.target.value }))}
                placeholder="IR..."
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.cashAccounts.شماره_کارت")}</Label>
              <Input
                value={form.card_no ?? ""}
                onChange={(e) => setForm((f) => ({ ...f, card_no: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("common.description")}</Label>
              <Input
                value={form.description ?? ""}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              />
            </div>
            <div className="flex items-center gap-2">
              <Checkbox
                id="cash_active"
                checked={!!form.is_active}
                onCheckedChange={(c) => setForm((f) => ({ ...f, is_active: c === true ? 1 : 0 }))}
              />
              <Label htmlFor="cash_active" className="cursor-pointer">
                {t("common.active")}
              </Label>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{t("common.cancel")}</Button>
            <Button onClick={() => void handleSave()} disabled={saving || !form.name?.trim()}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={!!deleteId}
        onOpenChange={() => setDeleteId(null)}
        title={t("pages.accounting.cashAccounts.حذف_حساب")}
        description={t("pages.accounting.cashAccounts.حذف_تأیید")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingCashAccountDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.shared.deleted") })) {
            setDeleteId(null)
            load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
