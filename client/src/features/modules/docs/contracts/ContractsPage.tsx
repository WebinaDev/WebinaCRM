import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { useNavigate, useSearchParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { ContractWizardStepper } from "./ContractWizardStepper"
import { ContractRelatedProjects } from "./ContractRelatedProjects"
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
import { SELECT_ALL_VALUE } from "@/lib/constants"
import {
  getContracts,
  getContract,
  manageContract,
  deleteContract,
  cancelContract,
  getProjectTemplates,
  getContractProducts,
  getProductProjectsPreview,
  getProjectAssignees,
  type Contract,
  type ContractDetail,
  type ProjectTemplate,
  type WcProduct,
} from "@/api/contracts"
import {
  getLeadForContract,
  createCustomerFromLead,
  type LeadForContract,
} from "@/api/leads"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import { Plus, Loader2, Pencil, Trash2, List, XCircle, ChevronRight, ChevronLeft, Package, UserPlus } from "lucide-react"

export function ContractsPage() {
  const { t, isRtl, formatNumber } = useLocale()
      const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const contractIdParam = searchParams.get("contract_id")
  const editId = contractIdParam ? parseInt(contractIdParam, 10) : null
  const initialTab =
    searchParams.get("action") === "new" || (editId && !Number.isNaN(editId)) ? "new" : "list"
  const fromLeadId = searchParams.get("from_lead") ? parseInt(searchParams.get("from_lead")!, 10) : null

  const [tab, setTab] = useState<"list" | "new">(initialTab)
  const [contracts, setContracts] = useState<Contract[]>([])
  const [customers, setCustomers] = useState<{ id: number; display_name: string }[]>([])
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [deleteTarget, setDeleteTarget] = useState<Contract | null>(null)
  const [cancelConfirm, setCancelConfirm] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const [customerFilter, setCustomerFilter] = useState("")
  const [paymentStatusFilter, setPaymentStatusFilter] = useState("")
  const [editingContract, setEditingContract] = useState<ContractDetail | null>(null)
  const [relatedProjects, setRelatedProjects] = useState<{ id: number; title: string }[]>([])
  const [durations, setDurations] = useState<{ value: string; label: string }[]>([])
  const [models, setModels] = useState<{ value: string; label: string }[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [projectTemplates, setProjectTemplates] = useState<ProjectTemplate[]>([])
  const [createProjectWithTemplate, setCreateProjectWithTemplate] = useState(false)
  const [projectTemplateId, setProjectTemplateId] = useState<string>("")
  const [wizardStep, setWizardStep] = useState(1)
  const [wcProducts, setWcProducts] = useState<WcProduct[]>([])
  const [wcActive, setWcActive] = useState(false)
  const [productId, setProductId] = useState(0)
  const [projectTitlesPreview, setProjectTitlesPreview] = useState<string[]>([])
  const [projectAssignees, setProjectAssignees] = useState<{ id: number; display_name: string }[]>([])
  const [projectAssignments, setProjectAssignments] = useState<number[]>([])
  const [leadForContract, setLeadForContract] = useState<LeadForContract | null>(null)
  const [creatingCustomerFromLead, setCreatingCustomerFromLead] = useState(false)
  const [form, setForm] = useState({
    customer_id: 0,
    start_date: "",
    contract_title: "",
    total_amount: "",
    total_installments: 1,
    duration: "1-month",
    subscription_model: "onetime",
    installments: [] as { amount: string; due_date: string; status: string }[],
  })

  const loadList = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getContracts({
        s: search || undefined,
        customer_filter: customerFilter ? parseInt(customerFilter, 10) : undefined,
        payment_status: paymentStatusFilter || undefined,
        paged: currentPage,
      })
      if (res.success && res.data) {
        setContracts(res.data.contracts ?? [])
        setCustomers(res.data.customers ?? [])
        setTotalPages(res.data.total_pages ?? 1)
        setCurrentPage(res.data.current_page ?? 1)
      } else {
        setError(res.message ?? t("pages.contracts.خطا_در_بارگذاری_قراردادها"))
      }
    } catch {
      setError(t("pages.contracts.خطا_در_بارگذاری_قراردادها"))
    } finally {
      setLoading(false)
    }
  }, [search, customerFilter, paymentStatusFilter, currentPage, t])

  useEffect(() => {
    resetPage()
  }, [search, customerFilter, paymentStatusFilter, resetPage])

  const loadContract = useCallback(async (id: number) => {
    setLoading(true)
    setError(null)
    try {
      const res = await getContract(id)
      if (res.success && res.data) {
        const c = res.data.contract
        setRelatedProjects(res.data.related_projects ?? [])
        setDurations(res.data.durations ?? [])
        setModels(res.data.models ?? [])
        if (res.data.customers?.length) {
          setCustomers(res.data.customers)
        }
        if (c) {
          setEditingContract(c)
          setForm({
            customer_id: c.customer_id,
            start_date: c.start_date ? c.start_date.slice(0, 10) : "",
            contract_title: c.title ?? "",
            total_amount: c.total_amount ?? "",
            total_installments: c.total_installments ?? 1,
            duration: c.duration ?? "1-month",
            subscription_model: c.subscription_model ?? "onetime",
            installments: (c.installments ?? []).map((i) => ({
              amount: i.amount,
              due_date: i.due_date_gregorian ? i.due_date_gregorian.slice(0, 10) : i.due_date,
              status: i.status,
            })),
          })
        } else {
          setEditingContract(null)
          setError(res.message ?? t("pages.contracts.خطا_در_بارگذاری_قرارداد"))
        }
      } else {
        setError(res.message ?? t("pages.contracts.خطا_در_بارگذاری_قرارداد"))
      }
    } catch {
      setError(t("pages.contracts.خطا_در_بارگذاری_قرارداد"))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    if (tab === "list") {
      queueMicrotask(() => {
        void loadList()
      })
    }
  }, [tab, loadList])

  useEffect(() => {
    if (fromLeadId && tab === "new") {
      getLeadForContract(fromLeadId).then((res) => {
        const d = res.success && res.data ? res.data : null
        if (d) {
          setLeadForContract(d)
          if (d.existing_customer_id > 0) {
            setForm((f) => ({ ...f, customer_id: d.existing_customer_id }))
          }
        }
      })
    } else {
      setLeadForContract(null)
    }
  }, [fromLeadId, tab])

  useEffect(() => {
    if (editId && tab === "new") {
      queueMicrotask(() => {
        void loadContract(editId)
      })
    } else if (tab === "new" && !editId) {
      setEditingContract(null)
      setForm({
        customer_id: 0,
        start_date: new Date().toISOString().slice(0, 10),
        contract_title: "",
        total_amount: "",
        total_installments: 1,
        duration: "1-month",
        subscription_model: "onetime",
        installments: [],
      })
      setRelatedProjects([])
      getContracts({}).then((res) => {
        if (res.success && res.data) setCustomers(res.data.customers ?? [])
      })
      setDurations([
        { value: "1-month", label: t("pages.contracts.یک_ماهه") },
        { value: "3-months", label: t("pages.contracts.سه_ماهه") },
        { value: "6-months", label: t("pages.contracts.شش_ماهه") },
        { value: "12-months", label: t("pages.contracts.یک_ساله") },
      ])
      setModels([
        { value: "onetime", label: t("pages.contracts.یکبار_پرداخت") },
        { value: "subscription", label: t("pages.contracts.اشتراکی") },
      ])
    }
  }, [tab, editId, loadContract])

  useEffect(() => {
    if (tab === "new" && !editingContract) {
      getProjectTemplates().then((res) => {
        if (res.success && res.data?.templates) setProjectTemplates(res.data.templates)
      })
      getContractProducts().then((res) => {
        if (res.success && res.data) {
          setWcProducts(res.data.products ?? [])
          setWcActive(res.data.wc_active ?? false)
        }
      })
      getProjectAssignees().then((res) => {
        if (res.success && res.data?.users) setProjectAssignees(res.data.users)
      })
    }
  }, [tab, editingContract])

  useEffect(() => {
    if (productId > 0) {
      getProductProjectsPreview(productId).then((res) => {
        if (res.success && res.data?.titles) {
          setProjectTitlesPreview(res.data.titles)
          setProjectAssignments(res.data.titles.map(() => 0))
        }
      })
    } else {
      setProjectTitlesPreview([])
      setProjectAssignments([])
    }
  }, [productId])

  const openNew = () => {
    navigate("/contracts?action=new")
    setTab("new")
    setLeadForContract(null)
    setEditingContract(null)
    setCreateProjectWithTemplate(false)
    setProjectTemplateId("")
    setWizardStep(1)
    setProductId(0)
    setProjectTitlesPreview([])
    setProjectAssignments([])
  }

  const openEdit = (c: Contract) => {
    navigate(`/contracts?action=new&contract_id=${c.id}`)
    setTab("new")
    loadContract(c.id)
  }

  const backToList = () => {
    navigate("/contracts")
    setTab("list")
    loadList()
  }

  const addInstallmentRow = () => {
    setForm((f) => ({
      ...f,
      installments: [...f.installments, { amount: "", due_date: "", status: "pending" }],
    }))
  }

  const removeInstallmentRow = (idx: number) => {
    setForm((f) => ({
      ...f,
      installments: f.installments.filter((_, i) => i !== idx),
    }))
  }

  const updateInstallment = (idx: number, field: "amount" | "due_date" | "status", value: string) => {
    setForm((f) => {
      const next = [...f.installments]
      next[idx] = { ...next[idx], [field]: value }
      return { ...f, installments: next }
    })
  }

  const calculateInstallments = () => {
    const total = parseInt(form.total_amount.replace(/\D/g, ""), 10) || 0
    const count = form.total_installments || 1
    if (total <= 0 || count <= 0) return
    const perInstallment = Math.floor(total / count)
    const remainder = total - perInstallment * count
    const firstDate = form.start_date ? new Date(form.start_date) : new Date()
    const rows: { amount: string; due_date: string; status: string }[] = []
    for (let i = 0; i < count; i++) {
      const d = new Date(firstDate)
      d.setMonth(d.getMonth() + i)
      const amount = i === count - 1 ? perInstallment + remainder : perInstallment
      rows.push({
        amount: String(amount),
        due_date: d.toISOString().slice(0, 10),
        status: "pending",
      })
    }
    setForm((f) => ({ ...f, installments: rows }))
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.customer_id || !form.start_date) {
      setError(t("pages.contracts.مشتری_و_تاریخ_شروع_الزامی_هستند"))
      return
    }
    if (form.installments.length === 0) {
      setError(t("pages.contracts.حداقل_یک_قسط_باید_ثبت_شود"))
      return
    }
    const hasInvalid = form.installments.some((i) => !i.amount || !i.due_date)
    if (hasInvalid) {
      setError(t("pages.contracts.تمام_اقساط_باید_مبلغ_و_تاریخ_داشته_باشند"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const totalAmount = form.total_amount.replace(/\D/g, "") || String(form.installments.reduce((s, i) => s + (parseInt(i.amount.replace(/\D/g, ""), 10) || 0), 0))
      const res = await manageContract({
        contract_id: editingContract?.id,
        customer_id: form.customer_id,
        _project_start_date: form.start_date,
        contract_title: form.contract_title || undefined,
        total_amount: String(totalAmount),
        total_installments: form.installments.length,
        _project_contract_duration: form.duration,
        _project_subscription_model: form.subscription_model,
        payment_amount: form.installments.map((i) => i.amount.replace(/\D/g, "") || "0"),
        payment_due_date: form.installments.map((i) => i.due_date),
        payment_status: form.installments.map((i) => i.status),
        project_template_id:
          !editingContract && !productId && createProjectWithTemplate && projectTemplateId
            ? parseInt(projectTemplateId, 10)
            : undefined,
        product_id: !editingContract && productId > 0 ? productId : undefined,
        project_assignments: !editingContract && productId > 0 ? projectAssignments : undefined,
        lead_id: !editingContract && fromLeadId ? fromLeadId : undefined,
      })
      if (res.success) {
        const newId = (res.data as { contract_id?: number })?.contract_id
        if (newId && !editingContract) {
          navigate(`/contracts?action=new&contract_id=${newId}`, { replace: true })
          void loadContract(newId)
        } else {
          backToList()
        }
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (c: Contract) => {
    setSubmitting(true)
    setError(null)
    try {
      const res = await deleteContract(c.id, c.delete_nonce)
      if (res.success) {
        setDeleteTarget(null)
        loadList()
      } else setError(res.message ?? t("common.errors.deleteFailed"))
    } catch {
      setError(t("common.errors.deleteFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleCancel = async () => {
    if (!editingContract) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await cancelContract(editingContract.id)
      if (res.success) {
        setCancelConfirm(false)
        loadContract(editingContract.id)
      } else setError(res.message ?? t("pages.contracts.خطا_در_لغو"))
    } catch {
      setError(t("pages.contracts.خطا_در_لغو"))
    } finally {
      setSubmitting(false)
    }
  }

  const wizardSteps = [
    { id: 1, label: t("pages.contracts.مرحله_اطلاعات_پایه") },
    { id: 2, label: t("pages.contracts.مرحله_اقساط") },
    { id: 3, label: t("pages.contracts.مرحله_محصول") },
    { id: 4, label: t("pages.contracts.مرحله_ارجاع_پروژه") },
  ]

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.contracts.مدیریت_قراردادها")}
        isRtl={isRtl}
        actions={
          <div className="flex gap-2">
            <Button variant={tab === "list" ? "default" : "outline"} onClick={() => setTab("list")}>
              <List className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
              {t("pages.contracts.لیست_قراردادها")}
            </Button>
            <Button variant={tab === "new" ? "default" : "outline"} onClick={openNew}>
              <Plus className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
              {t("pages.contracts.قرارداد_جدید")}
            </Button>
          </div>
        }
      />

      <PmAlerts error={error} />

      <PmConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title={t("pages.contracts.confirm.deleteTitle")}
        description={t("pages.contracts.confirm.deletePermanent")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={async () => {
          if (deleteTarget) await handleDelete(deleteTarget)
        }}
        loading={submitting}
        isRtl={isRtl}
      />
      <PmConfirmDialog
        open={cancelConfirm}
        onOpenChange={setCancelConfirm}
        title={t("pages.contracts.confirm.cancelTitle")}
        description={t("pages.contracts.confirm.cancel")}
        confirmLabel={t("pages.contracts.لغو_قرارداد")}
        cancelLabel={t("common.cancel")}
        onConfirm={handleCancel}
        loading={submitting}
        isRtl={isRtl}
      />

      {tab === "list" ? (
        <>
          <PmFilterBar applyLabel={t("pages.accounting.ledger.فیلتر")} onApply={loadList} isRtl={isRtl}>
            <Input
              className="max-w-[200px]"
              placeholder={t("pages.contracts.شماره_یا_عنوان")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <Select
              value={customerFilter === "" ? SELECT_ALL_VALUE : customerFilter}
              onValueChange={(v) => setCustomerFilter(v === SELECT_ALL_VALUE ? "" : v)}
            >
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder={t("pages.accounting.persons.مشتری")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SELECT_ALL_VALUE}>{t("pages.contracts.همه_مشتریان")}</SelectItem>
                {customers.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.display_name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select
              value={paymentStatusFilter === "" ? SELECT_ALL_VALUE : paymentStatusFilter}
              onValueChange={(v) => setPaymentStatusFilter(v === SELECT_ALL_VALUE ? "" : v)}
            >
              <SelectTrigger className="w-[140px]">
                <SelectValue placeholder={t("pages.contracts.وضعیت_پرداخت")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                <SelectItem value="paid">{t("pages.contracts.پرداخت_شده")}</SelectItem>
                <SelectItem value="pending">{t("pages.contracts.در_انتظار")}</SelectItem>
              </SelectContent>
            </Select>
          </PmFilterBar>

          <Card>
            <CardContent className="p-0">
              {loading ? (

                <TableListSkeleton rows={8} columns={6} withAvatarColumn />

              ) : contracts.length === 0 ? (
                <div className="py-12 text-center text-muted-foreground">
                  {t("pages.contracts.هیچ_قراردادی")}
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.accounting.invoices.شماره")}</TableHead>
                      <TableHead>{t("pages.accounting.persons.مشتری")}</TableHead>
                      <TableHead>{t("pages.contracts.مبلغ_کل")}</TableHead>
                      <TableHead>{t("pages.contracts.پرداخت_شده")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                      <TableHead>{t("common.actions")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {contracts.map((c) => (
                      <TableRow key={c.id}>
                        <TableCell>
                          <span className="font-mono">#{c.contract_number}</span>
                          {c.is_cancelled && (
                            <Badge variant="destructive" className="ms-2">{t("pages.contracts.لغو_شده")}</Badge>
                          )}
                        </TableCell>
                        <TableCell>
                          <div>{c.customer_name}</div>
                          {c.customer_email && (
                            <div className="text-xs text-muted-foreground" dir="ltr">{c.customer_email}</div>
                          )}
                        </TableCell>
                        <TableCell dir="ltr">{formatNumber(c.total_amount)} تومان</TableCell>
                        <TableCell>
                          <div className="text-success" dir="ltr">{formatNumber(c.paid_amount)} تومان</div>
                          <div className="text-xs text-muted-foreground">{c.payment_percentage}%</div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={c.status_class === "paid" ? "default" : "secondary"}>
                            {c.status_text}
                          </Badge>
                          {c.installment_count > 0 && (
                            <div className="text-xs text-muted-foreground mt-1">
                              {t("pages.contracts.payment_summary", { paid: c.paid_count, pending: c.pending_count })}
                            </div>
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => openEdit(c)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            {!c.is_cancelled && (
                              <Button
                                variant="outline"
                                size="sm"
                                className="text-destructive"
                                onClick={() => setDeleteTarget(c)}
                                disabled={submitting}
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            )}
                          </div>
                          {c.start_date_jalali !== "-" && (
                            <div className="text-xs text-muted-foreground mt-1">{c.start_date_jalali}</div>
                          )}
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
                {editingContract ? t("pages.contracts.ویرایش_قرارداد") : t("pages.contracts.قرارداد_جدید")}
              </h3>
              <div className="flex gap-2">
                {editingContract && !editingContract.is_cancelled && (
                  <Button variant="outline" size="sm" onClick={() => setCancelConfirm(true)} disabled={submitting}>
                    <XCircle className="h-4 w-4" />
                    {t("pages.contracts.لغو_قرارداد")}
                  </Button>
                )}
                <Button variant="outline" size="sm" onClick={backToList}>
                  {t("common.back")}
                </Button>
              </div>
            </div>

            {editingContract?.is_cancelled && (
              <Alert variant="destructive" className="mb-4">
                <AlertDescription>{t("pages.contracts.این_قرارداد_لغو_شده_است")}</AlertDescription>
              </Alert>
            )}

            {fromLeadId && leadForContract && !editingContract && (
              <Alert className="mb-4">
                <AlertDescription>
                  {t("pages.contracts.from_lead_banner", {
                    name: leadForContract.business_name || `${leadForContract.first_name} ${leadForContract.last_name}`.trim(),
                  })}
                </AlertDescription>
              </Alert>
            )}

            {!editingContract && (
              <ContractWizardStepper steps={wizardSteps} current={wizardStep} isRtl={isRtl} />
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
              {editingContract ? (
                <>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label>{t("pages.appointments.مشتری")}</Label>
                  <Select
                    value={form.customer_id ? String(form.customer_id) : ""}
                    onValueChange={(v) => setForm((f) => ({ ...f, customer_id: parseInt(v, 10) || 0 }))}
                    disabled
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
                  <Label>{t("pages.contracts.تاریخ_شروع")}</Label>
                  <DatePicker
                    value={form.start_date}
                    onChange={(v) => setForm((f) => ({ ...f, start_date: v }))}
                    required
                  />
                </div>
                <div className="space-y-2 sm:col-span-2">
                  <Label>{t("pages.contracts.عنوان_قرارداد")}</Label>
                  <Input
                    value={form.contract_title}
                    onChange={(e) => setForm((f) => ({ ...f, contract_title: e.target.value }))}
                    placeholder={t("pages.contracts.مثال_قرارداد_پشتیبانی_سالانه")}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.contracts.مدت_قرارداد")}</Label>
                  <Select value={form.duration} onValueChange={(v) => setForm((f) => ({ ...f, duration: v }))}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {(durations.length ? durations : [{ value: "1-month", label: t("pages.contracts.یک_ماهه") }, { value: "3-months", label: t("pages.contracts.سه_ماهه") }, { value: "6-months", label: t("pages.contracts.شش_ماهه") }, { value: "12-months", label: t("pages.contracts.یک_ساله") }]).map((d) => (
                        <SelectItem key={d.value} value={d.value}>{d.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.contracts.مدل_اشتراک")}</Label>
                  <Select value={form.subscription_model} onValueChange={(v) => setForm((f) => ({ ...f, subscription_model: v }))}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {(models.length ? models : [{ value: "onetime", label: t("pages.contracts.یکبار_پرداخت") }, { value: "subscription", label: t("pages.contracts.اشتراکی") }]).map((m) => (
                        <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="space-y-4">
                <h4 className="font-medium">{t("pages.contracts.اقساط")}</h4>
                <div className="flex flex-wrap gap-4 items-end p-4 bg-muted/50 rounded-lg">
                  <div className="space-y-2">
                    <Label>{t("pages.contracts.مبلغ_کل_تومان")}</Label>
                    <Input
                      placeholder={t("common.placeholderAmount")}
                      value={form.total_amount}
                      onChange={(e) => setForm((f) => ({ ...f, total_amount: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.contracts.تعداد_اقساط")}</Label>
                    <Input
                      type="number"
                      min={1}
                      value={form.total_installments}
                      onChange={(e) => setForm((f) => ({ ...f, total_installments: parseInt(e.target.value, 10) || 1 }))}
                    />
                  </div>
                  <Button type="button" variant="secondary" onClick={calculateInstallments}>
                    {t("pages.contracts.محاسبه")}
                  </Button>
                </div>
                <div className="flex justify-between items-center">
                  <Label>{t("pages.contracts.لیست_اقساط")}</Label>
                  <Button type="button" variant="outline" size="sm" onClick={addInstallmentRow}>
                    افزودن قسط دستی
                  </Button>
                </div>
                <div className="space-y-2">
                  {form.installments.map((row, idx) => (
                    <div key={idx} className="flex gap-2 items-center">
                      <span className="w-8 text-muted-foreground">#{idx + 1}</span>
                      <Input
                        placeholder={t("pages.accounting.checks.مبلغ")}
                        value={row.amount}
                        onChange={(e) => updateInstallment(idx, "amount", e.target.value)}
                        className="flex-1"
                      />
                      <DatePicker
                        value={row.due_date}
                        onChange={(v) => updateInstallment(idx, "due_date", v)}
                        className="flex-1"
                      />
                      <Select value={row.status} onValueChange={(v) => updateInstallment(idx, "status", v)}>
                        <SelectTrigger className="w-[130px]">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="pending">{t("pages.contracts.در_انتظار")}</SelectItem>
                          <SelectItem value="paid">{t("pages.contracts.پرداخت_شده")}</SelectItem>
                          <SelectItem value="cancelled">{t("pages.contracts.لغو_شده")}</SelectItem>
                        </SelectContent>
                      </Select>
                      <Button type="button" variant="ghost" size="icon" onClick={() => removeInstallmentRow(idx)}>
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>

              <div className="flex gap-4">
                <Button type="submit" disabled={submitting || (editingContract?.is_cancelled ?? false)}>
                  {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
                  {t("common.saveChanges")}
                </Button>
                <Button type="button" variant="outline" onClick={backToList}>
                  {t("common.cancel")}
                </Button>
              </div>
                </>
              ) : (
                <>
              {(wizardStep === 1) && (
                <div className="grid gap-4 sm:grid-cols-2">
                  {leadForContract && (
                    <div className="sm:col-span-2 rounded-lg border p-4 bg-muted/30 space-y-2">
                      <p className="text-sm font-medium">
                        {t("pages.contracts.سرنخ_خلاصه", {
                          id: leadForContract.lead_id,
                          name: `${leadForContract.first_name} ${leadForContract.last_name}`,
                          mobile: leadForContract.mobile,
                        })}
                      </p>
                      {leadForContract.existing_customer_id > 0 ? (
                        <p className="text-sm text-muted-foreground">{t("pages.contracts.مشتری_با_این_موبایلایمیل_از_قبل_وجود_دار")}</p>
                      ) : (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={creatingCustomerFromLead}
                          onClick={async () => {
                            setCreatingCustomerFromLead(true)
                            setError(null)
                            const res = await createCustomerFromLead(leadForContract.lead_id)
                            setCreatingCustomerFromLead(false)
                            if (res.success && res.data?.customer_id) {
                              setForm((f) => ({ ...f, customer_id: res.data!.customer_id }))
                              getContracts({}).then((r) => {
                                if (r.success && r.data) setCustomers(r.data.customers ?? [])
                              })
                            } else {
                              setError(res.message ?? t("pages.contracts.خطا_در_ایجاد_مشتری"))
                            }
                          }}
                        >
                          {creatingCustomerFromLead ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                          {t("pages.contracts.ایجاد_مشتری_از_سرنخ")}
                        </Button>
                      )}
                    </div>
                  )}
                  <div className="space-y-2">
                    <Label>{t("pages.appointments.مشتری")}</Label>
                    <Select value={form.customer_id ? String(form.customer_id) : ""} onValueChange={(v) => setForm((f) => ({ ...f, customer_id: parseInt(v, 10) || 0 }))}>
                      <SelectTrigger><SelectValue placeholder={t("pages.appointments.انتخاب_مشتری")} /></SelectTrigger>
                      <SelectContent>
                        {customers.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.display_name}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.contracts.تاریخ_شروع")}</Label>
                    <DatePicker value={form.start_date} onChange={(v) => setForm((f) => ({ ...f, start_date: v }))} required />
                  </div>
                  <div className="space-y-2 sm:col-span-2">
                    <Label>{t("pages.contracts.عنوان_قرارداد")}</Label>
                    <Input value={form.contract_title} onChange={(e) => setForm((f) => ({ ...f, contract_title: e.target.value }))} placeholder={t("pages.contracts.مثال_قرارداد_پشتیبانی_سالانه")} />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.contracts.مدت_قرارداد")}</Label>
                    <Select value={form.duration} onValueChange={(v) => setForm((f) => ({ ...f, duration: v }))}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {(durations.length ? durations : [{ value: "1-month", label: t("pages.contracts.یک_ماهه") }, { value: "3-months", label: t("pages.contracts.سه_ماهه") }, { value: "6-months", label: t("pages.contracts.شش_ماهه") }, { value: "12-months", label: t("pages.contracts.یک_ساله") }]).map((d) => <SelectItem key={d.value} value={d.value}>{d.label}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.contracts.مدل_اشتراک")}</Label>
                    <Select value={form.subscription_model} onValueChange={(v) => setForm((f) => ({ ...f, subscription_model: v }))}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {(models.length ? models : [{ value: "onetime", label: t("pages.contracts.یکبار_پرداخت") }, { value: "subscription", label: t("pages.contracts.اشتراکی") }]).map((m) => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              )}
              {(wizardStep === 2) && (
                <div className="space-y-4">
                  <h4 className="font-medium">{t("pages.contracts.اقساط")}</h4>
                  <div className="flex flex-wrap gap-4 items-end p-4 bg-muted/50 rounded-lg">
                    <div className="space-y-2">
                      <Label>{t("pages.contracts.مبلغ_کل_تومان")}</Label>
                      <Input placeholder={t("common.placeholderAmount")} value={form.total_amount} onChange={(e) => setForm((f) => ({ ...f, total_amount: e.target.value }))} />
                    </div>
                    <div className="space-y-2">
                      <Label>{t("pages.contracts.تعداد_اقساط")}</Label>
                      <Input type="number" min={1} value={form.total_installments} onChange={(e) => setForm((f) => ({ ...f, total_installments: parseInt(e.target.value, 10) || 1 }))} />
                    </div>
                    <Button type="button" variant="secondary" onClick={calculateInstallments}>{t("pages.contracts.محاسبه")}</Button>
                  </div>
                  <div className="flex justify-between items-center">
                    <Label>{t("pages.contracts.لیست_اقساط")}</Label>
                    <Button type="button" variant="outline" size="sm" onClick={addInstallmentRow}>{t("pages.contracts.افزودن_قسط_دستی")}</Button>
                  </div>
                  <div className="space-y-2">
                    {form.installments.map((row, idx) => (
                      <div key={idx} className="flex gap-2 items-center">
                        <span className="w-8 text-muted-foreground">#{idx + 1}</span>
                        <Input placeholder={t("pages.accounting.checks.مبلغ")} value={row.amount} onChange={(e) => updateInstallment(idx, "amount", e.target.value)} className="flex-1" />
                        <DatePicker value={row.due_date} onChange={(v) => updateInstallment(idx, "due_date", v)} className="flex-1" />
                        <Select value={row.status} onValueChange={(v) => updateInstallment(idx, "status", v)}>
                          <SelectTrigger className="w-[130px]"><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="pending">{t("pages.contracts.در_انتظار")}</SelectItem>
                            <SelectItem value="paid">{t("pages.contracts.پرداخت_شده")}</SelectItem>
                            <SelectItem value="cancelled">{t("pages.contracts.لغو_شده")}</SelectItem>
                          </SelectContent>
                        </Select>
                        <Button type="button" variant="ghost" size="icon" onClick={() => removeInstallmentRow(idx)}><Trash2 className="h-4 w-4 text-destructive" /></Button>
                      </div>
                    ))}
                  </div>
                </div>
              )}
              {(wizardStep === 3) && (
                <div className="space-y-4">
                  <h4 className="font-medium flex items-center gap-2"><Package className="h-5 w-5" /> {t("pages.contracts.انتخاب_محصول_ووکامرس_عنوان")}</h4>
                  {wcActive ? (
                    <>
                      <Select value={productId > 0 ? String(productId) : "_none"} onValueChange={(v) => setProductId(v === "_none" ? 0 : parseInt(v, 10))}>
                        <SelectTrigger className="max-w-md">
                          <SelectValue placeholder={t("pages.contracts.انتخاب_محصول_اختیاری")} />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="_none">{t("pages.contracts.بدون_محصول")}</SelectItem>
                          {wcProducts.map((p) => (
                            <SelectItem key={p.id} value={String(p.id)}>{p.name} ({p.type}) — {p.price}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      {productId === 0 && (
                        <div className="rounded-lg border p-4 space-y-4 bg-muted/30">
                          <div className="flex items-center gap-2">
                            <input type="checkbox" id="create-project-template" checked={createProjectWithTemplate} onChange={(e) => { setCreateProjectWithTemplate(e.target.checked); if (!e.target.checked) setProjectTemplateId("") }} className="h-4 w-4 rounded border-input" />
                            <Label htmlFor="create-project-template" className="cursor-pointer font-medium">{t("pages.contracts.ایجاد_پروژه_با_قالب")}</Label>
                          </div>
                          {createProjectWithTemplate && (
                            <Select value={projectTemplateId} onValueChange={setProjectTemplateId}>
                              <SelectTrigger className="max-w-md"><SelectValue placeholder={t("pages.contracts.انتخاب_قالب")} /></SelectTrigger>
                              <SelectContent>
                                {projectTemplates.map((t) => <SelectItem key={t.id} value={String(t.id)}>{t.title}</SelectItem>)}
                                {projectTemplates.length === 0 && <SelectItem value="__none__" disabled>{t("pages.contracts.قالبی_یافت_نشد")}</SelectItem>}
                              </SelectContent>
                            </Select>
                          )}
                        </div>
                      )}
                    </>
                  ) : (
                    <p className="text-muted-foreground">{t("pages.contracts.ووکامرس_فعال_نیست_از_قالب_پروژه_استفاده_")}</p>
                  )}
                </div>
              )}
              {(wizardStep === 4) && (
                <div className="space-y-4">
                  <h4 className="font-medium flex items-center gap-2"><UserPlus className="h-5 w-5" /> {t("pages.contracts.ارجاع_پروژهها_عنوان")}</h4>
                  {projectTitlesPreview.length > 0 ? (
                    <div className="space-y-3">
                      {projectTitlesPreview.map((title, idx) => (
                        <div key={idx} className="flex items-center gap-4">
                          <span className="font-medium min-w-[180px]">{title}</span>
                          <Select value={projectAssignments[idx] > 0 ? String(projectAssignments[idx]) : "_none"} onValueChange={(v) => setProjectAssignments((prev) => { const n = [...prev]; n[idx] = v === "_none" ? 0 : parseInt(v, 10); return n })}>
                            <SelectTrigger className="max-w-[220px]"><SelectValue placeholder={t("pages.contracts.ارجاع_به")} /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value="_none">—</SelectItem>
                              {projectAssignees.map((u) => <SelectItem key={u.id} value={String(u.id)}>{u.display_name}</SelectItem>)}
                            </SelectContent>
                          </Select>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="text-muted-foreground">{t("pages.contracts.محصولی_انتخاب_نشده_یا_پروژهای_از_آن_ایجا")}</p>
                  )}
                </div>
              )}
              <div className="flex gap-4">
                {wizardStep > 1 && (
                  <Button type="button" variant="outline" onClick={() => setWizardStep((s) => s - 1)}>
                    <ChevronRight className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                    {t("common.prevPage")}
                  </Button>
                )}
                {wizardStep < 4 ? (
                  <Button
                    type="button"
                    onClick={() => setWizardStep((s) => s + 1)}
                    disabled={
                      (wizardStep === 1 && (!form.customer_id || !form.start_date)) ||
                      (wizardStep === 2 && (form.installments.length === 0 || form.installments.some((i) => !i.amount || !i.due_date)))
                    }
                  >
                    {t("common.nextPage")}
                    <ChevronLeft className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                  </Button>
                ) : (
                  <Button type="submit" disabled={submitting}>
                    {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
                    {t("pages.contracts.ایجاد_قرارداد")}
                  </Button>
                )}
                <Button type="button" variant="outline" onClick={backToList}>{t("common.cancel")}</Button>
              </div>
                </>
              )}
            </form>

            {editingContract && (
              <div className="mt-8 pt-8 border-t">
                <ContractRelatedProjects
                  contractId={editingContract.id}
                  projects={relatedProjects}
                  onAdded={(p) => setRelatedProjects((prev) => [...prev, p])}
                />
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
