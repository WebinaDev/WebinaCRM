import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { useNavigate, useSearchParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
import { Label } from "@/components/ui/label"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  getInvoices,
  getInvoice,
  getProjectsForCustomer,
  manageInvoice,
  downloadInvoicePdf,
  sendInvoiceEmail,
  type Invoice,
  type InvoiceDetail,
} from "@/api/invoices"
import { useLocale } from "@/hooks/use-locale"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { Plus, Loader2, Pencil, List, FileDown, Mail } from "lucide-react"
import { cn } from "@/lib/utils"
import { InvoiceEmailDialog } from "./InvoiceEmailDialog"

type ItemRow = { title: string; desc: string; price: string; discount: string }

export function InvoicesPage() {
  const { t, isRtl, formatNumber } = useLocale()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const initialTab = searchParams.get("action") === "new" ? "new" : "list"
  const editId = searchParams.get("invoice_id")
    ? parseInt(searchParams.get("invoice_id")!, 10)
    : null

  const { currentPage, setCurrentPage, totalPages, setTotalPages } = usePmPagination()
  const [tab, setTab] = useState<"list" | "new">(initialTab)
  const [invoices, setInvoices] = useState<Invoice[]>([])
  const [customers, setCustomers] = useState<{ id: number; display_name: string }[]>([])
  const [projects, setProjects] = useState<{ id: number; title: string }[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [editingInvoice, setEditingInvoice] = useState<InvoiceDetail | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [pdfLoadingId, setPdfLoadingId] = useState<number | null>(null)
  const [emailTarget, setEmailTarget] = useState<Invoice | null>(null)
  const [emailValue, setEmailValue] = useState("")
  const [form, setForm] = useState({
    customer_id: 0,
    project_id: 0,
    issue_date: new Date().toISOString().slice(0, 10),
    items: [] as ItemRow[],
    payment_method: "",
    notes: "",
  })

  const loadList = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getInvoices(currentPage)
      if (res.success && res.data) {
        setInvoices(res.data.invoices ?? [])
        setCustomers(res.data.customers ?? [])
        setTotalPages(res.data.total_pages ?? 1)
      } else {
        setError(res.message ?? t("pages.invoices.خطا_در_بارگذاری_پیشفاکتورها"))
      }
    } catch {
      setError(t("pages.invoices.خطا_در_بارگذاری_پیشفاکتورها"))
    } finally {
      setLoading(false)
    }
  }, [currentPage, setTotalPages, t])

  async function loadProjectsForCustomer(customerId: number) {
    if (!customerId) {
      setProjects([])
      return
    }
    const res = await getProjectsForCustomer(customerId)
    if (res.success && res.data) {
      setProjects(Array.isArray(res.data) ? res.data : [])
    } else {
      setProjects([])
    }
  }

  const loadInvoice = useCallback(
    async (id: number) => {
      setLoading(true)
      setError(null)
      try {
        const res = await getInvoice(id)
        if (res.success && res.data?.invoice) {
          const inv = res.data.invoice
          setEditingInvoice(inv)
          setForm({
            customer_id: inv.customer_id,
            project_id: inv.project_id,
            issue_date: inv.issue_date ? inv.issue_date.slice(0, 10) : "",
            items: (inv.items ?? []).map((i) => ({
              title: i.title ?? "",
              desc: i.desc ?? "",
              price: String(i.price ?? 0),
              discount: String(i.discount ?? 0),
            })),
            payment_method: inv.payment_method ?? "",
            notes: inv.notes ?? "",
          })
          void loadProjectsForCustomer(inv.customer_id)
        } else {
          setEditingInvoice(null)
          setError(res.message ?? t("pages.invoices.خطا_در_بارگذاری_پیشفاکتور"))
        }
      } catch {
        setError(t("pages.invoices.خطا_در_بارگذاری_پیشفاکتور"))
      } finally {
        setLoading(false)
      }
    },
    [t],
  )

  useEffect(() => {
    const action = searchParams.get("action")
    const invId = searchParams.get("invoice_id")
    if (action === "new") {
      setTab("new")
    } else {
      setTab("list")
    }
    if (invId && action === "new") {
      const id = parseInt(invId, 10)
      if (!Number.isNaN(id)) void loadInvoice(id)
    }
  }, [searchParams, loadInvoice])

  useEffect(() => {
    if (tab === "list") void loadList()
  }, [tab, loadList])

  useEffect(() => {
    if (editId && tab === "new") {
      void loadInvoice(editId)
    } else if (tab === "new" && !editId) {
      setEditingInvoice(null)
      setForm({
        customer_id: 0,
        project_id: 0,
        issue_date: new Date().toISOString().slice(0, 10),
        items: [{ title: "", desc: "", price: "", discount: "0" }],
        payment_method: "",
        notes: "",
      })
      setProjects([])
      getInvoices(1).then((res) => {
        if (res.success && res.data) setCustomers(res.data.customers ?? [])
      })
    }
  }, [tab, editId, loadInvoice])

  const openNew = () => {
    navigate("/invoices?action=new")
    setTab("new")
    setEditingInvoice(null)
  }

  const openEdit = (inv: Invoice) => {
    navigate(`/invoices?action=new&invoice_id=${inv.id}`)
    setTab("new")
    void loadInvoice(inv.id)
  }

  const backToList = () => {
    navigate("/invoices")
    setTab("list")
    void loadList()
  }

  const onCustomerChange = (customerId: number) => {
    setForm((f) => ({ ...f, customer_id: customerId, project_id: 0 }))
    void loadProjectsForCustomer(customerId)
  }

  const addItemRow = () => {
    setForm((f) => ({
      ...f,
      items: [...f.items, { title: "", desc: "", price: "", discount: "0" }],
    }))
  }

  const removeItemRow = (idx: number) => {
    setForm((f) => ({ ...f, items: f.items.filter((_, i) => i !== idx) }))
  }

  const updateItem = (idx: number, field: keyof ItemRow, value: string) => {
    setForm((f) => {
      const next = [...f.items]
      next[idx] = { ...next[idx], [field]: value }
      return { ...f, items: next }
    })
  }

  const subtotal = form.items.reduce(
    (s, i) => s + (parseFloat(i.price.replace(/\D/g, "")) || 0),
    0,
  )
  const totalDiscount = form.items.reduce(
    (s, i) => s + (parseFloat(i.discount.replace(/\D/g, "")) || 0),
    0,
  )
  const finalTotal = subtotal - totalDiscount

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.customer_id || !form.project_id || !form.issue_date) {
      setError(t("pages.invoices.مشتری،_پروژه_و_تاریخ_صدور_الزامی_هستند"))
      return
    }
    const validItems = form.items.filter((i) => i.title.trim())
    if (validItems.length === 0) {
      setError(t("pages.invoices.حداقل_یک_آیتم_باید_ثبت_شود"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await manageInvoice({
        invoice_id: editingInvoice?.id,
        customer_id: form.customer_id,
        project_id: form.project_id,
        issue_date: form.issue_date,
        item_title: validItems.map((i) => i.title),
        item_desc: validItems.map((i) => i.desc),
        item_price: validItems.map((i) => i.price.replace(/\D/g, "") || "0"),
        item_discount: validItems.map((i) => i.discount.replace(/\D/g, "") || "0"),
        payment_method: form.payment_method,
        notes: form.notes,
      })
      if (res.success) {
        setSuccess(res.message ?? t("common.save"))
        backToList()
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleDownloadPdf = async (inv: Invoice) => {
    setPdfLoadingId(inv.id)
    setError(null)
    try {
      const res = await downloadInvoicePdf(inv.id)
      if (res.success && res.data?.pdf_url) {
        window.open(res.data.pdf_url, "_blank", "noopener,noreferrer")
        setSuccess(res.message ?? t("pages.invoices.pdf_موفق"))
      } else {
        setError(res.message ?? t("pages.invoices.pdf_خطا"))
      }
    } catch {
      setError(t("pages.invoices.pdf_خطا"))
    } finally {
      setPdfLoadingId(null)
    }
  }

  const handleSendEmail = async () => {
    if (!emailTarget) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await sendInvoiceEmail(
        emailTarget.id,
        emailValue.trim() || undefined,
      )
      if (res.success) {
        setSuccess(res.message ?? t("pages.invoices.ایمیل_موفق"))
        setEmailTarget(null)
        setEmailValue("")
      } else {
        setError(res.message ?? t("pages.invoices.ایمیل_خطا"))
      }
    } catch {
      setError(t("pages.invoices.ایمیل_خطا"))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.invoices.پیشفاکتورها")}
        isRtl={isRtl}
        actions={
          <div className="flex gap-2">
            <Button variant={tab === "list" ? "default" : "outline"} size="sm" onClick={() => { navigate("/invoices"); setTab("list") }}>
              <List className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("common.list")}
            </Button>
            <Button variant={tab === "new" ? "default" : "outline"} size="sm" onClick={openNew}>
              <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("common.new")}
            </Button>
          </div>
        }
      />

      <PmAlerts error={error} success={success} />

      {tab === "list" ? (
        <>
          <Card>
            <CardContent className="p-0 pt-6">
              {loading ? (

                <TableListSkeleton rows={8} columns={6} withAvatarColumn />

              ) : invoices.length === 0 ? (
                <div className="py-12 text-center text-muted-foreground">{t("common.noResults")}</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.accounting.invoices.شماره")}</TableHead>
                      <TableHead>{t("pages.accounting.persons.مشتری")}</TableHead>
                      <TableHead>{t("pages.invoices.پروژه")}</TableHead>
                      <TableHead>{t("pages.invoices.مبلغ_نهایی")}</TableHead>
                      <TableHead>{t("common.date")}</TableHead>
                      <TableHead>{t("common.actions")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {invoices.map((inv) => (
                      <TableRow key={inv.id}>
                        <TableCell className="font-mono">{inv.invoice_number}</TableCell>
                        <TableCell>{inv.customer_name}</TableCell>
                        <TableCell>{inv.project_title}</TableCell>
                        <TableCell dir="ltr">
                          {formatNumber(inv.final_total)} {t("common.toman")}
                        </TableCell>
                        <TableCell>{inv.date_display}</TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" onClick={() => openEdit(inv)} title={t("common.edit")}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => void handleDownloadPdf(inv)}
                              disabled={pdfLoadingId === inv.id}
                              title={t("pages.invoices.دانلود_pdf")}
                            >
                              {pdfLoadingId === inv.id ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                              ) : (
                                <FileDown className="h-4 w-4" />
                              )}
                            </Button>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => {
                                setEmailTarget(inv)
                                setEmailValue("")
                              }}
                              title={t("pages.invoices.ارسال_ایمیل")}
                            >
                              <Mail className="h-4 w-4" />
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
          <PmPagination
            page={currentPage}
            totalPages={totalPages}
            onPageChange={setCurrentPage}
            prevLabel={t("common.prevPage")}
            nextLabel={t("common.nextPage")}
            isRtl={isRtl}
          />
        </>
      ) : (
        <Card>
          <CardContent className="pt-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold">
                {editingInvoice ? t("pages.invoices.ویرایش_پیشفاکتور") : t("pages.invoices.ایجاد_پیشفاکتور_جدید")}
              </h3>
              <Button variant="outline" size="sm" onClick={backToList}>
                {t("pages.invoices.بازگشت_به_لیست")}
              </Button>
            </div>

            {loading ? (


              <TableListSkeleton rows={8} columns={6} withAvatarColumn />


            ) : (
              <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t("pages.appointments.مشتری")}</Label>
                    <Select
                      value={form.customer_id ? String(form.customer_id) : ""}
                      onValueChange={(v) => onCustomerChange(parseInt(v, 10) || 0)}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder={t("pages.appointments.انتخاب_مشتری")} />
                      </SelectTrigger>
                      <SelectContent>
                        {customers.map((c) => (
                          <SelectItem key={c.id} value={String(c.id)}>
                            {c.display_name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.invoices.پروژه_9")}</Label>
                    <Select
                      value={form.project_id ? String(form.project_id) : ""}
                      onValueChange={(v) => setForm((f) => ({ ...f, project_id: parseInt(v, 10) || 0 }))}
                    >
                      <SelectTrigger>
                        <SelectValue
                          placeholder={
                            form.customer_id
                              ? t("pages.invoices.انتخاب_پروژه")
                              : t("pages.invoices.ابتدا_مشتری")
                          }
                        />
                      </SelectTrigger>
                      <SelectContent>
                        {projects.map((p) => (
                          <SelectItem key={p.id} value={String(p.id)}>
                            {p.title}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.invoices.تاریخ_صدور")}</Label>
                    <DatePicker
                      value={form.issue_date}
                      onChange={(v) => setForm((f) => ({ ...f, issue_date: v }))}
                      required
                    />
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="flex justify-between items-center">
                    <Label>{t("pages.invoices.ردیفهای_خدمات")}</Label>
                    <Button type="button" variant="outline" size="sm" onClick={addItemRow}>
                      {t("pages.invoices.افزودن_ردیف")}
                    </Button>
                  </div>
                  <div className="space-y-2">
                    {form.items.map((row, idx) => (
                      <div key={idx} className="grid grid-cols-12 gap-2 items-end">
                        <div className="col-span-3">
                          <Label>{t("pages.accounting.accountingReports.عنوان")}</Label>
                          <Input
                            value={row.title}
                            onChange={(e) => updateItem(idx, "title", e.target.value)}
                            placeholder={t("pages.invoices.عنوان_خدمت")}
                          />
                        </div>
                        <div className="col-span-3">
                          <Label>{t("common.description")}</Label>
                          <Input
                            value={row.desc}
                            onChange={(e) => updateItem(idx, "desc", e.target.value)}
                          />
                        </div>
                        <div className="col-span-2">
                          <Label>{t("pages.invoices.قیمت_تومان")}</Label>
                          <Input
                            value={row.price}
                            onChange={(e) => updateItem(idx, "price", e.target.value)}
                            placeholder={t("common.placeholderZero")}
                          />
                        </div>
                        <div className="col-span-2">
                          <Label>{t("pages.invoices.تخفیف")}</Label>
                          <Input
                            value={row.discount}
                            onChange={(e) => updateItem(idx, "discount", e.target.value)}
                            placeholder={t("common.placeholderZero")}
                          />
                        </div>
                        <div className="col-span-1">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => removeItemRow(idx)}
                            disabled={form.items.length <= 1}
                          >
                            ×
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="flex flex-wrap gap-4 text-sm">
                    <span>
                      {t("pages.invoices.جمع_کل")}{" "}
                      <strong dir="ltr">{formatNumber(subtotal)}</strong> {t("common.toman")}
                    </span>
                    <span>
                      {t("pages.invoices.تخفیف")}:{" "}
                      <strong dir="ltr">{formatNumber(totalDiscount)}</strong> {t("common.toman")}
                    </span>
                    <span>
                      {t("pages.invoices.مبلغ_نهایی")}:{" "}
                      <strong dir="ltr">{formatNumber(finalTotal)}</strong> {t("common.toman")}
                    </span>
                  </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t("pages.invoices.نحوه_پرداخت")}</Label>
                    <textarea
                      className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                      value={form.payment_method}
                      onChange={(e) => setForm((f) => ({ ...f, payment_method: e.target.value }))}
                      rows={4}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.consultations.یادداشتها")}</Label>
                    <textarea
                      className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                      value={form.notes}
                      onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                      rows={4}
                    />
                  </div>
                </div>

                <Button type="submit" disabled={submitting}>
                  {submitting && (
                    <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />
                  )}
                  {editingInvoice ? t("common.save") : t("pages.invoices.ایجاد_و_ارسال")}
                </Button>
              </form>
            )}
          </CardContent>
        </Card>
      )}

      <InvoiceEmailDialog
        open={emailTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setEmailTarget(null)
            setEmailValue("")
          }
        }}
        email={emailValue}
        setEmail={setEmailValue}
        submitting={submitting}
        onSend={handleSendEmail}
      />
    </div>
  )
}
