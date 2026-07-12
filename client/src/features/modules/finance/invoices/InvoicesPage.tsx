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
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  accountingInvoiceList,
  accountingInvoiceGet,
  accountingInvoiceSave,
  accountingInvoiceDelete,
  accountingInvoiceNextNumber,
  accountingInvoiceConfirm,
  accountingFiscalYears,
  accountingPersonsList,
  accountingProductsList,
  accountingUserDefaultsGet,
} from "@/api/accounting"
import type { AccountingInvoice, AccountingInvoiceLine, FiscalYear, Person, Product } from "@/api/accounting"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { DatePicker } from "@/components/ui/date-picker"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { Loader2, Plus, Pencil, Trash2, CheckCircle } from "lucide-react"

const PER_PAGE = 15

export function InvoicesPage() {
  const { t, isRtl, formatDate } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const { currentPage, setCurrentPage, totalPages, setTotalPages } = usePmPagination()
  const INVOICE_TYPES = [
    { value: "proforma", label: t("pages.accounting.invoices.پیشفاکتور") },
    { value: "sales", label: t("pages.accounting.invoices.فروش") },
    { value: "purchase", label: t("pages.accounting.invoices.خرید") },
  ]
  const STATUS_LABELS: Record<string, string> = {
    draft: t("pages.accounting.invoices.پیشنویس"),
    confirmed: t("pages.accounting.invoices.تأیید_شده"),
    returned: t("pages.accounting.invoices.مرجوع"),
  }

  const [items, setItems] = useState<AccountingInvoice[]>([])
  const [, setFiscalYears] = useState<FiscalYear[]>([])
  const [persons, setPersons] = useState<Person[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [loading, setLoading] = useState(true)
  const [fiscalYearId, setFiscalYearId] = useState(0)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<{
    person_id: number
    invoice_date: string
    due_date?: string
    invoice_no?: string
    invoice_type: string
    lines: AccountingInvoiceLine[]
  }>({
    person_id: 0,
    invoice_date: new Date().toISOString().slice(0, 10),
    invoice_type: "sales",
    lines: [],
  })
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [confirmingId, setConfirmingId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    const params: Record<string, string | number> = { page: currentPage, per_page: PER_PAGE }
    if (fiscalYearId > 0) params.fiscal_year_id = fiscalYearId
    const res = await accountingInvoiceList(params)
    if (res.success && res.data) {
      setItems(res.data.items ?? [])
      setTotalPages(Math.max(1, Math.ceil((res.data.total ?? 0) / PER_PAGE)))
    }
    setLoading(false)
  }, [currentPage, fiscalYearId, setTotalPages])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    accountingFiscalYears().then((res) => {
      if (res.success && res.data?.items) {
        setFiscalYears(res.data.items)
        const active = res.data.items.find((y) => y.is_active === 1)
        if (active && !fiscalYearId) setFiscalYearId(active.id)
      }
    })
    accountingPersonsList({ per_page: 500 }).then((res) => {
      if (res.success && res.data?.items) setPersons(res.data.items)
    })
    accountingProductsList({ per_page: 500 }).then((res) => {
      if (res.success && res.data?.items) setProducts(res.data.items)
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const openCreate = () => {
    setEditingId(null)
    accountingUserDefaultsGet().then((res) => {
      const defPerson =
        res.success && res.data?.defaults?.default_invoice_person_id
          ? res.data.defaults.default_invoice_person_id
          : 0
      accountingInvoiceNextNumber(fiscalYearId || undefined, "sales").then((nr) => {
        const nextNo = nr.success && nr.data?.invoice_no ? nr.data.invoice_no : "sales-1"
        setForm({
          person_id: defPerson || 0,
          invoice_date: new Date().toISOString().slice(0, 10),
          invoice_type: "sales",
          invoice_no: nextNo,
          lines: [],
        })
        setDialogOpen(true)
      })
    })
  }

  const openEdit = (id: number) => {
    accountingInvoiceGet(id).then((res) => {
      if (res.success && res.data?.invoice) {
        const inv = res.data.invoice
        setForm({
          person_id: inv.person_id,
          invoice_date: inv.invoice_date,
          due_date: inv.due_date ?? undefined,
          invoice_no: inv.invoice_no,
          invoice_type: inv.invoice_type,
          lines: (res.data.lines ?? []).map((l: AccountingInvoiceLine) => ({
            product_id: l.product_id,
            quantity: l.quantity,
            unit_id: l.unit_id,
            unit_price: l.unit_price,
            discount_percent: l.discount_percent,
            discount_amount: l.discount_amount,
            tax_percent: l.tax_percent,
            tax_amount: l.tax_amount,
            description: l.description,
          })),
        })
        setEditingId(id)
        setDialogOpen(true)
      }
    })
  }

  const handleSave = async () => {
    if (!form.person_id || !form.invoice_date) return
    setSaving(true)
    const res = await accountingInvoiceSave({
      ...form,
      id: editingId ?? undefined,
      fiscal_year_id: fiscalYearId || undefined,
      lines: form.lines.filter((l) => l.product_id > 0),
    })
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
      setDialogOpen(false)
      void load()
    }
  }

  const addLine = () => {
    setForm((f) => ({
      ...f,
      lines: [
        ...f.lines,
        {
          product_id: 0,
          quantity: 1,
          unit_price: 0,
          unit_id: null,
          discount_percent: null,
          discount_amount: null,
          tax_percent: null,
          tax_amount: null,
          description: null,
          sort_order: f.lines.length + 1,
        },
      ],
    }))
  }

  const updateLine = (index: number, field: keyof AccountingInvoiceLine, value: number | string | null) => {
    setForm((f) => {
      const next = [...f.lines]
      if (!next[index]) return f
      next[index] = { ...next[index], [field]: value }
      return { ...f, lines: next }
    })
  }

  const removeLine = (index: number) => {
    setForm((f) => ({ ...f, lines: f.lines.filter((_, i) => i !== index) }))
  }

  const personName = (id: number) => persons.find((p) => p.id === id)?.name ?? String(id)

  return (
    <AccountingPageLayout
      title={t("pages.accounting.invoices.عنوان_صفحه")}
      description={t("pages.accounting.invoices.فاکتور_فروش_و_خرید_و_پیشفاکتور")}
      {...layoutProps}
      actions={
        <Button onClick={openCreate} disabled={fiscalYearId <= 0}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.invoices.فاکتور_جدید")}
        </Button>
      }
    >
      <Card>
        <CardHeader>
          <div className="flex flex-wrap items-center gap-4">
            <CardTitle className="text-base">{t("pages.accounting.invoices.لیست_فاکتورها")}</CardTitle>
            <FiscalYearSelect value={fiscalYearId} onChange={setFiscalYearId} allowAll />
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <p className="text-muted-foreground">{t("pages.accounting.invoices.فاکتوری_یافت_نشد")}</p>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.accounting.invoices.شماره")}</TableHead>
                    <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.طرف_حساب")}</TableHead>
                    <TableHead>{t("common.date")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell className="font-mono">{row.invoice_no}</TableCell>
                      <TableCell>
                        {INVOICE_TYPES.find((opt) => opt.value === row.invoice_type)?.label ?? row.invoice_type}
                      </TableCell>
                      <TableCell>{personName(row.person_id)}</TableCell>
                      <TableCell>{formatDate(row.invoice_date)}</TableCell>
                      <TableCell>
                        <Badge variant={row.status === "confirmed" ? "default" : "secondary"}>
                          {STATUS_LABELS[row.status] ?? row.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon" onClick={() => openEdit(row.id)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          {row.status === "draft" && (
                            <>
                              <Button
                                variant="ghost"
                                size="icon"
                                disabled={confirmingId === row.id}
                                onClick={() => {
                                  setConfirmingId(row.id)
                                  accountingInvoiceConfirm(row.id)
                                    .then((res) => {
                                      if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") }))
                                        void load()
                                    })
                                    .finally(() => setConfirmingId(null))
                                }}
                              >
                                {confirmingId === row.id ? (
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
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.invoices.ویرایش_فاکتور") : t("pages.accounting.invoices.فاکتور_جدید")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.accounting.checks.طرف_حساب_3")}</label>
                <Select
                  value={String(form.person_id)}
                  onValueChange={(v) => setForm((f) => ({ ...f, person_id: Number(v) }))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t("pages.accounting.checks.انتخاب")} />
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
                <label className="text-sm font-medium">{t("pages.accounting.invoices.شماره_فاکتور")}</label>
                <Input
                  value={form.invoice_no ?? ""}
                  onChange={(e) => setForm((f) => ({ ...f, invoice_no: e.target.value }))}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.accounting.invoices.تاریخ_5")}</label>
                <DatePicker
                  value={form.invoice_date}
                  onChange={(v) => setForm((f) => ({ ...f, invoice_date: v }))}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.accounting.checks.سررسید")}</label>
                <DatePicker
                  value={form.due_date ?? ""}
                  onChange={(v) => setForm((f) => ({ ...f, due_date: v || undefined }))}
                />
              </div>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.accounting.invoices.نوع_فاکتور")}</label>
              <Select
                value={form.invoice_type}
                onValueChange={(v) => setForm((f) => ({ ...f, invoice_type: v }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {INVOICE_TYPES.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <div className="flex justify-between items-center mb-2">
                <label className="text-sm font-medium">{t("pages.accounting.invoices.ردیفها")}</label>
                <Button type="button" variant="outline" size="sm" onClick={addLine}>
                  {t("pages.accounting.shared.addLine")}
                </Button>
              </div>
              <div className="border rounded-md overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.accounting.products.کد")}</TableHead>
                      <TableHead>{t("pages.accounting.invoices.تعداد")}</TableHead>
                      <TableHead>{t("pages.accounting.invoices.قیمت_واحد")}</TableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {form.lines.map((line, idx) => (
                      <TableRow key={idx}>
                        <TableCell>
                          <Select
                            value={String(line.product_id || 0)}
                            onValueChange={(v) => updateLine(idx, "product_id", Number(v))}
                          >
                            <SelectTrigger className="min-w-[140px]">
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="0">—</SelectItem>
                              {products.map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>
                                  {p.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            min={0}
                            value={line.quantity}
                            onChange={(e) => updateLine(idx, "quantity", Number(e.target.value))}
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            min={0}
                            value={line.unit_price}
                            onChange={(e) => updateLine(idx, "unit_price", Number(e.target.value))}
                          />
                        </TableCell>
                        <TableCell>
                          <Button type="button" variant="ghost" size="icon" onClick={() => removeLine(idx)}>
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => void handleSave()} disabled={saving || !form.person_id || !form.invoice_date}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId !== null}
        onOpenChange={() => setDeleteId(null)}
        title={t("pages.accounting.invoices.حذف_فاکتور")}
        description={t("pages.accounting.invoices.فقط_فاکتورهای_پیشنویس_قابل_حذف_هستند_آیا")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingInvoiceDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.shared.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
