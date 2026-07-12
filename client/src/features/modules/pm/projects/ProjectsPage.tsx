import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useMemo, useState } from "react"
import { Link, useNavigate, useParams, useSearchParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { PmViewToggle } from "@/features/shared/pm/PmViewToggle"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { ProjectDetailPanel } from "./ProjectDetail"
import { RichTextEditor } from "@/features/shared/pm/RichTextEditor"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { SELECT_ALL_VALUE } from "@/lib/constants"
import {
  getProjects,
  getProject,
  manageProject,
  deleteProject,
  type Project,
} from "@/api/projects"
import { cn } from "@/lib/utils"
import { Plus, Loader2, Pencil, Trash2, List, LayoutGrid, FolderOpen, MoreVertical, Eye, ChevronRight, ChevronLeft, FileText } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
export function ProjectsPage() {
  const { t, isRtl } = useLocale()
      const navigate = useNavigate()
  const params = useParams()
  const [searchParams] = useSearchParams()
  const [deleteTarget, setDeleteTarget] = useState<(Project & { delete_nonce?: string }) | null>(null)
  const actionParam = searchParams.get("action")
  const projectIdParam = searchParams.get("project_id")
  const editId =
    actionParam === "edit" && projectIdParam ? parseInt(projectIdParam, 10) : null
  const initialTab =
    actionParam === "new" ? "new" : actionParam === "edit" && editId ? "form" : "list"

  const [tab, setTab] = useState<"list" | "new" | "form">(initialTab)
  const [projects, setProjects] = useState<Project[]>([])
  const [contracts, setContracts] = useState<{ id: number; title: string; customer_name: string }[]>([])
  const [statuses, setStatuses] = useState<{ id: number; name: string }[]>([])
  const [managers, setManagers] = useState<{ id: number; display_name: string }[]>([])
  const [totalPages, setTotalPages] = useState(1)
  const [currentPage, setCurrentPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search, 400)
  const [listLayout, setListLayout] = useState<"cards" | "table">("cards")
  const [contractFilter, setContractFilter] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [submitting, setSubmitting] = useState(false)
  const [wizardStep, setWizardStep] = useState(1)
  const [logoFile, setLogoFile] = useState<File | null>(null)
  const [form, setForm] = useState({
    project_title: "",
    contract_id: 0,
    project_manager: 0,
    project_status: 0,
    project_content: "",
    start_date: "",
    end_date: "",
    priority: "medium",
    team_member_ids: [] as number[],
    project_tags: "",
  })

  const priorityOptions = useMemo(
    () => [
      { value: "high", label: t("pages.projects.اولویت_زیاد") },
      { value: "medium", label: t("pages.projects.اولویت_متوسط") },
      { value: "low", label: t("pages.projects.اولویت_کم") },
    ],
    [t],
  )

  const toggleTeamMember = (id: number) => {
    setForm((f) => ({
      ...f,
      team_member_ids: f.team_member_ids.includes(id)
        ? f.team_member_ids.filter((x) => x !== id)
        : [...f.team_member_ids, id],
    }))
  }

  const loadList = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getProjects({
        s: debouncedSearch || undefined,
        contract_id: contractFilter ? parseInt(contractFilter, 10) : undefined,
        status_filter: statusFilter ? parseInt(statusFilter, 10) : undefined,
        paged: currentPage,
      })
      if (res.success && res.data) {
        setProjects(res.data.projects ?? [])
        setContracts(res.data.contracts ?? [])
        setStatuses(res.data.statuses ?? [])
        setManagers(res.data.managers ?? [])
        setTotalPages(res.data.total_pages ?? 1)
      } else {
        setError(res.message ?? t("pages.projects.خطا_در_بارگذاری_پروژهها"))
      }
    } catch {
      setError(t("pages.projects.خطا_در_بارگذاری_پروژهها"))
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, contractFilter, statusFilter, currentPage, t])

  const loadProject = useCallback(async (id: number) => {
    setLoading(true)
    setError(null)
    try {
      const res = await getProject(id)
      if (res.success && res.data) {
        const p = res.data.project
        setManagers(res.data.managers ?? [])
        setContracts(res.data.contracts ?? [])
        setStatuses(res.data.statuses ?? [])
        if (p) {
          setForm({
            project_title: p.title ?? "",
            contract_id: p.contract_id ?? 0,
            project_manager: p.project_manager ?? 0,
            project_status: p.project_status ?? 0,
            project_content: p.content ?? "",
            start_date: p.start_date ? p.start_date.slice(0, 10) : "",
            end_date: p.end_date ? p.end_date.slice(0, 10) : "",
            priority: p.priority ?? "medium",
            team_member_ids: (p.assigned_members ?? []).map((m) => m.id),
            project_tags: (p.project_tags ?? []).join(", "),
          })
          setLogoFile(null)
        } else {
          setError(res.message ?? t("pages.projects.خطا_در_بارگذاری_پروژه_18"))
        }
      } else {
        setError(res.message ?? t("pages.projects.خطا_در_بارگذاری_پروژه_18"))
      }
    } catch {
      setError(t("pages.projects.خطا_در_بارگذاری_پروژه_18"))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    const action = searchParams.get("action")
    const pid = searchParams.get("project_id")
    if (action === "view") return
    if (action === "new") setTab("new")
    else if (action === "edit" && pid) setTab("form")
    else if (!action) setTab("list")
  }, [searchParams])

  useEffect(() => {
    setCurrentPage(1)
  }, [debouncedSearch, contractFilter, statusFilter])

  useEffect(() => {
    if (tab === "list") {
      queueMicrotask(() => {
        void loadList()
      })
    }
  }, [tab, loadList])

  useEffect(() => {
    if (editId && tab === "form") {
      queueMicrotask(() => {
        void loadProject(editId)
      })
    }
  }, [tab, editId, loadProject])

  useEffect(() => {
    if (tab === "new") {
      getProjects({}).then((res) => {
        if (res.success && res.data) {
          setContracts(res.data.contracts ?? [])
          setStatuses(res.data.statuses ?? [])
          setManagers(res.data.managers ?? [])
        }
      })
    }
  }, [tab])

  const openNew = () => {
    navigate("/projects?action=new")
    setTab("new")
    setWizardStep(1)
    setForm({
      project_title: "",
      contract_id: 0,
      project_manager: 0,
      project_status: 0,
      project_content: "",
      start_date: "",
      end_date: "",
      priority: "medium",
      team_member_ids: [],
      project_tags: "",
    })
    setLogoFile(null)
  }

  const openEdit = (p: Project) => {
    navigate(`/projects?action=edit&project_id=${p.id}`)
    setTab("form")
    loadProject(p.id)
  }

  const backToList = () => {
    navigate("/projects")
    setTab("list")
    loadList()
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.project_title.trim() || !form.contract_id || !form.project_status) {
      setError(t("pages.projects.نام_پروژه،_قرارداد_و_وضعیت_الزامی_هستند"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await manageProject({
        project_id: editId ?? undefined,
        project_title: form.project_title.trim(),
        contract_id: form.contract_id,
        project_content: form.project_content || undefined,
        project_status: form.project_status,
        project_manager: form.project_manager || undefined,
        project_start_date: form.start_date || undefined,
        project_end_date: form.end_date || undefined,
        project_priority: form.priority,
        assigned_team_members: form.team_member_ids,
        project_tags: form.project_tags.trim(),
        project_logo: logoFile,
      })
      if (res.success) {
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

  useEffect(() => {
    const qAction = searchParams.get("action")
    const qId = searchParams.get("project_id")
    if (qAction === "view" && qId && !Number.isNaN(parseInt(qId, 10))) {
      navigate(`/projects/${qId}`, { replace: true })
    }
  }, [searchParams, navigate])

  const pathViewId = params["*"]?.match(/^(\d+)/)?.[1]
  const viewIdFromPath = pathViewId ? parseInt(pathViewId, 10) : null

  const handleDelete = async (p: Project & { delete_nonce?: string }) => {
    const nonce = p.delete_nonce
    if (!nonce) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await deleteProject(p.id, nonce)
      if (res.success) loadList()
      else setError(res.message ?? t("common.errors.deleteFailed"))
    } catch {
      setError(t("common.errors.deleteFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const showForm = tab === "new" || tab === "form"
  const viewId = viewIdFromPath

  if (viewId) {
    return (
      <>
        <ProjectDetailPanel
          projectId={viewId}
          isRtl={isRtl}
          onBack={() => { navigate("/projects"); setTab("list") }}
          onEdit={() => { navigate(`/projects?action=edit&project_id=${viewId}`); setTab("form"); loadProject(viewId) }}
        />
      </>
    )
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.projects.پروژهها")}
        isRtl={isRtl}
        actions={
          <>
            <Button variant={tab === "list" ? "default" : "outline"} onClick={() => { setTab("list"); navigate("/projects") }}>
              <List className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
              {t("common.listView")}
            </Button>
            <Button variant={showForm ? "default" : "outline"} onClick={openNew}>
              <Plus className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
              {t("pages.projects.ایجاد_پروژه")}
            </Button>
          </>
        }
      />

      <PmAlerts error={error} />
      <PmConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title={t("common.delete")}
        description={t("pages.projects.confirm.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        onConfirm={async () => {
          if (deleteTarget) await handleDelete(deleteTarget)
          setDeleteTarget(null)
        }}
      />

      {showForm ? (
        <Card>
          <CardContent className="pt-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold">{editId ? t("pages.projects.ویرایش_پروژه") : t("pages.projects.ایجاد_پروژه_جدید")}</h3>
              <Button variant="outline" size="sm" onClick={backToList}>{t("common.back")}</Button>
            </div>
            {!editId && (
              <div className="flex items-center gap-2 mb-6 pb-4 border-b">
                <div className={cn("w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium", wizardStep === 1 ? "bg-primary text-primary-foreground" : "bg-primary/20 text-primary")}>1</div>
                <ChevronRight className="h-4 w-4 text-muted-foreground" />
                <div className={cn("w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium", wizardStep === 2 ? "bg-primary text-primary-foreground" : wizardStep > 1 ? "bg-primary/20 text-primary" : "bg-muted text-muted-foreground")}>2</div>
                <span className="text-sm text-muted-foreground mr-2">
                  {wizardStep === 1 ? t("pages.projects.انتخاب_قرارداد_مرحله") : t("pages.projects.جزئیات_پروژه_مرحله")}
                </span>
              </div>
            )}
            <form onSubmit={handleSubmit} className="space-y-4">
              {editId ? (
                <>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t("pages.projects.نام_پروژه")}</Label>
                    <Input value={form.project_title} onChange={(e) => setForm((f) => ({ ...f, project_title: e.target.value }))} required />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.projects.قرارداد_مشتری")}</Label>
                    <Select value={form.contract_id ? String(form.contract_id) : ""} onValueChange={(v) => setForm((f) => ({ ...f, contract_id: parseInt(v, 10) || 0 }))} disabled>
                      <SelectTrigger><SelectValue placeholder={t("pages.projects.انتخاب_قرارداد")} /></SelectTrigger>
                      <SelectContent>
                        {contracts.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.title} ({c.customer_name})</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                <div className="space-y-2">
                  <Label>{t("pages.projects.مدیر_پروژه_16")}</Label>
                  <Select
                    value={form.project_manager ? String(form.project_manager) : ""}
                    onValueChange={(v) => setForm((f) => ({ ...f, project_manager: parseInt(v, 10) || 0 }))}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t("pages.projects.انتخاب_مدیر")} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="0">—</SelectItem>
                      {managers.map((m) => (
                        <SelectItem key={m.id} value={String(m.id)}>{m.display_name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.projects.وضعیت")}</Label>
                  <Select
                    value={form.project_status ? String(form.project_status) : ""}
                    onValueChange={(v) => setForm((f) => ({ ...f, project_status: parseInt(v, 10) || 0 }))}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t("pages.projects.انتخاب_وضعیت")} />
                    </SelectTrigger>
                    <SelectContent>
                      {statuses.map((s) => (
                        <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.licenses.تاریخ_شروع")}</Label>
                  <DatePicker value={form.start_date} onChange={(v) => setForm((f) => ({ ...f, start_date: v }))} />
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.projects.تاریخ_پایان_17")}</Label>
                  <DatePicker value={form.end_date} onChange={(v) => setForm((f) => ({ ...f, end_date: v }))} />
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.projects.اولویت")}</Label>
                  <Select value={form.priority} onValueChange={(v) => setForm((f) => ({ ...f, priority: v }))}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {priorityOptions.map((o) => (
                        <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.projects.تیم_پروژه")}</Label>
                <div className="flex flex-wrap gap-3 rounded-md border p-3 max-h-40 overflow-y-auto">
                  {managers.map((m) => (
                    <label key={m.id} className="flex items-center gap-2 text-sm cursor-pointer">
                      <Checkbox
                        checked={form.team_member_ids.includes(m.id)}
                        onCheckedChange={() => toggleTeamMember(m.id)}
                      />
                      {m.display_name}
                    </label>
                  ))}
                </div>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.projects.برچسبها")}</Label>
                <Input
                  value={form.project_tags}
                  onChange={(e) => setForm((f) => ({ ...f, project_tags: e.target.value }))}
                  dir="ltr"
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.projects.لوگو")}</Label>
                <Input type="file" accept="image/*" onChange={(e) => setLogoFile(e.target.files?.[0] ?? null)} />
              </div>
              <div className="space-y-2">
                <Label>{t("common.description")}</Label>
                <RichTextEditor
                  value={form.project_content}
                  onChange={(html) => setForm((f) => ({ ...f, project_content: html }))}
                  dir={isRtl ? "rtl" : "ltr"}
                />
              </div>
              <Button type="submit" disabled={submitting}>
                {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
                {t("common.save")}
              </Button>
                </>
              ) : (
                <>
              {wizardStep === 1 && (
                <div className="space-y-4">
                  <div className="flex items-center gap-2 text-muted-foreground mb-4">
                    <FileText className="h-5 w-5" />
                    <span>{t("pages.projects.انتخاب_قرارداد_برای_متصل_کردن_پروژه")}</span>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.projects.قرارداد_مشتری")}</Label>
                    <Select value={form.contract_id ? String(form.contract_id) : ""} onValueChange={(v) => setForm((f) => ({ ...f, contract_id: parseInt(v, 10) || 0 }))}>
                      <SelectTrigger>
                        <SelectValue placeholder={t("pages.projects.انتخاب_قرارداد")} />
                      </SelectTrigger>
                      <SelectContent>
                        {contracts.map((c) => (
                          <SelectItem key={c.id} value={String(c.id)}>
                            {c.title} ({c.customer_name})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <Button type="button" onClick={() => setWizardStep(2)} disabled={!form.contract_id}>
                    {t("common.nextPage")}
                    <ChevronLeft className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                  </Button>
                </div>
              )}
              {wizardStep === 2 && (
                <>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t("pages.projects.نام_پروژه")}</Label>
                    <Input value={form.project_title} onChange={(e) => setForm((f) => ({ ...f, project_title: e.target.value }))} required />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.projects.قرارداد_انتخاب_شده")}</Label>
                    <Select value={String(form.contract_id)} onValueChange={() => {}} disabled>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {contracts.filter((c) => c.id === form.contract_id).map((c) => (
                          <SelectItem key={c.id} value={String(c.id)}>{c.title} ({c.customer_name})</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.projects.مدیر_پروژه_16")}</Label>
                    <Select value={form.project_manager ? String(form.project_manager) : ""} onValueChange={(v) => setForm((f) => ({ ...f, project_manager: parseInt(v, 10) || 0 }))}>
                      <SelectTrigger><SelectValue placeholder={t("pages.projects.انتخاب_مدیر")} /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="0">—</SelectItem>
                        {managers.map((m) => <SelectItem key={m.id} value={String(m.id)}>{m.display_name}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.projects.وضعیت")}</Label>
                    <Select value={form.project_status ? String(form.project_status) : ""} onValueChange={(v) => setForm((f) => ({ ...f, project_status: parseInt(v, 10) || 0 }))}>
                      <SelectTrigger><SelectValue placeholder={t("pages.projects.انتخاب_وضعیت")} /></SelectTrigger>
                      <SelectContent>
                        {statuses.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.licenses.تاریخ_شروع")}</Label>
                    <DatePicker value={form.start_date} onChange={(v) => setForm((f) => ({ ...f, start_date: v }))} />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.projects.تاریخ_پایان_17")}</Label>
                    <DatePicker value={form.end_date} onChange={(v) => setForm((f) => ({ ...f, end_date: v }))} />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.projects.اولویت")}</Label>
                    <Select value={form.priority} onValueChange={(v) => setForm((f) => ({ ...f, priority: v }))}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {priorityOptions.map((o) => (
                          <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.projects.تیم_پروژه")}</Label>
                  <div className="flex flex-wrap gap-3 rounded-md border p-3 max-h-40 overflow-y-auto">
                    {managers.map((m) => (
                      <label key={m.id} className="flex items-center gap-2 text-sm cursor-pointer">
                        <Checkbox
                          checked={form.team_member_ids.includes(m.id)}
                          onCheckedChange={() => toggleTeamMember(m.id)}
                        />
                        {m.display_name}
                      </label>
                    ))}
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.projects.برچسبها")}</Label>
                  <Input value={form.project_tags} onChange={(e) => setForm((f) => ({ ...f, project_tags: e.target.value }))} dir="ltr" />
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.projects.لوگو")}</Label>
                  <Input type="file" accept="image/*" onChange={(e) => setLogoFile(e.target.files?.[0] ?? null)} />
                </div>
                <div className="space-y-2">
                  <Label>{t("common.description")}</Label>
                  <RichTextEditor
                    value={form.project_content}
                    onChange={(html) => setForm((f) => ({ ...f, project_content: html }))}
                    dir={isRtl ? "rtl" : "ltr"}
                  />
                </div>
                <div className="flex gap-4">
                  <Button type="button" variant="outline" onClick={() => setWizardStep(1)}>
                    <ChevronRight className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                    {t("common.prevPage")}
                  </Button>
                  <Button type="submit" disabled={submitting}>
                    {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
                    {t("pages.projects.ایجاد_پروژه")}
                  </Button>
                </div>
                </>
              )}
                </>
              )}
            </form>
          </CardContent>
        </Card>
      ) : (
        <>
          <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} isRtl={isRtl} className="mb-4">
                <Input placeholder={t("pages.accounting.warehouseAudit.جستجو")} value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-[200px]" />
                <Select
                  value={contractFilter === "" ? SELECT_ALL_VALUE : contractFilter}
                  onValueChange={(v) => setContractFilter(v === SELECT_ALL_VALUE ? "" : v)}
                >
                  <SelectTrigger className="w-[180px]">
                    <SelectValue placeholder={t("pages.projects.قرارداد")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                    {contracts.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>{c.title}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select
                  value={statusFilter === "" ? SELECT_ALL_VALUE : statusFilter}
                  onValueChange={(v) => setStatusFilter(v === SELECT_ALL_VALUE ? "" : v)}
                >
                  <SelectTrigger className="w-[120px]">
                    <SelectValue placeholder={t("common.status")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                    {statuses.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <PmViewToggle
                  value={listLayout}
                  onChange={setListLayout}
                  isRtl={isRtl}
                  options={[
                    { id: "cards", label: t("pages.projects.نمای_کارت"), icon: <LayoutGrid className="h-4 w-4" /> },
                    { id: "table", label: t("pages.projects.نمای_جدول"), icon: <List className="h-4 w-4" /> },
                  ]}
                />
          </PmFilterBar>
          {loading ? (
            <Card>
              <CardContent className="p-0">

                <TableListSkeleton rows={8} columns={5} />

              </CardContent>
            </Card>
          ) : projects.length === 0 ? (
            <PmEmptyState
              iconNode={<FolderOpen className="h-16 w-16 mb-3 opacity-40" />}
              message={t("pages.projects.هیچ_پروژهای_یافت_نشد")}
            />
          ) : listLayout === "table" ? (
            <Card>
              <CardContent className="pt-6">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.projects.نام_پروژه")}</TableHead>
                      <TableHead>{t("pages.projects.مشتری")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                      <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {projects.map((p) => (
                      <TableRow key={p.id}>
                        <TableCell>
                          <Link to={`/projects/${p.id}`} className="font-medium text-primary hover:underline">
                            {p.title}
                          </Link>
                        </TableCell>
                        <TableCell>{p.customer_name}</TableCell>
                        <TableCell><Badge variant="secondary">{p.status_name}</Badge></TableCell>
                        <TableCell>
                          <div className="flex gap-1">
                            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(p)}>
                              <Pencil className="h-4 w-4" />
                            </Button>
                            {(p as Project & { delete_nonce?: string }).delete_nonce && (
                              <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive" onClick={() => setDeleteTarget(p as Project & { delete_nonce?: string })}>
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {projects.map((p) => (
                <Card key={p.id} className="overflow-hidden">
                  <CardContent className="pt-6">
                    <div className="flex flex-col items-center text-center mb-4">
                      <div className="h-16 w-16 rounded-lg bg-muted flex items-center justify-center mb-2 overflow-hidden">
                        {(p as Project & { logo_url?: string }).logo_url ? (
                          <img src={(p as Project & { logo_url?: string }).logo_url} alt="" className="h-full w-full object-cover" />
                        ) : (
                          <FolderOpen className="h-8 w-8 text-muted-foreground" />
                        )}
                      </div>
                      <Link
                        to={`/projects/${p.id}`}
                        className="font-medium text-primary hover:underline line-clamp-2"
                      >
                        {p.title}
                      </Link>
                      <p className="text-sm text-muted-foreground mt-1">{p.customer_name}</p>
                    </div>
                    <div className="flex items-center justify-between">
                      <Badge variant="secondary">{p.status_name}</Badge>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="h-8 w-8">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem asChild>
                            <Link to={`/projects/${p.id}`}>
                              <Eye className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                              {t("pages.projects.مشاهده")}
                            </Link>
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => openEdit(p)}>
                            <Pencil className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                            {t("common.edit")}
                          </DropdownMenuItem>
                          {(p as Project & { delete_nonce?: string }).delete_nonce && (
                            <DropdownMenuItem
                              className="text-destructive"
                              onClick={() => setDeleteTarget(p as Project & { delete_nonce?: string })}
                              disabled={submitting}
                            >
                              <Trash2 className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                              {t("common.delete")}
                            </DropdownMenuItem>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
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
    </div>
  )
}
