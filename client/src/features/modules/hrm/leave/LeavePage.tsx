import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { DatePicker } from "@/components/ui/date-picker"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
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
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { normalizeCapabilities } from "@/lib/bootstrapQuery"
import {
  approveLeave,
  getLeaveRequests,
  getLeaveTypes,
  rejectLeave,
  saveLeaveRequest,
  saveLeaveType,
  type LeaveRequest,
  type LeaveType,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { Plus, Loader2, Check, X } from "lucide-react"
import { cn } from "@/lib/utils"

export function LeavePage() {
  const { t, isRtl, formatDate } = useLocale()
  const canHr = normalizeCapabilities(window.webinoDashboard?.bootstrap?.capabilities).includes(
    "webinocrm_route_staff",
  )

  const [tab, setTab] = useState("mine")
  const [types, setTypes] = useState<LeaveType[]>([])
  const [myRequests, setMyRequests] = useState<LeaveRequest[]>([])
  const [pending, setPending] = useState<LeaveRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [form, setForm] = useState({
    leave_type_id: 0,
    date_from: "",
    date_to: "",
    reason: "",
  })
  const [typeForm, setTypeForm] = useState({ name: "", code: "", max_days_per_year: 0 })

  const loadTypes = useCallback(async () => {
    const res = await getLeaveTypes()
    if (res.success && res.data?.types) setTypes(res.data.types)
  }, [])

  const loadMine = useCallback(async () => {
    const res = await getLeaveRequests({ per_page: 50 })
    if (res.success && res.data?.requests) setMyRequests(res.data.requests)
  }, [])

  const loadPending = useCallback(async () => {
    if (!canHr) return
    const res = await getLeaveRequests({ status: "pending", per_page: 50 })
    if (res.success && res.data?.requests) setPending(res.data.requests)
  }, [canHr])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      await Promise.all([loadTypes(), loadMine(), loadPending()])
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [loadTypes, loadMine, loadPending, t])

  useEffect(() => {
    void load()
  }, [load])

  const handleSubmitRequest = async () => {
    if (!form.leave_type_id || !form.date_from || !form.date_to) return
    setSubmitting(true)
    try {
      const res = await saveLeaveRequest({
        leave_type_id: form.leave_type_id,
        date_from: form.date_from,
        date_to: form.date_to,
        reason: form.reason,
      })
      if (res.success) {
        setDialogOpen(false)
        setSuccess(getAjaxMessage(res) ?? t("common.saved"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleSaveType = async () => {
    if (!typeForm.name.trim()) return
    setSubmitting(true)
    try {
      const res = await saveLeaveType({
        name: typeForm.name.trim(),
        code: typeForm.code.trim(),
        max_days_per_year: typeForm.max_days_per_year,
      })
      if (res.success) {
        setTypeForm({ name: "", code: "", max_days_per_year: 0 })
        setSuccess(getAjaxMessage(res) ?? t("common.saved"))
        void loadTypes()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  const requestTable = (rows: LeaveRequest[], showActions?: boolean) => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t("common.name")}</TableHead>
          <TableHead>{t("common.date")}</TableHead>
          <TableHead>{t("pages.hrm.leave.days")}</TableHead>
          <TableHead>{t("common.status")}</TableHead>
          {showActions && <TableHead>{t("common.actions")}</TableHead>}
        </TableRow>
      </TableHeader>
      <TableBody>
        {rows.map((r) => (
          <TableRow key={r.id}>
            <TableCell>{r.user_name}</TableCell>
            <TableCell className="text-start">
              {formatDate(r.date_from)} — {formatDate(r.date_to)}
            </TableCell>
            <TableCell>{r.days}</TableCell>
            <TableCell>{r.status}</TableCell>
            {showActions && r.status === "pending" && (
              <TableCell>
                <div className={cn("flex gap-1", isRtl && "flex-row-reverse")}>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={async () => {
                      const res = await approveLeave(r.id)
                      if (res.success) {
                        setSuccess(getAjaxMessage(res) ?? t("common.saved"))
                        void load()
                      } else setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
                    }}
                  >
                    <Check className="h-4 w-4" />
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    className="text-destructive"
                    onClick={async () => {
                      const res = await rejectLeave(r.id)
                      if (res.success) {
                        setSuccess(getAjaxMessage(res) ?? t("common.saved"))
                        void load()
                      } else setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
                    }}
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              </TableCell>
            )}
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )

  return (
    <CrmPageLayout
      title={t("pages.hrm.leave.title")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <Button size="sm" onClick={() => setDialogOpen(true)}>
          <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
          {t("pages.hrm.leave.newRequest")}
        </Button>
      }
    >
      <Tabs value={tab} onValueChange={setTab} dir={isRtl ? "rtl" : "ltr"} className="text-start">
        <TabsList className="text-start">
          <TabsTrigger value="mine">{t("pages.hrm.leave.myRequests")}</TabsTrigger>
          {canHr && <TabsTrigger value="pending">{t("pages.hrm.leave.pending")}</TabsTrigger>}
          {canHr && <TabsTrigger value="types">{t("pages.hrm.leave.types")}</TabsTrigger>}
        </TabsList>
        <TabsContent value="mine" className="mt-4">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6">
              {loading ? (
                <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
              ) : myRequests.length === 0 ? (
                <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                requestTable(myRequests)
              )}
            </CardContent>
          </Card>
        </TabsContent>
        {canHr && (
          <TabsContent value="pending" className="mt-4">
            <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
              <CardContent className="pt-6">
                {pending.length === 0 ? (
                  <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
                ) : (
                  requestTable(pending, true)
                )}
              </CardContent>
            </Card>
          </TabsContent>
        )}
        {canHr && (
          <TabsContent value="types" className="mt-4">
            <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
              <CardContent className="pt-6 space-y-4">
                <div className="flex flex-wrap gap-2 items-end">
                  <Input
                    placeholder={t("common.name")}
                    value={typeForm.name}
                    onChange={(e) => setTypeForm((f) => ({ ...f, name: e.target.value }))}
                    className="max-w-xs"
                  />
                  <Input
                    placeholder={t("pages.hrm.leave.code")}
                    value={typeForm.code}
                    onChange={(e) => setTypeForm((f) => ({ ...f, code: e.target.value }))}
                    className="max-w-[120px]"
                  />
                  <Button onClick={() => void handleSaveType()} disabled={submitting}>
                    {t("common.add")}
                  </Button>
                </div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("common.name")}</TableHead>
                      <TableHead>{t("pages.hrm.leave.code")}</TableHead>
                      <TableHead>{t("pages.hrm.leave.maxDays")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {types.map((lt) => (
                      <TableRow key={lt.id}>
                        <TableCell>{lt.name}</TableCell>
                        <TableCell dir="ltr" className="text-start">{lt.code}</TableCell>
                        <TableCell>{lt.max_days_per_year}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>
        )}
      </Tabs>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{t("pages.hrm.leave.newRequest")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <Select
              value={form.leave_type_id ? String(form.leave_type_id) : ""}
              onValueChange={(v) => setForm((f) => ({ ...f, leave_type_id: parseInt(v, 10) }))}
            >
              <SelectTrigger>
                <SelectValue placeholder={t("pages.hrm.leave.type")} />
              </SelectTrigger>
              <SelectContent>
                {types.map((lt) => (
                  <SelectItem key={lt.id} value={String(lt.id)}>{lt.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <DatePicker value={form.date_from} onChange={(v) => setForm((f) => ({ ...f, date_from: v }))} />
            <DatePicker value={form.date_to} onChange={(v) => setForm((f) => ({ ...f, date_to: v }))} />
            <Textarea
              value={form.reason}
              onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
              placeholder={t("pages.hrm.leave.reason")}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{t("common.cancel")}</Button>
            <Button onClick={() => void handleSubmitRequest()} disabled={submitting}>
              {submitting && <Loader2 className="h-4 w-4 animate-spin me-2" />}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  )
}
