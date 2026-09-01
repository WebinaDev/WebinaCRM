import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Badge } from "@/components/ui/badge"
import {
  getLeads,
  addLead,
  assignLead,
  getLeadAssignees,
  editLead,
  deleteLead,
  changeLeadStatus,
  type Lead,
  type GetLeadsResponse,
} from "@/api/leads"
import { Plus, Loader2, UserPlus, Eye, FileText, Pencil, Trash2, Download, LayoutGrid, LayoutList } from "lucide-react"
import { PmViewToggle } from "@/features/shared/pm/PmViewToggle"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { exportLeads, importLeads } from "@/api/leads"
import { ImportCsvButton } from "../components/ImportCsvButton"
import { useNavigate } from "react-router-dom"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"

const STATUS_BADGE_VARIANTS: Record<string, string> = {
  new: "secondary",
  assigned: "default",
  "in-progress": "outline",
  contracted: "default",
  converted: "default",
  cancelled: "destructive",
}

function StatusBadge({ slug, name }: { slug: string; name: string }) {
  const v = STATUS_BADGE_VARIANTS[slug] ?? "secondary"
  return <Badge variant={v as "default" | "secondary" | "destructive" | "outline"}>{name}</Badge>
}

type LeadFormState = {
  first_name: string
  last_name: string
  mobile: string
  email: string
  business_name: string
  gender: string
  notes: string
  lead_source: string
  campaign_id: number
  lead_status: string
}

const emptyForm = (): LeadFormState => ({
  first_name: "",
  last_name: "",
  mobile: "",
  email: "",
  business_name: "",
  gender: "",
  notes: "",
  lead_source: "",
  campaign_id: 0,
  lead_status: "",
})

function LeadFormFields({
  form,
  setForm,
  leadSources,
  campaigns,
  statuses,
  showStatus,
  t,
}: {
  form: LeadFormState
  setForm: React.Dispatch<React.SetStateAction<LeadFormState>>
  leadSources: { slug: string; name: string }[]
  campaigns: { id: number; title: string }[]
  statuses: { slug: string; name: string }[]
  showStatus: boolean
  t: (k: string) => string
}) {
  return (
    <>
      <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
          <Label htmlFor="lf_first">{t("pages.accounting.cashAccounts.نام_1")}</Label>
          <Input id="lf_first" value={form.first_name} onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))} required />
        </div>
        <div className="space-y-2">
          <Label htmlFor="lf_last">{t("pages.customers.نام_خانوادگی")}</Label>
          <Input id="lf_last" value={form.last_name} onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))} required />
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="lf_mobile">{t("pages.leads.شماره_موبایل")}</Label>
        <Input id="lf_mobile" type="tel" dir="ltr" value={form.mobile} onChange={(e) => setForm((f) => ({ ...f, mobile: e.target.value }))} required />
      </div>
      <div className="space-y-2">
        <Label htmlFor="lf_email">{t("pages.consultations.ایمیل")}</Label>
        <Input id="lf_email" type="email" dir="ltr" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="lf_business">{t("pages.leads.نام_کسبوکار")}</Label>
        <Input id="lf_business" value={form.business_name} onChange={(e) => setForm((f) => ({ ...f, business_name: e.target.value }))} />
      </div>
      {showStatus && statuses.length > 0 && (
        <div className="space-y-2">
          <Label>{t("common.status")}</Label>
          <Select value={form.lead_status || undefined} onValueChange={(v) => setForm((f) => ({ ...f, lead_status: v }))}>
            <SelectTrigger><SelectValue placeholder={t("pages.leads.انتخاب_کنید")} /></SelectTrigger>
            <SelectContent>
              {statuses.map((s) => (
                <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>{t("pages.leads.منبع_ورود")}</Label>
          <Select value={form.lead_source || "_none"} onValueChange={(v) => setForm((f) => ({ ...f, lead_source: v === "_none" ? "" : v }))}>
            <SelectTrigger><SelectValue placeholder={t("pages.leads.انتخاب_کنید")} /></SelectTrigger>
            <SelectContent>
              <SelectItem value="_none">—</SelectItem>
              {leadSources.map((s) => (
                <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>{t("pages.leads.کمپین")}</Label>
          <Select value={form.campaign_id > 0 ? String(form.campaign_id) : "_none"} onValueChange={(v) => setForm((f) => ({ ...f, campaign_id: v === "_none" ? 0 : parseInt(v, 10) }))}>
            <SelectTrigger><SelectValue placeholder={t("pages.leads.انتخاب_کنید")} /></SelectTrigger>
            <SelectContent>
              <SelectItem value="_none">—</SelectItem>
              {campaigns.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>{c.title}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
      <div className="space-y-2">
        <Label>{t("pages.leads.جنسیت")}</Label>
        <Select value={form.gender || "_none"} onValueChange={(v) => setForm((f) => ({ ...f, gender: v === "_none" ? "" : v }))}>
          <SelectTrigger><SelectValue placeholder={t("pages.leads.انتخاب_کنید")} /></SelectTrigger>
          <SelectContent>
            <SelectItem value="_none">—</SelectItem>
            <SelectItem value="male">{t("pages.leads.آقا")}</SelectItem>
            <SelectItem value="female">{t("pages.leads.خانم")}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      
      <div className="space-y-2">
        <Label htmlFor="lf_notes">{t("pages.appointments.یادداشت")}</Label>
        <Input id="lf_notes" value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} />
      </div>
    </>
  )
}

export function LeadsPage() {
  const { t, isRtl, formatDate } = useLocale()
  const navigate = useNavigate()
  const [leads, setLeads] = useState<Lead[]>([])
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [currentPage, setCurrentPage] = useState(1)
  const [statuses, setStatuses] = useState<{ slug: string; name: string }[]>([])
  const [leadSources, setLeadSources] = useState<{ slug: string; name: string }[]>([])
  const [campaigns, setCampaigns] = useState<{ id: number; title: string }[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [openAdd, setOpenAdd] = useState(false)
  const [openEdit, setOpenEdit] = useState<Lead | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [form, setForm] = useState<LeadFormState>(emptyForm())
  const [openAssign, setOpenAssign] = useState<Lead | null>(null)
  const [assignTo, setAssignTo] = useState(0)
  const [assignNote, setAssignNote] = useState("")
  const [assignSubmitting, setAssignSubmitting] = useState(false)
  const [assignees, setAssignees] = useState<{ id: number; display_name: string }[]>([])
  const [assigneesLoading, setAssigneesLoading] = useState(false)
  const [openDetail, setOpenDetail] = useState<Lead | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Lead | null>(null)
  const [viewMode, setViewMode] = useState<"table" | "card">("table")
  const [statusUpdating, setStatusUpdating] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getLeads({
        paged: currentPage,
        per_page: 20,
        s: search.trim() || undefined,
        status_filter: statusFilter || undefined,
      })
      if (res.success && res.data) {
        const d = res.data as GetLeadsResponse
        setLeads(d.leads)
        setTotal(d.total)
        setTotalPages(Math.max(1, d.pages ?? 1))
        setStatuses(d.statuses ?? [])
        setLeadSources(d.lead_sources ?? [])
        setCampaigns(d.campaigns ?? [])
      } else {
        setError(res.message ?? t("pages.leads.خطا_در_بارگذاری_سرنخها"))
      }
    } catch {
      setError(t("pages.leads.خطا_در_بارگذاری_سرنخها"))
    } finally {
      setLoading(false)
    }
  }, [currentPage, search, statusFilter, t])

  useEffect(() => {
    const timer = setTimeout(() => void load(), search ? 300 : 0)
    return () => clearTimeout(timer)
  }, [load, search])

  useEffect(() => {
    setCurrentPage(1)
  }, [statusFilter])

  useEffect(() => {
    if (openAssign) {
      void (async () => {
        setAssigneesLoading(true)
        try {
          const res = await getLeadAssignees()
          if (res.success && res.data?.users) setAssignees(res.data.users)
        } finally {
          setAssigneesLoading(false)
        }
      })()
      setAssignTo(openAssign.assigned_to ?? 0)
      setAssignNote("")
    }
  }, [openAssign])

  const clearMessages = () => {
    setError(null)
    setSuccess(null)
  }

  const handleAdd = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.first_name.trim() || !form.last_name.trim() || !form.mobile.trim()) {
      setError(t("pages.leads.نام،_نام_خانوادگی_و_شماره_موبایل_ضروری_ه"))
      return
    }
    setSubmitting(true)
    clearMessages()
    const res = await addLead({
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      mobile: form.mobile.trim(),
      email: form.email.trim() || undefined,
      business_name: form.business_name.trim() || undefined,
      gender: form.gender || undefined,
      notes: form.notes.trim() || undefined,
      lead_source: form.lead_source.trim() || undefined,
      campaign_id: form.campaign_id > 0 ? form.campaign_id : undefined,
    })
    setSubmitting(false)
    if (res.success) {
      setOpenAdd(false)
      setForm(emptyForm())
      setSuccess((res.message as string) || t("common.save"))
      void load()
    } else {
      setError(res.message ?? t("pages.leads.خطا_در_ثبت_سرنخ"))
    }
  }

  const openEditDialog = (lead: Lead) => {
    clearMessages()
    setOpenEdit(lead)
    setForm({
      first_name: lead.first_name,
      last_name: lead.last_name,
      mobile: lead.mobile,
      email: lead.email ?? "",
      business_name: lead.business_name ?? "",
      gender: lead.gender ?? "",
      notes: lead.notes ?? "",
      lead_source: lead.lead_source_slug ?? "",
      campaign_id: lead.campaign_id ?? 0,
      lead_status: lead.status_slug,
    })
  }

  const handleEdit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!openEdit) return
    if (!form.first_name.trim() || !form.last_name.trim() || !form.mobile.trim()) {
      setError(t("pages.leads.نام،_نام_خانوادگی_و_شماره_موبایل_ضروری_ه"))
      return
    }
    setSubmitting(true)
    clearMessages()
    const res = await editLead(openEdit.id, {
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      mobile: form.mobile.trim(),
      email: form.email.trim(),
      business_name: form.business_name.trim(),
      gender: form.gender,
      notes: form.notes.trim(),
      lead_source: form.lead_source,
      campaign_id: form.campaign_id > 0 ? form.campaign_id : 0,
      lead_status: form.lead_status,
    })
    setSubmitting(false)
    if (res.success) {
      setOpenEdit(null)
      setForm(emptyForm())
      setSuccess((res.message as string) || t("common.save"))
      void load()
    } else {
      setError(res.message ?? t("common.errors.saveFailed"))
    }
  }

  const handleDelete = async (lead: Lead) => {
    setSubmitting(true)
    clearMessages()
    const res = await deleteLead(lead.id)
    setSubmitting(false)
    if (res.success) {
      setDeleteTarget(null)
      setSuccess((res.message as string) || t("common.delete"))
      if (openDetail?.id === lead.id) setOpenDetail(null)
      void load()
    } else {
      setError(res.message ?? t("common.errors.deleteFailed"))
    }
  }

  const handleQuickStatus = async (lead: Lead, newStatus: string) => {
    if (newStatus === lead.status_slug) return
    setStatusUpdating(lead.id)
    clearMessages()
    const res = await changeLeadStatus(lead.id, newStatus)
    setStatusUpdating(null)
    if (res.success) {
      setSuccess((res.message as string) || t("common.save"))
      void load()
    } else {
      setError(res.message ?? t("common.errors.saveFailed"))
    }
  }

  const handleExport = async () => {
    setSubmitting(true)
    try {
      const res = await exportLeads()
      if (res.success && res.data?.file_url) {
        window.open(res.data.file_url, "_blank")
        setSuccess(res.data.message ?? t("pages.leads.export_done"))
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleImport = async (file: File) => {
    clearMessages()
    const res = await importLeads(file)
    if (res.success && res.data) {
      const errCount = res.data.errors?.length ?? 0
      const base = res.data.message ?? t("pages.leads.import_done")
      setSuccess(errCount > 0 ? `${base} (${t("pages.leads.import_errors", { count: errCount })})` : base)
      void load()
    } else {
      setError(res.message ?? t("common.errors.saveFailed"))
    }
  }

  const handleAssign = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!openAssign) return
    setAssignSubmitting(true)
    clearMessages()
    const res = await assignLead(openAssign.id, assignTo, assignNote || undefined)
    setAssignSubmitting(false)
    if (res.success) {
      setOpenAssign(null)
      setSuccess((res.message as string) || t("common.save"))
      void load()
    } else {
      setError(res.message ?? t("pages.leads.خطا_در_ارجاع"))
    }
  }

  const renderFormDialog = (
    open: boolean,
    onOpenChange: (o: boolean) => void,
    title: string,
    onSubmit: (e: React.FormEvent) => void,
    showStatus: boolean
  ) => (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <LeadFormFields
            form={form}
            setForm={setForm}
            leadSources={leadSources}
            campaigns={campaigns}
            statuses={statuses}
            showStatus={showStatus}
            t={t}
          />
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="submit" disabled={submitting}>
              {t("common.save")}
              {submitting ? <Loader2 className="h-4 w-4 animate-spin ml-2" /> : null}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.leads.سرنخها")}
        isRtl={isRtl}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={handleExport} disabled={submitting}>
              <Download className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.leads.export_csv")}
            </Button>
            <ImportCsvButton
              label={t("pages.leads.import_csv")}
              disabled={submitting}
              onImport={handleImport}
            />
            <Button
              size="sm"
              onClick={() => {
                clearMessages()
                setForm(emptyForm())
                setOpenAdd(true)
              }}
            >
              <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.leads.افزودن_سرنخ")}
            </Button>
          </div>
        }
      />

      <PmAlerts error={error} success={success} />

      <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void load()} isRtl={isRtl}>
        <Input
          placeholder={t("common.search")}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-48"
        />
        {statuses.length > 0 && (
          <Select value={statusFilter || "_all"} onValueChange={(v) => setStatusFilter(v === "_all" ? "" : v)}>
            <SelectTrigger className="w-40">
              <SelectValue placeholder={t("pages.accounting.checks.همه_وضعیتها")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="_all">{t("pages.accounting.checks.همه_وضعیتها")}</SelectItem>
              {statuses.map((s) => (
                <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </PmFilterBar>

      <Card>
        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
          <CardTitle>
            {t("pages.leads.listTitle")}
            {total > 0 ? ` (${total})` : ""}
          </CardTitle>
          <PmViewToggle
            value={viewMode}
            onChange={setViewMode}
            isRtl={isRtl}
            options={[
              { id: "table", label: t("pages.leads.view_table"), icon: <LayoutList className="h-4 w-4" /> },
              { id: "card", label: t("pages.leads.view_cards"), icon: <LayoutGrid className="h-4 w-4" /> },
            ]}
          />
        </CardHeader>
        <CardContent>
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : viewMode === "card" ? (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {leads.length === 0 ? (
                <p className="col-span-full text-center text-muted-foreground py-8">{t("common.noResults")}</p>
              ) : (
                leads.map((lead) => (
                  <Card key={lead.id} className="overflow-hidden">
                    <CardContent className="pt-4 space-y-3">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <p className="font-semibold">{lead.first_name} {lead.last_name}</p>
                          <p className="text-sm text-muted-foreground" dir="ltr">{lead.mobile}</p>
                        </div>
                        <StatusBadge slug={lead.status_slug} name={lead.status_name} />
                      </div>
                      {statuses.length > 0 && (
                        <Select
                          value={lead.status_slug}
                          onValueChange={(v) => void handleQuickStatus(lead, v)}
                          disabled={statusUpdating === lead.id}
                        >
                          <SelectTrigger className="h-8 text-xs">
                            <SelectValue placeholder={t("pages.leads.change_status")} />
                          </SelectTrigger>
                          <SelectContent>
                            {statuses.map((s) => (
                              <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      )}
                      <div className="flex flex-wrap gap-1">
                        <Button variant="ghost" size="sm" onClick={() => { clearMessages(); setOpenDetail(lead) }}><Eye className="h-4 w-4" /></Button>
                        <Button variant="ghost" size="sm" onClick={() => openEditDialog(lead)}><Pencil className="h-4 w-4" /></Button>
                        <Button variant="ghost" size="sm" onClick={() => { clearMessages(); setOpenAssign(lead) }}><UserPlus className="h-4 w-4" /></Button>
                        <Button variant="ghost" size="sm" onClick={() => navigate(`/contracts?action=new&from_lead=${lead.id}`)}><FileText className="h-4 w-4" /></Button>
                      </div>
                    </CardContent>
                  </Card>
                ))
              )}
            </div>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("common.name")}</TableHead>
                    <TableHead>{t("pages.accounting.persons.موبایل")}</TableHead>
                    <TableHead>{t("pages.consultations.ایمیل")}</TableHead>
                    <TableHead>{t("pages.leads.منبع")}</TableHead>
                    <TableHead>{t("pages.leads.ارجاع_به")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead>{t("common.date")}</TableHead>
                    <TableHead className="w-32">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {leads.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={8} className="text-center text-muted-foreground py-8">
                        {t("common.noResults")}
                      </TableCell>
                    </TableRow>
                  ) : (
                    leads.map((lead) => (
                      <TableRow key={lead.id}>
                        <TableCell className="font-medium">{lead.first_name} {lead.last_name}</TableCell>
                        <TableCell dir="ltr">{lead.mobile}</TableCell>
                        <TableCell dir="ltr">{lead.email || "—"}</TableCell>
                        <TableCell>{lead.lead_source_name || "—"}</TableCell>
                        <TableCell>{lead.assigned_to_name || "—"}</TableCell>
                        <TableCell>
                          {statuses.length > 0 ? (
                            <Select
                              value={lead.status_slug}
                              onValueChange={(v) => void handleQuickStatus(lead, v)}
                              disabled={statusUpdating === lead.id}
                            >
                              <SelectTrigger className="h-8 w-[130px] text-xs">
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                {statuses.map((s) => (
                                  <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          ) : (
                            <StatusBadge slug={lead.status_slug} name={lead.status_name} />
                          )}
                        </TableCell>
                        <TableCell>{formatDate(lead.date)}</TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            <Button variant="ghost" size="sm" onClick={() => { clearMessages(); setOpenDetail(lead) }} title={t("pages.leads.مشاهده")}><Eye className="h-4 w-4" /></Button>
                            <Button variant="ghost" size="sm" onClick={() => openEditDialog(lead)} title={t("common.edit")}><Pencil className="h-4 w-4" /></Button>
                            <Button variant="ghost" size="sm" onClick={() => { clearMessages(); setOpenAssign(lead) }} title={t("pages.leads.ارجاع")}><UserPlus className="h-4 w-4" /></Button>
                            <Button variant="ghost" size="sm" onClick={() => navigate(`/contracts?action=new&from_lead=${lead.id}`)} title={t("pages.leads.ایجاد_قرارداد")}><FileText className="h-4 w-4" /></Button>
                            <Button variant="ghost" size="sm" className="text-destructive hover:text-destructive" onClick={() => setDeleteTarget(lead)} disabled={submitting} title={t("common.delete")}><Trash2 className="h-4 w-4" /></Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
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

      <PmConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title={t("common.delete")}
        description={t("pages.leads.confirm.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        onConfirm={async () => {
          if (deleteTarget) await handleDelete(deleteTarget)
        }}
      />

      {renderFormDialog(openAdd, setOpenAdd, t("pages.leads.افزودن_سرنخ"), handleAdd, false)}
      {renderFormDialog(!!openEdit, (o) => { if (!o) setOpenEdit(null) }, t("pages.leads.editTitle"), handleEdit, true)}

      <Dialog open={!!openAssign} onOpenChange={(o) => !o && setOpenAssign(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {t("pages.leads.assignTitle")}
              {openAssign && <span className="font-normal text-muted-foreground mr-2">— {openAssign.first_name} {openAssign.last_name}</span>}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleAssign} className="space-y-4">
            <div className="space-y-2">
              <Label>{t("pages.leads.ارجاع_به_کارشناس_فروش")}</Label>
              {assigneesLoading ? (
                <div className="flex items-center gap-2 py-2"><Loader2 className="h-4 w-4 animate-spin" /><span className="text-sm text-muted-foreground">{t("common.loading")}</span></div>
              ) : (
                <Select value={assignTo > 0 ? String(assignTo) : "_none"} onValueChange={(v) => setAssignTo(v === "_none" ? 0 : parseInt(v, 10))}>
                  <SelectTrigger><SelectValue placeholder={t("pages.leads.انتخاب_کنید")} /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="_none">{t("pages.leads.بدون_ارجاع")}</SelectItem>
                    {assignees.map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>{u.display_name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="assign_note">{t("pages.leads.یادداشت_ارجاع")}</Label>
              <Input id="assign_note" value={assignNote} onChange={(e) => setAssignNote(e.target.value)} placeholder={t("pages.leads.توضیح_مختصر_برای_ارجاع")} />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setOpenAssign(null)}>{t("common.cancel")}</Button>
              <Button type="submit" disabled={assignSubmitting}>{t("pages.leads.ارجاع")}{assignSubmitting ? <Loader2 className="h-4 w-4 animate-spin ml-2" /> : null}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={!!openDetail} onOpenChange={(o) => !o && setOpenDetail(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader><DialogTitle>{t("pages.leads.جزئیات_سرنخ")}</DialogTitle></DialogHeader>
          {openDetail && (
            <div className="space-y-3 text-sm">
              <div className="flex flex-wrap gap-2 justify-end mb-2">
                <Button size="sm" variant="outline" onClick={() => { setOpenDetail(null); openEditDialog(openDetail) }}><Pencil className="h-4 w-4 ml-2" />{t("common.edit")}</Button>
                <Button size="sm" onClick={() => { setOpenDetail(null); navigate(`/contracts?action=new&from_lead=${openDetail.id}`) }}><FileText className="h-4 w-4 ml-2" />{t("pages.leads.ایجاد_قرارداد")}</Button>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <span className="text-muted-foreground">{t("pages.leads.نام")}</span>
                <span>{openDetail.first_name} {openDetail.last_name}</span>
                <span className="text-muted-foreground">{t("pages.leads.موبایل")}</span>
                <span dir="ltr">{openDetail.mobile}</span>
                <span className="text-muted-foreground">{t("pages.leads.ایمیل")}</span>
                <span dir="ltr">{openDetail.email || "—"}</span>
                <span className="text-muted-foreground">{t("pages.leads.نام_کسبوکار_11")}</span>
                <span>{openDetail.business_name || "—"}</span>
                <span className="text-muted-foreground">{t("pages.leads.منبع_ورود_12")}</span>
                <span>{openDetail.lead_source_name || "—"}</span>
                <span className="text-muted-foreground">{t("pages.leads.ارجاع_به_13")}</span>
                <span>{openDetail.assigned_to_name || "—"}</span>
                <span className="text-muted-foreground">{t("pages.leads.وضعیت")}</span>
                <span><StatusBadge slug={openDetail.status_slug} name={openDetail.status_name} /></span>
                <span className="text-muted-foreground">{t("pages.leads.تاریخ")}</span>
                <span>{openDetail.date}</span>
                {openDetail.last_assignment_note ? (
                  <>
                    <span className="text-muted-foreground">{t("pages.leads.یادداشت_ارجاع_14")}</span>
                    <span>{openDetail.last_assignment_note}</span>
                  </>
                ) : null}
                {openDetail.notes ? (
                  <>
                    <span className="text-muted-foreground">{t("pages.leads.یادداشت")}</span>
                    <span className="whitespace-pre-wrap">{openDetail.notes}</span>
                  </>
                ) : null}
              </div>
              {openDetail.form_submission_data && openDetail.form_submission_data.length > 0 ? (
                <div className="mt-4 pt-4 border-t">
                  <h4 className="font-medium mb-2">{t("pages.leads.دادههای_فرم")}</h4>
                  <div className="space-y-2 text-sm">
                    {openDetail.form_submission_data.map((f, i) => (
                      <div key={i} className="flex gap-2">
                        <span className="text-muted-foreground shrink-0 min-w-24">{f.label}:</span>
                        <span className="whitespace-pre-wrap break-words">{f.value || "—"}</span>
                      </div>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
