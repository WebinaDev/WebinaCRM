import { DetailSkeleton } from "@/components/skeletons"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { useNavigate, useSearchParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
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
  getTickets,
  getTicket,
  newTicket,
  ticketReply,
  convertTicketToTask,
  type Ticket,
  type TicketDetail,
} from "@/api/tickets"
import { getLeadAssignees } from "@/api/leads"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import { Plus, Loader2, List, ArrowRight, CheckSquare } from "lucide-react"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { RichTextEditor } from "@/features/shared/pm/RichTextEditor"
import { CannedResponsePicker } from "../components/CannedResponsePicker"

export function TicketsPage() {
  const { t, isRtl } = useLocale()
      const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const viewId = searchParams.get("ticket_id") ? parseInt(searchParams.get("ticket_id")!, 10) : null
  const initialTab = searchParams.get("action") === "new" ? "new" : viewId ? "single" : "list"

  const [tab, setTab] = useState<"list" | "new" | "single">(initialTab)
  const [tickets, setTickets] = useState<Ticket[]>([])
  const [statuses, setStatuses] = useState<{ slug: string; name: string }[]>([])
  const [priorities, setPriorities] = useState<{ slug: string; name: string; term_id: number }[]>([])
  const [departments, setDepartments] = useState<{ id: number; name: string }[]>([])
  const [totalPages, setTotalPages] = useState(1)
  const [currentPage, setCurrentPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [priorityFilter, setPriorityFilter] = useState("")
  const [departmentFilter, setDepartmentFilter] = useState("")
  const [singleTicket, setSingleTicket] = useState<TicketDetail | null>(null)
  const [singleStatuses, setSingleStatuses] = useState<{ slug: string; name: string }[]>([])
  const [singleDepartments, setSingleDepartments] = useState<{ id: number; name: string }[]>([])
  const [canManage, setCanManage] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [newForm, setNewForm] = useState({
    ticket_title: "",
    ticket_content: "",
    department: 0,
    ticket_priority: 0,
  })
  const [replyContent, setReplyContent] = useState("")
  const [replyStatus, setReplyStatus] = useState("")
  const [replyDepartment, setReplyDepartment] = useState(0)
  const [replyAssignee, setReplyAssignee] = useState(0)
  const [replyPriority, setReplyPriority] = useState(0)
  const [singlePriorities, setSinglePriorities] = useState<{ term_id: number; slug: string; name: string }[]>([])
  const [assignees, setAssignees] = useState<{ id: number; display_name: string }[]>([])
  const [convertConfirmOpen, setConvertConfirmOpen] = useState(false)
  const [success] = useState<string | null>(null)

  const loadList = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getTickets({
        s: search || undefined,
        status_filter: statusFilter || undefined,
        priority_filter: priorityFilter || undefined,
        department_filter: departmentFilter ? parseInt(departmentFilter, 10) : undefined,
        paged: currentPage,
      })
      if (res.success && res.data) {
        setTickets(res.data.tickets ?? [])
        setStatuses(res.data.statuses ?? [])
        setPriorities(res.data.priorities ?? [])
        setDepartments(res.data.departments ?? [])
        setTotalPages(res.data.total_pages ?? 1)
      } else {
        setError(res.message ?? t("pages.tickets.خطا_در_بارگذاری_تیکتها"))
      }
    } catch {
      setError(t("pages.tickets.خطا_در_بارگذاری_تیکتها"))
    } finally {
      setLoading(false)
    }
  }, [search, statusFilter, priorityFilter, departmentFilter, currentPage])

  const loadTicket = useCallback(async (id: number) => {
    setLoading(true)
    setError(null)
    try {
      const res = await getTicket(id)
      if (res.success && res.data) {
        const ticket = res.data.ticket
        setSingleTicket(ticket)
        setSingleStatuses(res.data.statuses ?? [])
        setSingleDepartments(res.data.departments ?? [])
        setSinglePriorities(res.data.priorities ?? [])
        setCanManage(res.data.can_manage ?? false)
        if (ticket) {
          setReplyStatus(ticket.status_slug ?? "")
          setReplyDepartment(ticket.department_id ?? 0)
          setReplyAssignee(ticket.assigned_to_id ?? 0)
          setReplyPriority(ticket.priority_id ?? 0)
        } else {
          setReplyStatus("")
          setReplyDepartment(0)
          setReplyAssignee(0)
          setReplyPriority(0)
        }
      } else {
        setError(res.message ?? t("pages.tickets.خطا_در_بارگذاری_تیکت"))
      }
    } catch {
      setError(t("pages.tickets.خطا_در_بارگذاری_تیکت"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    const action = searchParams.get("action")
    const tid = searchParams.get("ticket_id")
    if (action === "new") {
      setTab("new")
      getTickets({}).then((res) => {
        if (res.success && res.data) {
          setStatuses(res.data.statuses ?? [])
          setPriorities(res.data.priorities ?? [])
          setDepartments(res.data.departments ?? [])
        }
      })
    } else if (tid) {
      const id = parseInt(tid, 10)
      if (!Number.isNaN(id)) {
        setTab("single")
        setSingleTicket(null)
      }
    } else {
      setTab("list")
      setSingleTicket(null)
    }
  }, [searchParams])

  useEffect(() => {
    setCurrentPage(1)
  }, [search, statusFilter, priorityFilter, departmentFilter])

  useEffect(() => {
    if (tab !== "list") return
    const timer = setTimeout(() => {
      void loadList()
    }, search ? 300 : 0)
    return () => clearTimeout(timer)
  }, [tab, loadList, search, statusFilter, priorityFilter, departmentFilter, currentPage])

  useEffect(() => {
    if (viewId && tab === "single") {
      queueMicrotask(() => {
        void loadTicket(viewId)
      })
    }
  }, [tab, viewId, loadTicket])

  useEffect(() => {
    if (!canManage) return
    void (async () => {
      const res = await getLeadAssignees()
      if (res.success && res.data?.users) setAssignees(res.data.users)
    })()
  }, [canManage])

  const openNew = () => {
    navigate("/tickets?action=new")
    setTab("new")
    setNewForm({ ticket_title: "", ticket_content: "", department: 0, ticket_priority: 0 })
  }

  const openTicket = (t: Ticket) => {
    navigate(`/tickets?ticket_id=${t.id}`)
    setTab("single")
    loadTicket(t.id)
  }

  const backToList = () => {
    navigate("/tickets")
    setTab("list")
    setSingleTicket(null)
    loadList()
  }

  const htmlToText = (html: string) => html.replace(/<[^>]+>/g, "").replace(/&nbsp;/g, " ").trim()

  const handleNewTicket = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newForm.ticket_title.trim() || !htmlToText(newForm.ticket_content) || !newForm.department || !newForm.ticket_priority) {
      setError(t("pages.tickets.عنوان،_دپارتمان،_اولویت_و_متن_تیکت_الزام"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await newTicket({
        ticket_title: newForm.ticket_title.trim(),
        ticket_content: newForm.ticket_content.trim(),
        department: newForm.department,
        ticket_priority: newForm.ticket_priority,
      })
      if (res.success) {
        backToList()
      } else {
        setError(res.message ?? t("pages.tickets.خطا_در_ثبت_تیکت"))
      }
    } catch {
      setError(t("pages.tickets.خطا_در_ثبت_تیکت"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleReply = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!singleTicket || !htmlToText(replyContent)) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await ticketReply(
        singleTicket.id,
        replyContent.trim(),
        canManage
          ? {
              ticket_status: replyStatus || undefined,
              department: replyDepartment || undefined,
              assigned_to: replyAssignee || undefined,
              ticket_priority: replyPriority || undefined,
            }
          : undefined
      )
      if (res.success) {
        setReplyContent("")
        loadTicket(singleTicket.id)
      } else {
        setError(res.message ?? t("pages.tickets.خطا_در_ثبت_پاسخ"))
      }
    } catch {
      setError(t("pages.tickets.خطا_در_ثبت_پاسخ"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleConvertToTask = async () => {
    if (!singleTicket || !canManage) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await convertTicketToTask(singleTicket.id)
      if (res.success) {
        setConvertConfirmOpen(false)
        backToList()
      } else {
        setError(res.message ?? t("pages.consultations.خطا_در_تبدیل"))
      }
    } catch {
      setError(t("pages.consultations.خطا_در_تبدیل"))
    } finally {
      setSubmitting(false)
    }
  }

  if (tab === "single") {
    if (loading && !singleTicket) {
      return (
        <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
          <DetailSkeleton compact showPageHeader={false} />
        </div>
      )
    }
    if (!loading && !singleTicket) {
      return (
        <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
          <Button variant="ghost" onClick={backToList} className="mb-4">
            <ArrowRight className={cn("h-4 w-4", isRtl ? "me-2" : "ms-2 rotate-180")} />
            {t("pages.tickets.بازگشت_به_لیست")}
          </Button>
          {error && (
            <div className="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-destructive">
              {error}
            </div>
          )}
          {!error && (
            <p className="text-muted-foreground">{t("pages.tickets.تیکت_یافت_نشد")}</p>
          )}
        </div>
      )
    }
  }

  if (tab === "single" && singleTicket) {
    return (
      <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
        <Button variant="ghost" onClick={backToList} className="mb-4">
          <ArrowRight className={cn("h-4 w-4", isRtl ? "me-2" : "ms-2 rotate-180")} />
          {t("pages.tickets.بازگشت_به_لیست")}
        </Button>
        {error && (
          <div className="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-destructive">
            {error}
          </div>
        )}
        <Card>
          <CardContent className="pt-6">
            <h2 className="text-xl font-semibold mb-4">{singleTicket.title}</h2>
            <div className="flex flex-wrap gap-2 mb-4 text-sm text-muted-foreground">
              <span>{t("pages.tickets.ارسال_شده_توسط")}: {singleTicket.author_name}</span>
              <span>{t("pages.tickets.در_تاریخ")}: {singleTicket.date}</span>
              <Badge variant="secondary">{singleTicket.status_name}</Badge>
              <Badge variant="outline">{singleTicket.priority_name}</Badge>
              <span>{t("pages.tickets.دپارتمان")}: {singleTicket.department_name}</span>
              <span>{t("pages.tickets.مسئول")}: {singleTicket.assigned_to_name}</span>
            </div>
            <div className="prose prose-sm max-w-none mb-6" dangerouslySetInnerHTML={{ __html: singleTicket.content }} />
            {canManage && (
              <Button variant="outline" size="sm" onClick={() => setConvertConfirmOpen(true)} disabled={submitting} className="mb-6">
                <CheckSquare className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
                {t("pages.tickets.تبدیل_به_وظیفه")}
              </Button>
            )}
            <div className="space-y-4">
              <h3 className="font-medium">{t("pages.tickets.پاسخها")}</h3>
              {singleTicket.replies.length === 0 ? (
                <p className="text-muted-foreground">{t("pages.tickets.هنوز_پاسخی_ثبت_نشده_است")}</p>
              ) : (
                <div className="space-y-4">
                  {singleTicket.replies.map((r) => (
                    <div key={r.id} className="border rounded-lg p-4">
                      <div className="text-sm text-muted-foreground mb-2">
                        {r.author} — {r.date}
                      </div>
                      <div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: r.content }} />
                    </div>
                  ))}
                </div>
              )}
              {!singleTicket.is_closed && (
                <form onSubmit={handleReply} className="space-y-4 pt-4 border-t">
                  <h4 className="font-medium">{t("pages.tickets.ارسال_پاسخ_جدید")}</h4>
                  {canManage && (
                    <div className="flex gap-4 flex-wrap">
                      <div className="space-y-2">
                        <Label>{t("common.status")}</Label>
                        <Select value={replyStatus} onValueChange={setReplyStatus}>
                          <SelectTrigger className="w-[140px]">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {(singleStatuses.length ? singleStatuses : [
                              { slug: "open", name: t("pages.tickets.status.open") },
                              { slug: "in-progress", name: t("pages.tickets.status.inProgress") },
                              { slug: "closed", name: t("pages.tickets.status.closed") },
                            ]).map((s) => (
                              <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label>{t("pages.staff.دپارتمان")}</Label>
                        <Select value={replyDepartment ? String(replyDepartment) : ""} onValueChange={(v) => setReplyDepartment(parseInt(v, 10) || 0)}>
                          <SelectTrigger className="w-[140px]">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {(singleDepartments.length ? singleDepartments : departments).map((d) => (
                              <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label>{t("pages.tickets.assignee")}</Label>
                        <Select value={replyAssignee ? String(replyAssignee) : "_none"} onValueChange={(v) => setReplyAssignee(v === "_none" ? 0 : parseInt(v, 10))}>
                          <SelectTrigger className="w-[160px]">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="_none">—</SelectItem>
                            {assignees.map((u) => (
                              <SelectItem key={u.id} value={String(u.id)}>{u.display_name}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="space-y-2">
                        <Label>{t("pages.tickets.اولویت")}</Label>
                        <Select value={replyPriority ? String(replyPriority) : ""} onValueChange={(v) => setReplyPriority(parseInt(v, 10) || 0)}>
                          <SelectTrigger className="w-[140px]">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {(singlePriorities.length ? singlePriorities : priorities).map((p) => (
                              <SelectItem key={p.term_id} value={String(p.term_id)}>{p.name}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                  )}
                  {canManage && (
                    <CannedResponsePicker onSelect={(html) => setReplyContent((prev) => prev + html)} />
                  )}
                  <div className="space-y-2">
                    <Label>{t("pages.tickets.متن_پاسخ")}</Label>
                    <RichTextEditor
                      value={replyContent}
                      onChange={setReplyContent}
                      dir={isRtl ? "rtl" : "ltr"}
                      placeholder={t("pages.tickets.متن_پاسخ")}
                    />
                  </div>
                  <Button type="submit" disabled={submitting || !htmlToText(replyContent)}>
                    {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
                    {t("pages.tickets.ارسال_پاسخ")}
                  </Button>
                </form>
              )}
            </div>
          </CardContent>
        </Card>

        <PmConfirmDialog
          open={convertConfirmOpen}
          onOpenChange={setConvertConfirmOpen}
          title={t("pages.tickets.تبدیل_به_وظیفه")}
          description={t("pages.tickets.confirm.convertToTask")}
          confirmLabel={t("common.yes")}
          cancelLabel={t("common.cancel")}
          loading={submitting}
          isRtl={isRtl}
          onConfirm={handleConvertToTask}
        />
      </div>
    )
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.reports.تیکتها")}
        isRtl={isRtl}
        actions={
          <div className="flex gap-2">
            <Button variant={tab === "list" ? "default" : "outline"} size="sm" onClick={() => { setTab("list"); navigate("/tickets") }}>
              <List className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.tickets.لیست_تیکتها")}
            </Button>
            <Button variant={tab === "new" ? "default" : "outline"} size="sm" onClick={openNew}>
              <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.tickets.ارسال_تیکت_جدید")}
            </Button>
          </div>
        }
      />

      <PmAlerts error={error} success={success} />

      {tab === "new" ? (
        <Card>
          <CardContent className="pt-6">
            <h3 className="text-lg font-semibold mb-4">{t("pages.tickets.ارسال_تیکت_جدید")}</h3>
            <form onSubmit={handleNewTicket} className="space-y-4">
              <div className="space-y-2">
                <Label>{t("pages.appointments.موضوع_6")}</Label>
                <Input
                  value={newForm.ticket_title}
                  onChange={(e) => setNewForm((f) => ({ ...f, ticket_title: e.target.value }))}
                  placeholder={t("pages.tickets.موضوع_تیکت_خود_را_وارد_کنید")}
                  required
                />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label>{t("pages.tickets.دپارتمان")}</Label>
                  <Select
                    value={newForm.department ? String(newForm.department) : ""}
                    onValueChange={(v) => setNewForm((f) => ({ ...f, department: parseInt(v, 10) || 0 }))}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t("pages.staff.انتخاب_دپارتمان")} />
                    </SelectTrigger>
                    <SelectContent>
                      {departments.map((d) => (
                        <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.tickets.اولویت")}</Label>
                  <Select
                    value={newForm.ticket_priority ? String(newForm.ticket_priority) : ""}
                    onValueChange={(v) => setNewForm((f) => ({ ...f, ticket_priority: parseInt(v, 10) || 0 }))}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t("pages.tickets.انتخاب_اولویت")} />
                    </SelectTrigger>
                    <SelectContent>
                      {priorities.map((p) => (
                        <SelectItem key={p.term_id} value={String(p.term_id)}>{p.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.tickets.پیام_شما")}</Label>
                <RichTextEditor
                  value={newForm.ticket_content}
                  onChange={(html) => setNewForm((f) => ({ ...f, ticket_content: html }))}
                  dir={isRtl ? "rtl" : "ltr"}
                  placeholder={t("pages.tickets.پیام_شما")}
                />
              </div>
              <Button type="submit" disabled={submitting}>
                {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
                {t("pages.tickets.ارسال_تیکت")}
              </Button>
            </form>
          </CardContent>
        </Card>
      ) : (
        <>
          <Card>
            <CardContent className="pt-6">
              <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void loadList()} isRtl={isRtl}>
                <Input
                  placeholder={t("pages.accounting.warehouseAudit.جستجو")}
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="max-w-[200px]"
                />
                <Select
                  value={statusFilter === "" ? SELECT_ALL_VALUE : statusFilter}
                  onValueChange={(v) => setStatusFilter(v === SELECT_ALL_VALUE ? "" : v)}
                >
                  <SelectTrigger className="w-[130px]">
                    <SelectValue placeholder={t("common.status")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                    {statuses.map((s) => (
                      <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select
                  value={priorityFilter === "" ? SELECT_ALL_VALUE : priorityFilter}
                  onValueChange={(v) => setPriorityFilter(v === SELECT_ALL_VALUE ? "" : v)}
                >
                  <SelectTrigger className="w-[120px]">
                    <SelectValue placeholder={t("pages.tasks.اولویت")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                    {priorities.map((p) => (
                      <SelectItem key={p.term_id} value={p.slug}>{p.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select
                  value={departmentFilter === "" ? SELECT_ALL_VALUE : departmentFilter}
                  onValueChange={(v) => setDepartmentFilter(v === SELECT_ALL_VALUE ? "" : v)}
                >
                  <SelectTrigger className="w-[140px]">
                    <SelectValue placeholder={t("pages.staff.دپارتمان")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                    {departments.map((d) => (
                      <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </PmFilterBar>
              {loading ? (

                <TableListSkeleton rows={8} columns={6} withAvatarColumn />

              ) : tickets.length === 0 ? (
                <div className="py-12 text-center text-muted-foreground">
                  {t("common.noResults")}
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.appointments.موضوع")}</TableHead>
                      <TableHead>{t("pages.tickets.آخرین_بروزرسانی")}</TableHead>
                      <TableHead>{t("pages.tasks.اولویت")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                      <TableHead></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {tickets.map((ticket) => (
                      <TableRow key={ticket.id}>
                        <TableCell>
                          <button
                            type="button"
                            className="text-primary hover:underline text-start font-medium"
                            onClick={() => openTicket(ticket)}
                          >
                            {ticket.title}
                          </button>
                        </TableCell>
                        <TableCell>{ticket.modified}</TableCell>
                        <TableCell><Badge variant="outline">{ticket.priority_name}</Badge></TableCell>
                        <TableCell><Badge variant="secondary">{ticket.status_name}</Badge></TableCell>
                        <TableCell>
                          <Button variant="outline" size="sm" onClick={() => openTicket(ticket)}>
                            {t("common.view")}
                          </Button>
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
      )}
    </div>
  )
}
