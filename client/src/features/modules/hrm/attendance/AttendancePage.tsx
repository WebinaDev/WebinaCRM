import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useLocale } from "@/hooks/use-locale"
import {
  checkIn,
  checkOut,
  getAttendance,
  type AttendanceRecord,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { LogIn, LogOut, Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

export function AttendancePage() {
  const { t, isRtl, formatDate } = useLocale()
  const { currentPage, setCurrentPage, totalPages, setTotalPages } = usePmPagination()
  const [items, setItems] = useState<AttendanceRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [acting, setActing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [dateFrom, setDateFrom] = useState(() => {
    const d = new Date()
    d.setDate(1)
    return d.toISOString().slice(0, 10)
  })
  const [dateTo, setDateTo] = useState(() => new Date().toISOString().slice(0, 10))

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getAttendance({
        date_from: dateFrom,
        date_to: dateTo,
        paged: currentPage,
        per_page: 25,
      })
      if (res.success && res.data) {
        setItems(res.data.items ?? [])
        setTotalPages(res.data.total_pages ?? 1)
      } else {
        setError(getAjaxMessage(res) ?? t("pages.hrm.loadError"))
      }
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [dateFrom, dateTo, currentPage, setTotalPages, t])

  useEffect(() => {
    void load()
  }, [load])

  const handleCheckIn = async () => {
    setActing(true)
    setError(null)
    try {
      const res = await checkIn()
      if (res.success) {
        setSuccess(getAjaxMessage(res) ?? t("pages.hrm.attendance.checkInOk"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setActing(false)
    }
  }

  const handleCheckOut = async () => {
    setActing(true)
    setError(null)
    try {
      const res = await checkOut()
      if (res.success) {
        setSuccess(getAjaxMessage(res) ?? t("pages.hrm.attendance.checkOutOk"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setActing(false)
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.attendance.title")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <div className={cn("flex gap-2", isRtl && "flex-row-reverse")}>
          <Button size="sm" onClick={() => void handleCheckIn()} disabled={acting}>
            <LogIn className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
            {t("pages.hrm.attendance.checkIn")}
          </Button>
          <Button size="sm" variant="outline" onClick={() => void handleCheckOut()} disabled={acting}>
            {acting ? <Loader2 className="h-4 w-4 animate-spin" /> : <LogOut className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />}
            {t("pages.hrm.attendance.checkOut")}
          </Button>
        </div>
      }
    >
      <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
        <CardContent className="pt-6 text-start">
          <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void load()} isRtl={isRtl}>
            <DatePicker value={dateFrom} onChange={setDateFrom} />
            <DatePicker value={dateTo} onChange={setDateTo} />
          </PmFilterBar>
          {loading ? (
            <TableListSkeleton rows={6} columns={5} />
          ) : items.length === 0 ? (
            <div className="py-12 text-muted-foreground">{t("pages.hrm.empty")}</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.date")}</TableHead>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.hrm.attendance.checkIn")}</TableHead>
                  <TableHead>{t("pages.hrm.attendance.checkOut")}</TableHead>
                  <TableHead>{t("common.status")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="text-start">{formatDate(row.work_date)}</TableCell>
                    <TableCell>{row.user_name}</TableCell>
                    <TableCell dir="ltr" className="text-start">{row.check_in || "—"}</TableCell>
                    <TableCell dir="ltr" className="text-start">{row.check_out || "—"}</TableCell>
                    <TableCell>{row.status}</TableCell>
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
    </CrmPageLayout>
  )
}
