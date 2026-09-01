import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
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
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog"
import { SELECT_ALL_VALUE } from "@/lib/constants"
import {
  getAppointments,
  getAppointment,
  getAppointmentsCalendar,
  getAppointmentCustomers,
  manageAppointment,
  deleteAppointment,
  rescheduleAppointment,
  type AppointmentItem,
  type CalendarEvent,
} from "@/api/appointments"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { AppointmentsCalendarPanel } from "./AppointmentsCalendarPanel"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import { Loader2, Calendar as CalendarIcon, List, Plus, Pencil, Trash2 } from "lucide-react"

const STATUS_COLORS: Record<string, string> = {
  pending: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200",
  confirmed: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200",
  cancelled: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200",
  completed: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200",
}

export function AppointmentsPage() {
  const { t, isRtl, formatDateTime } = useLocale()
      const [view, setView] = useState<"list" | "calendar">("list")
  const [appointments, setAppointments] = useState<AppointmentItem[]>([])
  const [events, setEvents] = useState<CalendarEvent[]>([])
  const [statuses, setStatuses] = useState<{ slug: string; name: string }[]>([])
  const [customers, setCustomers] = useState<{ id: number; display_name: string }[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [draggingEventId, setDraggingEventId] = useState<number | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [form, setForm] = useState({
    customer_id: 0,
    title: "",
    date: "",
    time: "10:00",
    status_slug: "pending",
    notes: "",
  })
  const [statusFilter, setStatusFilter] = useState("")

  const loadList = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getAppointments({ per_page: 100 })
      if (res.success && res.data) {
        setAppointments(res.data.appointments ?? [])
        setStatuses(res.data.statuses ?? [])
      } else {
        setError(res.message ?? t("pages.appointments.خطا_در_بارگذاری_قرار_ملاقاتها"))
      }
    } catch {
      setError(t("pages.appointments.خطا_در_بارگذاری_قرار_ملاقاتها"))
    } finally {
      setLoading(false)
    }
  }, [])

  const loadCalendar = useCallback(async () => {
    const y = new Date().getFullYear()
    const startStr = `${y}-01-01`
    const endStr = `${y}-12-31`
    try {
      const res = await getAppointmentsCalendar(startStr, endStr)
      if (res.success && res.data?.events) {
        setEvents(res.data.events)
      }
    } catch {
      setEvents([])
    }
  }, [])

  const loadCustomers = useCallback(async () => {
    const res = await getAppointmentCustomers()
    if (res.success && res.data?.customers) {
      setCustomers(
        res.data.customers.map((c) => ({ id: c.id, display_name: c.name }))
      )
    }
  }, [])

  useEffect(() => {
    loadList()
  }, [loadList])

  useEffect(() => {
    if (view === "calendar") loadCalendar()
  }, [view, loadCalendar])

  useEffect(() => {
    if (formOpen) loadCustomers()
  }, [formOpen, loadCustomers])

  useEffect(() => {
    if (editId && formOpen) {
      getAppointment(editId).then((res) => {
        if (res.success && res.data?.appointment) {
          const a = res.data.appointment
          setForm({
            customer_id: a.customer_id,
            title: a.title,
            date: a.date || "",
            time: a.time || "10:00",
            status_slug: a.status_slug || "pending",
            notes: a.notes || "",
          })
        }
      })
    } else if (!formOpen) {
      setForm({
        customer_id: 0,
        title: "",
        date: "",
        time: "10:00",
        status_slug: "pending",
        notes: "",
      })
      setEditId(null)
    }
  }, [editId, formOpen])

  const openCreate = (prefillDate?: string) => {
    setEditId(null)
    setForm({
      customer_id: 0,
      title: "",
      date: prefillDate ?? "",
      time: "10:00",
      status_slug: "pending",
      notes: "",
    })
    setFormOpen(true)
  }

  const handleReschedule = useCallback(
    async (appointmentId: number, isoDate: string, timePart: string) => {
      const newDatetime = `${isoDate} ${timePart}:00`
      setSubmitting(true)
      setError(null)
      try {
        const res = await rescheduleAppointment(appointmentId, newDatetime)
        if (res.success) {
          loadCalendar()
          loadList()
        } else {
          setError(res.message ?? t("common.errors.saveFailed"))
        }
      } catch {
        setError(t("common.errors.saveFailed"))
      } finally {
        setSubmitting(false)
        setDraggingEventId(null)
      }
    },
    [loadCalendar, loadList, t],
  )

  const openEdit = (a: AppointmentItem) => {
    setEditId(a.id)
    setForm({
      customer_id: a.customer_id,
      title: a.title,
      date: a.datetime ? a.datetime.slice(0, 10) : "",
      time: a.datetime ? a.datetime.slice(11, 16) : "10:00",
      status_slug: a.status_slug,
      notes: a.notes || "",
    })
    setFormOpen(true)
  }

  const handleSubmit = useCallback(async () => {
    if (!form.customer_id || !form.title.trim() || !form.date) {
      setError(t("pages.appointments.مشتری،_موضوع_و_تاریخ_الزامی_هستند"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await manageAppointment({
        appointment_id: editId ?? undefined,
        customer_id: form.customer_id,
        title: form.title.trim(),
        date: form.date,
        time: form.time,
        status: form.status_slug,
        notes: form.notes,
      })
      if (res.success) {
        setFormOpen(false)
        loadList()
        if (view === "calendar") loadCalendar()
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }, [form, editId, loadList, loadCalendar, view])

  const handleDelete = useCallback(async () => {
    if (deleteId == null) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await deleteAppointment(deleteId)
      if (res.success) {
        setDeleteId(null)
        loadList()
        if (view === "calendar") loadCalendar()
      } else {
        setError(res.message ?? t("common.errors.deleteFailed"))
      }
    } catch {
      setError(t("common.errors.deleteFailed"))
    } finally {
      setSubmitting(false)
    }
  }, [deleteId, loadList, loadCalendar, view])

  const filteredAppointments = statusFilter
    ? appointments.filter((a) => a.status_slug === statusFilter)
    : appointments

  return (
    <div className="space-y-6" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.appointments.قرار_ملاقاتها")}
        isRtl={isRtl}
        actions={
          <div className="flex gap-2">
            <Button variant={view === "list" ? "default" : "outline"} size="sm" onClick={() => setView("list")} className="gap-2">
              <List className="h-4 w-4" />
              {t("common.listView")}
            </Button>
            <Button variant={view === "calendar" ? "default" : "outline"} size="sm" onClick={() => setView("calendar")} className="gap-2">
              <CalendarIcon className="h-4 w-4" />
              {t("common.calendar")}
            </Button>
            <Button size="sm" onClick={() => openCreate()} className="gap-2">
              <Plus className="h-4 w-4" />
              {t("pages.appointments.قرار_جدید")}
            </Button>
          </div>
        }
      />
      {view === "calendar" && (
        <p className="text-xs text-muted-foreground">{t("pages.appointments.drag_hint")}</p>
      )}

      <PmAlerts error={error} />

      {view === "list" && (
        <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} isRtl={isRtl}>
          <Select
            value={statusFilter === "" ? SELECT_ALL_VALUE : statusFilter}
            onValueChange={(v) => setStatusFilter(v === SELECT_ALL_VALUE ? "" : v)}
          >
            <SelectTrigger className="w-[160px]">
              <SelectValue placeholder={t("pages.appointments.status_filter")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
              {statuses.map((s) => (
                <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </PmFilterBar>
      )}

      {loading && view === "list" ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={5} />
          </CardContent>
        </Card>
      ) : view === "list" ? (
        <Card>
          <CardContent className="pt-6">
            {filteredAppointments.length === 0 ? (
              <div className="py-12 text-center text-muted-foreground">
                {t("pages.appointments.هیچ_قراری")}
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.appointments.موضوع")}</TableHead>
                    <TableHead>{t("pages.accounting.persons.مشتری")}</TableHead>
                    <TableHead>{t("pages.appointments.تاریخ_و_ساعت")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead className="w-[120px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredAppointments.map((a) => (
                    <TableRow key={a.id}>
                      <TableCell className="font-medium">{a.title}</TableCell>
                      <TableCell>{a.customer_name}</TableCell>
                      <TableCell>
                        {a.datetime ? formatDateTime(a.datetime) : "—"}
                      </TableCell>
                      <TableCell>
                        <Badge className={cn(STATUS_COLORS[a.status_slug] ?? "bg-muted")}>
                          {a.status_name}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => openEdit(a)}
                          >
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-destructive hover:text-destructive"
                            onClick={() => setDeleteId(a.id)}
                          >
                            <Trash2 className="h-4 w-4" />
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
      ) : (
        <AppointmentsCalendarPanel
          events={events}
          draggingEventId={draggingEventId}
          onDayClick={(iso) => openCreate(iso)}
          onEventClick={(id) => {
            const match = appointments.find((a) => a.id === id)
            if (match) openEdit(match)
          }}
          onEventDragStart={setDraggingEventId}
          onDayDrop={(iso) => {
            const id = draggingEventId
            if (!id) return
            const ev = events.find((x) => x.id === id)
            const time = ev?.start?.slice(11, 16) ?? "10:00"
            void handleReschedule(id, iso, time)
          }}
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="sm:max-w-md" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{editId ? t("pages.appointments.ویرایش_قرار") : t("pages.appointments.قرار_جدید_عنوان")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.appointments.مشتری")}</label>
              <Select
                value={form.customer_id ? String(form.customer_id) : ""}
                onValueChange={(v) => setForm((f) => ({ ...f, customer_id: parseInt(v, 10) || 0 }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder={t("pages.appointments.انتخاب_مشتری")} />
                </SelectTrigger>
                <SelectContent>
                  {customers.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>{c.display_name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.appointments.موضوع_6")}</label>
              <Input
                value={form.title}
                onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                placeholder={t("pages.appointments.موضوع_قرار")}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.accounting.invoices.تاریخ_5")}</label>
                <DatePicker
                  value={form.date}
                  onChange={(v) => setForm((f) => ({ ...f, date: v }))}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.appointments.ساعت")}</label>
                <Input
                  type="time"
                  value={form.time}
                  onChange={(e) => setForm((f) => ({ ...f, time: e.target.value }))}
                />
              </div>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("common.status")}</label>
              <Select
                value={form.status_slug}
                onValueChange={(v) => setForm((f) => ({ ...f, status_slug: v }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {statuses.map((s) => (
                    <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.appointments.یادداشت")}</label>
              <textarea
                className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                value={form.notes}
                onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>{t("common.cancel")}</Button>
            <Button onClick={handleSubmit} disabled={submitting}>
              {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
              {editId ? t("common.save") : t("common.create")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId !== null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("pages.appointments.حذف_قرار_ملاقات")}
        description={t("pages.appointments.confirm.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={handleDelete}
        loading={submitting}
        isRtl={isRtl}
      />
    </div>
  )
}
