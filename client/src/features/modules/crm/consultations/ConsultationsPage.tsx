import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useNavigate } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
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
  getConsultations,
  manageConsultation,
  convertConsultationToProject,
  type Consultation,
} from "@/api/consultations"
import { useLocale } from "@/hooks/use-locale"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import { Plus, Pencil, FolderInput } from "lucide-react"
import { cn } from "@/lib/utils"
import {
  ConsultationFormDialog,
  type ConsultationFormState,
} from "./ConsultationFormDialog"

function parseConsultationDatetime(datetime?: string): { date: string; time: string } {
  if (!datetime?.trim()) return { date: "", time: "" }
  const [datePart, timePart] = datetime.trim().split(/\s+/, 2)
  return { date: datePart ?? "", time: timePart ? timePart.slice(0, 5) : "" }
}

function spaPathFromRedirect(redirectUrl: string): string | null {
  const base =
    typeof window !== "undefined" && window.webinocrm?.dashboardBasePath
      ? window.webinocrm.dashboardBasePath.replace(/\/$/, "")
      : "/dashboard"
  if (redirectUrl.startsWith(base)) {
    const path = redirectUrl.slice(base.length)
    return path.startsWith("/") ? path : `/${path}`
  }
  const match = redirectUrl.match(/(\/contracts\?[^#]+)/)
  return match ? match[1] : null
}

const emptyForm = (): ConsultationFormState => ({
  name: "",
  phone: "",
  email: "",
  type: "phone",
  date: "",
  time: "",
  status: "in-progress",
  notes: "",
})

export function ConsultationsPage() {
  const { t, isRtl } = useLocale()
  const navigate = useNavigate()
  const [allConsultations, setAllConsultations] = useState<Consultation[]>([])
  const [statuses, setStatuses] = useState<{ slug: string; name: string }[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [statusFilter, setStatusFilter] = useState("")
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [form, setForm] = useState<ConsultationFormState>(emptyForm())
  const [convertTargetId, setConvertTargetId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getConsultations()
      if (res.success && res.data) {
        setAllConsultations(res.data.consultations ?? [])
        setStatuses(res.data.statuses ?? [])
      } else {
        setError(res.message ?? t("pages.consultations.خطا_در_بارگذاری_مشاورهها"))
      }
    } catch {
      setError(t("pages.consultations.خطا_در_بارگذاری_مشاورهها"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const consultations = useMemo(() => {
    let list = allConsultations
    if (statusFilter) {
      list = list.filter((c) => c.status_slug === statusFilter)
    }
    if (debouncedSearch.trim()) {
      const q = debouncedSearch.trim().toLowerCase()
      list = list.filter(
        (c) =>
          c.name.toLowerCase().includes(q) ||
          c.phone.includes(q) ||
          (c.email ?? "").toLowerCase().includes(q)
      )
    }
    return list
  }, [allConsultations, statusFilter, debouncedSearch])

  const openNew = () => {
    setEditingId(null)
    setForm(emptyForm())
    setError(null)
    setDialogOpen(true)
  }

  const openEdit = (c: Consultation) => {
    setEditingId(c.id)
    const { date, time } = parseConsultationDatetime(c.datetime)
    setForm({
      name: c.name,
      phone: c.phone,
      email: c.email ?? "",
      type: c.type,
      date,
      time,
      status: c.status_slug,
      notes: c.notes ?? "",
    })
    setError(null)
    setDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.name.trim() || !form.phone.trim()) {
      setError(t("pages.consultations.نام_و_شماره_تماس_الزامی_هستند"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await manageConsultation({
        consultation_id: editingId ?? undefined,
        name: form.name.trim(),
        phone: form.phone.trim(),
        email: form.email.trim() || undefined,
        type: form.type,
        date: form.date || undefined,
        time: form.time || undefined,
        status: form.status,
        notes: form.notes.trim() || undefined,
      })
      if (res.success) {
        setDialogOpen(false)
        setSuccess(res.message ?? t("common.save"))
        void load()
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleConvert = async () => {
    if (convertTargetId == null) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await convertConsultationToProject(convertTargetId)
      if (res.success) {
        const url = (res.data as { redirect_url?: string })?.redirect_url
        if (url) {
          const path = spaPathFromRedirect(url)
          if (path) {
            navigate(path)
            return
          }
        }
        setSuccess(res.message ?? t("pages.consultations.تبدیل_موفق"))
        void load()
      } else {
        setError(res.message ?? t("pages.consultations.خطا_در_تبدیل"))
      }
    } catch {
      setError(t("pages.consultations.خطا_در_تبدیل"))
    } finally {
      setSubmitting(false)
      setConvertTargetId(null)
    }
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.consultations.مدیریت_مشاورهها")}
        isRtl={isRtl}
        actions={
          <Button size="sm" onClick={openNew}>
            <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
            {t("pages.consultations.افزودن_جدید")}
          </Button>
        }
      />

      <PmAlerts error={error} success={success} />

      <Card>
        <CardContent className="pt-6">
          <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => {}} isRtl={isRtl}>
            <Input
              placeholder={t("common.search")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="max-w-[220px]"
            />
            <Select
              value={statusFilter === "" ? SELECT_ALL_VALUE : statusFilter}
              onValueChange={(v) => setStatusFilter(v === SELECT_ALL_VALUE ? "" : v)}
            >
              <SelectTrigger className="w-[160px]">
                <SelectValue placeholder={t("common.status")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                {statuses.map((s) => (
                  <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </PmFilterBar>

          {loading ? (


            <TableListSkeleton rows={8} columns={6} withAvatarColumn />


          ) : consultations.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">{t("common.noResults")}</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.consultations.نام_درخواست_کننده")}</TableHead>
                  <TableHead>{t("pages.consultations.شماره_تماس")}</TableHead>
                  <TableHead>{t("pages.consultations.نوع_مشاوره")}</TableHead>
                  <TableHead>{t("pages.appointments.تاریخ_و_ساعت")}</TableHead>
                  <TableHead>{t("pages.consultations.نتیجه")}</TableHead>
                  <TableHead>{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {consultations.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">{c.name}</TableCell>
                    <TableCell dir="ltr">{c.phone}</TableCell>
                    <TableCell>{c.type_label}</TableCell>
                    <TableCell>{c.datetime_display || "—"}</TableCell>
                    <TableCell>
                      <Badge variant={c.status_slug === "converted" ? "default" : "secondary"}>
                        {c.status_name}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => openEdit(c)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        {c.status_slug !== "converted" && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setConvertTargetId(c.id)}
                            disabled={submitting}
                            title={t("pages.consultations.تبدیل_به_پروژه")}
                          >
                            <FolderInput className="h-4 w-4" />
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <ConsultationFormDialog
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        editing={editingId !== null}
        form={form}
        setForm={setForm}
        statuses={statuses}
        submitting={submitting}
        onSubmit={handleSubmit}
      />

      <PmConfirmDialog
        open={convertTargetId !== null}
        onOpenChange={(open) => !open && setConvertTargetId(null)}
        title={t("pages.consultations.تبدیل_به_پروژه")}
        description={t("pages.consultations.confirm.convertToProject")}
        confirmLabel={t("common.yes")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        destructive={false}
        onConfirm={handleConvert}
      />
    </div>
  )
}
