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
  accountingCheckList,
  accountingCheckGet,
  accountingCheckSave,
  accountingCheckDelete,
  accountingCheckSetStatus,
  accountingCashAccountsList,
  accountingPersonsList,
} from "@/api/accounting"
import type { AccountingCheck, CashAccount, Person } from "@/api/accounting"
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
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"

const PER_PAGE = 15

export function ChecksPage() {
  const { t, isRtl, formatDate, formatNumber } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const TYPE_LABELS: Record<string, string> = {
    receivable: t("pages.accounting.checks.دریافتی"),
    payable: t("pages.accounting.checks.پرداختی"),
  }
  const STATUS_LABELS: Record<string, string> = {
    in_safe: t("pages.accounting.checks.در_صندوق"),
    collected: t("pages.accounting.checks.وصول_شده"),
    returned: t("pages.accounting.checks.برگشتی"),
    spent: t("pages.accounting.checks.خرجشده"),
  }

  const [items, setItems] = useState<AccountingCheck[]>([])
  const [cashAccounts, setCashAccounts] = useState<CashAccount[]>([])
  const [persons, setPersons] = useState<Person[]>([])
  const [loading, setLoading] = useState(true)
  const [typeFilter, setTypeFilter] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    type: "receivable",
    check_no: "",
    check_date: "",
    amount: 0,
    cash_account_id: 0,
    person_id: 0,
    due_date: "",
    description: "",
  })
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [statusChangingId, setStatusChangingId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    const params: Record<string, string | number> = { page: currentPage, per_page: PER_PAGE }
    if (typeFilter) params.type = typeFilter
    if (statusFilter) params.status = statusFilter
    const res = await accountingCheckList(params)
    if (res.success && res.data) {
      setItems(res.data.items ?? [])
      setTotalPages(Math.max(1, Math.ceil((res.data.total ?? 0) / PER_PAGE)))
    }
    setLoading(false)
  }, [currentPage, typeFilter, statusFilter, setTotalPages])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    resetPage()
  }, [typeFilter, statusFilter, resetPage])

  useEffect(() => {
    accountingCashAccountsList({ type: "bank" }).then((res) => {
      if (res.success && res.data?.items) setCashAccounts(res.data.items)
    })
    accountingPersonsList({ per_page: 500 }).then((res) => {
      if (res.success && res.data?.items) setPersons(res.data.items)
    })
  }, [])

  const cashAccountName = (id: number) => cashAccounts.find((c) => c.id === id)?.name ?? String(id)
  const personName = (id: number) => persons.find((p) => p.id === id)?.name ?? String(id)

  const openCreate = () => {
    setEditingId(null)
    const today = new Date().toISOString().slice(0, 10)
    setForm({
      type: "receivable",
      check_no: "",
      check_date: today,
      amount: 0,
      cash_account_id: cashAccounts[0]?.id ?? 0,
      person_id: 0,
      due_date: today,
      description: "",
    })
    setDialogOpen(true)
  }

  const openEdit = async (id: number) => {
    const res = await accountingCheckGet(id)
    if (res.success && res.data?.check) {
      const c = res.data.check
      setForm({
        type: c.type,
        check_no: c.check_no,
        check_date: c.check_date ?? "",
        amount: c.amount,
        cash_account_id: c.cash_account_id,
        person_id: c.person_id,
        due_date: c.due_date,
        description: c.description ?? "",
      })
      setEditingId(id)
      setDialogOpen(true)
    }
  }

  const handleSave = async () => {
    if (!form.check_no.trim() || form.cash_account_id <= 0 || form.person_id <= 0 || !form.due_date || form.amount <= 0)
      return
    setSaving(true)
    const res = await accountingCheckSave({ ...form, id: editingId ?? undefined })
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
      setDialogOpen(false)
      void load()
    }
  }

  const handleSetStatus = async (id: number, status: "collected" | "returned" | "spent") => {
    setStatusChangingId(id)
    const res = await accountingCheckSetStatus(id, status)
    setStatusChangingId(null)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) void load()
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.checks.عنوان_صفحه")}
      description={t("pages.accounting.checks.توضیح_صفحه")}
      {...layoutProps}
      actions={
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.checks.چک_جدید")}
        </Button>
      }
    >
      <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
        <Select
          value={typeFilter || SELECT_ALL_VALUE}
          onValueChange={(v) => setTypeFilter(v === SELECT_ALL_VALUE ? "" : v)}
        >
          <SelectTrigger className="w-[140px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SELECT_ALL_VALUE}>{t("pages.accounting.checks.همه_انواع")}</SelectItem>
            <SelectItem value="receivable">{t("pages.accounting.checks.دریافتی")}</SelectItem>
            <SelectItem value="payable">{t("pages.accounting.checks.پرداختی")}</SelectItem>
          </SelectContent>
        </Select>
        <Select
          value={statusFilter || SELECT_ALL_VALUE}
          onValueChange={(v) => setStatusFilter(v === SELECT_ALL_VALUE ? "" : v)}
        >
          <SelectTrigger className="w-[160px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SELECT_ALL_VALUE}>{t("pages.accounting.checks.همه_وضعیتها")}</SelectItem>
            <SelectItem value="in_safe">{t("pages.accounting.checks.در_صندوق")}</SelectItem>
            <SelectItem value="collected">{t("pages.accounting.checks.وصول_شده")}</SelectItem>
            <SelectItem value="returned">{t("pages.accounting.checks.برگشتی")}</SelectItem>
            <SelectItem value="spent">{t("pages.accounting.checks.خرجشده")}</SelectItem>
          </SelectContent>
        </Select>
      </PmFilterBar>

      <Card>
        <CardContent className="pt-6">
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <p className="text-muted-foreground py-4">{t("pages.accounting.checks.چکی_یافت_نشد")}</p>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.accounting.checks.شماره_چک")}</TableHead>
                    <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.طرف_حساب")}</TableHead>
                    <TableHead>{t("pages.accounting.cashAccounts.بانک")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.مبلغ")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.سررسید")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead className="w-[140px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell className="font-mono">{row.check_no}</TableCell>
                      <TableCell>{TYPE_LABELS[row.type] ?? row.type}</TableCell>
                      <TableCell>{personName(row.person_id)}</TableCell>
                      <TableCell>{cashAccountName(row.cash_account_id)}</TableCell>
                      <TableCell>{formatNumber(Number(row.amount))}</TableCell>
                      <TableCell>{formatDate(row.due_date)}</TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            row.status === "in_safe"
                              ? "secondary"
                              : row.status === "returned"
                                ? "destructive"
                                : "default"
                          }
                        >
                          {STATUS_LABELS[row.status] ?? row.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1 flex-wrap">
                          <Button variant="ghost" size="icon" onClick={() => void openEdit(row.id)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          {row.type === "receivable" && row.status === "in_safe" && (
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={statusChangingId === row.id}
                              onClick={() => void handleSetStatus(row.id, "collected")}
                            >
                              {statusChangingId === row.id ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                              ) : (
                                t("pages.accounting.checks.وصول")
                              )}
                            </Button>
                          )}
                          {row.type === "payable" && row.status === "in_safe" && (
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={statusChangingId === row.id}
                              onClick={() => void handleSetStatus(row.id, "spent")}
                            >
                              {statusChangingId === row.id ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                              ) : (
                                t("pages.accounting.checks.خرج")
                              )}
                            </Button>
                          )}
                          {row.status === "in_safe" && (
                            <Button
                              variant="ghost"
                              size="sm"
                              className="text-destructive"
                              disabled={statusChangingId === row.id}
                              onClick={() => void handleSetStatus(row.id, "returned")}
                            >
                              {t("pages.accounting.checks.برگشت")}
                            </Button>
                          )}
                          <Button variant="ghost" size="icon" onClick={() => setDeleteId(row.id)}>
                            <Trash2 className="h-4 w-4 text-destructive" />
                          </Button>
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
        <DialogContent className="max-w-md" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.checks.ویرایش_چک") : t("pages.accounting.checks.ثبت_چک_جدید")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.cashAccounts.نوع")}</Label>
                <Select value={form.type} onValueChange={(v) => setForm((f) => ({ ...f, type: v }))}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="receivable">{t("pages.accounting.checks.دریافتی")}</SelectItem>
                    <SelectItem value="payable">{t("pages.accounting.checks.پرداختی")}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.checks.شماره_چک_2")}</Label>
                <Input
                  value={form.check_no}
                  onChange={(e) => setForm((f) => ({ ...f, check_no: e.target.value }))}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.checks.تاریخ_چک")}</Label>
                <DatePicker
                  value={form.check_date}
                  onChange={(v) => setForm((f) => ({ ...f, check_date: v }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.checks.تاریخ_سررسید")}</Label>
                <DatePicker value={form.due_date} onChange={(v) => setForm((f) => ({ ...f, due_date: v }))} />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.cashAccounts.بانک")}</Label>
              <Select
                value={String(form.cash_account_id)}
                onValueChange={(v) => setForm((f) => ({ ...f, cash_account_id: Number(v) }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="0">{t("pages.accounting.checks.انتخاب")}</SelectItem>
                  {cashAccounts.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.checks.طرف_حساب_3")}</Label>
              <Select
                value={String(form.person_id)}
                onValueChange={(v) => setForm((f) => ({ ...f, person_id: Number(v) }))}
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
            <div className="space-y-2">
              <Label>{t("pages.accounting.checks.شرح")}</Label>
              <Input
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button
              onClick={() => void handleSave()}
              disabled={
                saving ||
                !form.check_no.trim() ||
                form.cash_account_id <= 0 ||
                form.person_id <= 0 ||
                !form.due_date ||
                form.amount <= 0
              }
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
        title={t("pages.accounting.checks.حذف_چک")}
        description={t("pages.accounting.checks.آیا_از_حذف_این_چک_اطمینان_دارید؟")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingCheckDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.shared.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
