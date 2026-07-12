import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useState, useEffect, useCallback } from "react"
import { Card, CardContent } from "@/components/ui/card"
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
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { SELECT_ALL_VALUE } from "@/lib/constants"
import {
  accountingReceiptVoucherList,
  accountingReceiptVoucherGet,
  accountingReceiptVoucherSave,
  accountingReceiptVoucherPost,
  accountingReceiptVoucherDelete,
  accountingCashAccountsList,
  accountingPersonsList,
} from "@/api/accounting"
import type { ReceiptVoucher, CashAccount, Person } from "@/api/accounting"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { DatePicker } from "@/components/ui/date-picker"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { Loader2, Plus, Pencil, Trash2, CheckCircle } from "lucide-react"

const PER_PAGE = 15

export function ReceiptsPage() {
  const { t, isRtl, formatDate, formatNumber } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const TYPE_LABELS: Record<string, string> = {
    receipt: t("pages.accounting.receipts.دریافت"),
    payment: t("pages.accounting.receipts.پرداخت"),
    transfer: t("pages.accounting.receipts.انتقال"),
  }
  const STATUS_LABELS: Record<string, string> = {
    draft: t("pages.accounting.warehouseAudit.پیشنویس"),
    posted: t("pages.accounting.warehouseAudit.ثبتشده"),
  }

  const [items, setItems] = useState<ReceiptVoucher[]>([])
  const [cashAccounts, setCashAccounts] = useState<CashAccount[]>([])
  const [persons, setPersons] = useState<Person[]>([])
  const [loading, setLoading] = useState(true)
  const [fiscalYearId, setFiscalYearId] = useState(0)
  const [typeFilter, setTypeFilter] = useState("")
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    voucher_date: new Date().toISOString().slice(0, 10),
    type: "receipt",
    cash_account_id: 0,
    transfer_to_cash_account_id: null as number | null,
    person_id: null as number | null,
    amount: 0,
    description: "",
    invoice_id: null as number | null,
    project_id: null as number | null,
    bank_fee: null as number | null,
  })
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [postingId, setPostingId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    const params: Record<string, string | number> = { page: currentPage, per_page: PER_PAGE }
    if (fiscalYearId > 0) params.fiscal_year_id = fiscalYearId
    if (typeFilter) params.type = typeFilter
    const res = await accountingReceiptVoucherList(params)
    if (res.success && res.data) {
      setItems(res.data.items ?? [])
      setTotalPages(Math.max(1, Math.ceil((res.data.total ?? 0) / PER_PAGE)))
    }
    setLoading(false)
  }, [currentPage, fiscalYearId, typeFilter, setTotalPages])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    resetPage()
  }, [fiscalYearId, typeFilter, resetPage])

  useEffect(() => {
    accountingCashAccountsList().then((res) => {
      if (res.success && res.data?.items) setCashAccounts(res.data.items)
    })
    accountingPersonsList({ per_page: 500 }).then((res) => {
      if (res.success && res.data?.items) setPersons(res.data.items)
    })
  }, [])

  const cashAccountName = (id: number) => cashAccounts.find((c) => c.id === id)?.name ?? String(id)
  const personName = (id: number | null) => (id ? persons.find((p) => p.id === id)?.name ?? String(id) : "—")

  const openCreate = () => {
    setEditingId(null)
    const firstCash = cashAccounts.find((c) => c.is_active)?.id ?? 0
    setForm({
      voucher_date: new Date().toISOString().slice(0, 10),
      type: "receipt",
      cash_account_id: firstCash,
      transfer_to_cash_account_id: null,
      person_id: null,
      amount: 0,
      description: "",
      invoice_id: null,
      project_id: null,
      bank_fee: null,
    })
    setDialogOpen(true)
  }

  const openEdit = async (id: number) => {
    const res = await accountingReceiptVoucherGet(id)
    if (res.success && res.data?.voucher) {
      const v = res.data.voucher
      setForm({
        voucher_date: v.voucher_date,
        type: v.type,
        cash_account_id: v.cash_account_id,
        transfer_to_cash_account_id: v.transfer_to_cash_account_id ?? null,
        person_id: v.person_id ?? null,
        amount: v.amount,
        description: v.description ?? "",
        invoice_id: v.invoice_id ?? null,
        project_id: v.project_id ?? null,
        bank_fee: v.bank_fee ?? null,
      })
      setEditingId(id)
      setDialogOpen(true)
    }
  }

  const handleSave = async () => {
    if (form.cash_account_id <= 0) return
    if (form.amount <= 0) return
    setSaving(true)
    const res = await accountingReceiptVoucherSave({
      ...form,
      id: editingId ?? undefined,
      fiscal_year_id: fiscalYearId || undefined,
    })
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
      setDialogOpen(false)
      void load()
    }
  }

  const handlePost = async (id: number) => {
    setPostingId(id)
    const res = await accountingReceiptVoucherPost(id)
    setPostingId(null)
    if (applyResponse(res, { successMessage: t("pages.accounting.journals.posted") })) void load()
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.receipts.عنوان_صفحه")}
      {...layoutProps}
      actions={
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.receipts.رسید_جدید")}
        </Button>
      }
    >
      <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
        <FiscalYearSelect value={fiscalYearId} onChange={setFiscalYearId} allowAll className="w-[180px]" />
        <Select
          value={typeFilter || SELECT_ALL_VALUE}
          onValueChange={(v) => setTypeFilter(v === SELECT_ALL_VALUE ? "" : v)}
        >
          <SelectTrigger className="w-[140px]">
            <SelectValue placeholder={t("pages.accounting.cashAccounts.نوع")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SELECT_ALL_VALUE}>{t("pages.accounting.checks.همه_انواع")}</SelectItem>
            <SelectItem value="receipt">{t("pages.accounting.receipts.دریافت")}</SelectItem>
            <SelectItem value="payment">{t("pages.accounting.receipts.پرداخت")}</SelectItem>
            <SelectItem value="transfer">{t("pages.accounting.receipts.انتقال")}</SelectItem>
          </SelectContent>
        </Select>
      </PmFilterBar>

      <Card>
        <CardContent className="pt-6">
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <p className="text-muted-foreground py-4">{t("pages.accounting.receipts.رسید_یا_پرداختی_یافت_نشد")}</p>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.accounting.invoices.شماره")}</TableHead>
                    <TableHead>{t("common.date")}</TableHead>
                    <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                    <TableHead>{t("pages.accounting.ledger.حساب")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.طرف_حساب")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.مبلغ")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead className="w-[120px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell className="font-mono">{row.voucher_no}</TableCell>
                      <TableCell>{formatDate(row.voucher_date)}</TableCell>
                      <TableCell>{TYPE_LABELS[row.type] ?? row.type}</TableCell>
                      <TableCell>{cashAccountName(row.cash_account_id)}</TableCell>
                      <TableCell>
                        {row.type === "transfer"
                          ? row.transfer_to_cash_account_id
                            ? cashAccountName(row.transfer_to_cash_account_id)
                            : "—"
                          : personName(row.person_id)}
                      </TableCell>
                      <TableCell>{formatNumber(Number(row.amount))}</TableCell>
                      <TableCell>
                        <Badge variant={row.status === "posted" ? "default" : "secondary"}>
                          {STATUS_LABELS[row.status] ?? row.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon" onClick={() => void openEdit(row.id)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          {row.status === "draft" && (
                            <>
                              <Button
                                variant="ghost"
                                size="icon"
                                disabled={postingId === row.id}
                                onClick={() => void handlePost(row.id)}
                              >
                                {postingId === row.id ? (
                                  <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                  <CheckCircle className="h-4 w-4 text-green-600" />
                                )}
                              </Button>
                              <Button variant="ghost" size="icon" onClick={() => setDeleteId(row.id)}>
                                <Trash2 className="h-4 w-4 text-destructive" />
                              </Button>
                            </>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <PmPagination
                page={currentPage}
                totalPages={totalPages}
                onPageChange={setCurrentPage}
                prevLabel={t("common.prevPage")}
                nextLabel={t("common.nextPage")}
                isRtl={isRtl}
              />
            </>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.receipts.ویرایش_رسید") : t("pages.accounting.receipts.رسید_جدید")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.invoices.تاریخ_5")}</Label>
                <DatePicker value={form.voucher_date} onChange={(v) => setForm((f) => ({ ...f, voucher_date: v }))} />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.cashAccounts.نوع")}</Label>
                <Select value={form.type} onValueChange={(v) => setForm((f) => ({ ...f, type: v }))}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="receipt">{t("pages.accounting.receipts.دریافت")}</SelectItem>
                    <SelectItem value="payment">{t("pages.accounting.receipts.پرداخت")}</SelectItem>
                    <SelectItem value="transfer">{t("pages.accounting.receipts.انتقال")}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.receipts.حساب_صندوقبانک")}</Label>
              <Select
                value={String(form.cash_account_id)}
                onValueChange={(v) => setForm((f) => ({ ...f, cash_account_id: Number(v) }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t("pages.accounting.checks.انتخاب")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="0">{t("pages.accounting.checks.انتخاب")}</SelectItem>
                  {cashAccounts
                    .filter((c) => c.is_active)
                    .map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.name} ({TYPE_LABELS[c.type]})
                      </SelectItem>
                    ))}
                </SelectContent>
              </Select>
            </div>
            {form.type === "transfer" && (
              <div className="space-y-2">
                <Label>{t("pages.accounting.receipts.انتقال_به_حساب")}</Label>
                <Select
                  value={String(form.transfer_to_cash_account_id ?? 0)}
                  onValueChange={(v) =>
                    setForm((f) => ({ ...f, transfer_to_cash_account_id: Number(v) || null }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">{t("pages.accounting.checks.انتخاب")}</SelectItem>
                    {cashAccounts
                      .filter((c) => c.is_active && c.id !== form.cash_account_id)
                      .map((c) => (
                        <SelectItem key={c.id} value={String(c.id)}>
                          {c.name}
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </div>
            )}
            {form.type !== "transfer" && (
              <>
                <div className="space-y-2">
                  <Label>{t("pages.accounting.checks.طرف_حساب")}</Label>
                  <Select
                    value={String(form.person_id ?? 0)}
                    onValueChange={(v) => setForm((f) => ({ ...f, person_id: Number(v) || null }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="0">{t("pages.accounting.checks.انتخاب")}</SelectItem>
                      {persons.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          {p.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.accounting.checks.مبلغ_4")}</Label>
                  <Input
                    type="number"
                    min={0}
                    step={0.01}
                    value={form.amount || ""}
                    onChange={(e) => setForm((f) => ({ ...f, amount: parseFloat(e.target.value) || 0 }))}
                  />
                </div>
              </>
            )}
            {form.type === "transfer" && (
              <div className="space-y-2">
                <Label>{t("pages.accounting.receipts.مبلغ_انتقال")}</Label>
                <Input
                  type="number"
                  min={0}
                  step={0.01}
                  value={form.amount || ""}
                  onChange={(e) => setForm((f) => ({ ...f, amount: parseFloat(e.target.value) || 0 }))}
                />
              </div>
            )}
            <div className="space-y-2">
              <Label>{t("pages.accounting.checks.شرح")}</Label>
              <Input
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.receipts.کارمزد_بانکی")}</Label>
              <Input
                type="number"
                min={0}
                step={0.01}
                value={form.bank_fee ?? ""}
                onChange={(e) =>
                  setForm((f) => ({ ...f, bank_fee: e.target.value ? parseFloat(e.target.value) : null }))
                }
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button
              onClick={() => void handleSave()}
              disabled={saving || form.cash_account_id <= 0 || form.amount <= 0}
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
        title={t("pages.accounting.receipts.حذف_رسیدپرداخت")}
        description={t("pages.accounting.receipts.فقط_پیشنویس_قابل_حذف_است_آیا_مطمئن_هستید")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingReceiptVoucherDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.shared.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
