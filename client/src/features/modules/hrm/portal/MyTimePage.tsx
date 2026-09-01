import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  checkIn,
  checkOut,
  getMyLeaveBalances,
  getMyLeaveTypes,
  getMyAttendance,
  getMyShift,
  submitHrmRequest,
  type AttendanceRecord,
  type LeaveBalance,
  type LeaveType,
  type ShiftTemplate,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function MyTimePage() {
  const { t, isRtl, formatNumber } = useLocale()
  const [balances, setBalances] = useState<LeaveBalance[]>([])
  const [types, setTypes] = useState<LeaveType[]>([])
  const [attendance, setAttendance] = useState<AttendanceRecord[]>([])
  const [shift, setShift] = useState<ShiftTemplate | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [leaveTypeId, setLeaveTypeId] = useState("")
  const [dateFrom, setDateFrom] = useState("")
  const [dateTo, setDateTo] = useState("")
  const [reason, setReason] = useState("")
  const [disputeNote, setDisputeNote] = useState("")
  const [selectedAttendanceId, setSelectedAttendanceId] = useState("")

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [balRes, typeRes, attRes, shiftRes] = await Promise.all([
        getMyLeaveBalances(),
        getMyLeaveTypes(),
        getMyAttendance(),
        getMyShift(),
      ])
      if (balRes.success && balRes.data?.balances) setBalances(balRes.data.balances)
      if (typeRes.success && typeRes.data?.types) setTypes(typeRes.data.types)
      if (attRes.success && attRes.data?.items) setAttendance(attRes.data.items)
      if (shiftRes.success) setShift(shiftRes.data?.shift ?? null)
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const punch = async (action: "in" | "out") => {
    const res = action === "in" ? await checkIn() : await checkOut()
    if (res.success) {
      setSuccess(res.data?.message ?? t("common.saved"))
      void load()
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  const submitLeave = async () => {
    if (!leaveTypeId || !dateFrom || !dateTo) return
    const res = await submitHrmRequest({
      type: "leave",
      payload: { leave_type_id: Number(leaveTypeId), date_from: dateFrom, date_to: dateTo, reason },
    })
    if (res.success) {
      setSuccess(res.data?.message ?? t("common.saved"))
      setReason("")
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  const submitDispute = async () => {
    if (!selectedAttendanceId) return
    const res = await submitHrmRequest({
      type: "attendance_dispute",
      payload: { attendance_id: Number(selectedAttendanceId), note: disputeNote },
    })
    if (res.success) {
      setSuccess(res.data?.message ?? t("common.saved"))
      setDisputeNote("")
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.portal.myTime")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      {loading ? (
        <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
      ) : (
        <div className="space-y-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
          <div className="flex flex-wrap gap-2">
            <Button onClick={() => void punch("in")}>{t("pages.hrm.attendance.checkIn")}</Button>
            <Button variant="outline" onClick={() => void punch("out")}>{t("pages.hrm.attendance.checkOut")}</Button>
          </div>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.leaveBalance")}</CardTitle></CardHeader>
            <CardContent className="space-y-1 text-sm">
              {balances.map((b) => (
                <div key={b.id} className="flex justify-between">
                  <span>{types.find((x) => x.id === b.leave_type_id)?.name ?? b.leave_type_id}</span>
                  <span>{formatNumber(b.balance)}</span>
                </div>
              ))}
            </CardContent>
          </Card>
          {shift && (
            <Card>
              <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.shift")}</CardTitle></CardHeader>
              <CardContent className="text-sm">
                {shift.name}: {shift.start_time} – {shift.end_time}
              </CardContent>
            </Card>
          )}
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.leave.newRequest")}</CardTitle></CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <Select value={leaveTypeId || "_"} onValueChange={(v) => setLeaveTypeId(v === "_" ? "" : v)}>
                <SelectTrigger><SelectValue placeholder={t("pages.hrm.leave.type")} /></SelectTrigger>
                <SelectContent>
                  {types.map((lt) => (
                    <SelectItem key={lt.id} value={String(lt.id)}>{lt.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <DatePicker value={dateFrom} onChange={setDateFrom} placeholder={t("pages.hrm.portal.dateFrom")} />
              <DatePicker value={dateTo} onChange={setDateTo} placeholder={t("pages.hrm.portal.dateTo")} />
              <Textarea className="sm:col-span-2" value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t("pages.hrm.leave.reason")} />
              <Button onClick={() => void submitLeave()}>{t("common.submit")}</Button>
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.attendance.title")}</CardTitle></CardHeader>
            <CardContent className="space-y-2 text-sm">
              {attendance.length === 0 ? (
                <div className="text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                attendance.map((a) => (
                  <div key={a.id} className="flex justify-between rounded border px-3 py-2">
                    <span>{a.work_date}</span>
                    <span>{a.check_in || "—"} – {a.check_out || "—"}</span>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t("pages.hrm.portal.attendanceDispute")}</CardTitle></CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <Select value={selectedAttendanceId || "_"} onValueChange={(v) => setSelectedAttendanceId(v === "_" ? "" : v)}>
                <SelectTrigger><SelectValue placeholder={t("pages.hrm.portal.selectDay")} /></SelectTrigger>
                <SelectContent>
                  {attendance.map((a) => (
                    <SelectItem key={a.id} value={String(a.id)}>{a.work_date}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Input value={disputeNote} onChange={(e) => setDisputeNote(e.target.value)} placeholder={t("pages.hrm.portal.disputeNote")} />
              <Button onClick={() => void submitDispute()}>{t("common.submit")}</Button>
            </CardContent>
          </Card>
        </div>
      )}
    </CrmPageLayout>
  )
}
