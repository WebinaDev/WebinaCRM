import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
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
import { getCampaigns, manageCampaign, deleteCampaign, type Campaign } from "@/api/campaigns"
import { useLocale } from "@/hooks/use-locale"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import { Plus, Pencil, Trash2 } from "lucide-react"
import { cn } from "@/lib/utils"
import { CampaignFormDialog, type CampaignFormState } from "./CampaignFormDialog"

const STATUS_VARIANT: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  draft: "secondary",
  active: "default",
  paused: "outline",
  ended: "destructive",
}

const emptyForm = (): CampaignFormState => ({
  title: "",
  description: "",
  channel: "web",
  status: "draft",
  budget: "",
  start_date: "",
  end_date: "",
})

export function CampaignsPage() {
  const { t, isRtl, formatNumber } = useLocale()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [campaigns, setCampaigns] = useState<Campaign[]>([])
  const [channels, setChannels] = useState<{ slug: string; name: string }[]>([])
  const [statuses, setStatuses] = useState<{ slug: string; name: string }[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [statusFilter, setStatusFilter] = useState("")
  const [channelFilter, setChannelFilter] = useState("")
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [form, setForm] = useState<CampaignFormState>(emptyForm())
  const [deleteTarget, setDeleteTarget] = useState<Campaign | null>(null)
  const [unlinkTarget, setUnlinkTarget] = useState<Campaign | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getCampaigns({
        search: debouncedSearch || undefined,
        status_filter: statusFilter || undefined,
        channel_filter: channelFilter || undefined,
        paged: currentPage,
      })
      if (res.success && res.data) {
        setCampaigns(res.data.campaigns ?? [])
        setChannels(res.data.channels ?? [])
        setStatuses(res.data.statuses ?? [])
        setTotalPages(res.data.total_pages ?? 1)
      } else {
        setError(res.message ?? t("pages.campaigns.خطا_در_بارگذاری"))
      }
    } catch {
      setError(t("pages.campaigns.خطا_در_بارگذاری"))
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, statusFilter, channelFilter, currentPage, setTotalPages, t])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    resetPage()
  }, [debouncedSearch, statusFilter, channelFilter, resetPage])

  const openNew = () => {
    setEditingId(null)
    setForm(emptyForm())
    setError(null)
    setDialogOpen(true)
  }

  const openEdit = (c: Campaign) => {
    setEditingId(c.id)
    setForm({
      title: c.title,
      description: c.description ?? "",
      channel: c.channel,
      status: c.status,
      budget: c.budget ? String(c.budget) : "",
      start_date: c.start_date?.slice(0, 10) ?? "",
      end_date: c.end_date?.slice(0, 10) ?? "",
    })
    setError(null)
    setDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.title.trim()) {
      setError(t("pages.campaigns.عنوان_الزامی"))
      return
    }
    if (form.start_date && form.end_date && form.end_date < form.start_date) {
      setError(t("pages.campaigns.تاریخ_نامعتبر"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await manageCampaign({
        campaign_id: editingId ?? undefined,
        title: form.title.trim(),
        description: form.description.trim() || undefined,
        channel: form.channel,
        status: form.status,
        budget: parseFloat(form.budget) || 0,
        start_date: form.start_date || undefined,
        end_date: form.end_date || undefined,
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

  const runDelete = async (c: Campaign, unlink: boolean) => {
    setSubmitting(true)
    setError(null)
    try {
      const res = await deleteCampaign(c.id, unlink)
      if (res.success) {
        setSuccess(res.message ?? t("common.delete"))
        void load()
      } else if (!unlink && res.data && typeof res.data === "object" && "lead_count" in res.data) {
        const count = (res.data as { lead_count?: number }).lead_count ?? c.lead_count
        if (count > 0) {
          setUnlinkTarget(c)
        } else {
          setError(res.message ?? t("common.errors.deleteFailed"))
        }
      } else {
        setError(res.message ?? t("common.errors.deleteFailed"))
      }
    } catch {
      setError(t("common.errors.deleteFailed"))
    } finally {
      setSubmitting(false)
      setDeleteTarget(null)
      setUnlinkTarget(null)
    }
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.campaigns.کمپینها")}
        isRtl={isRtl}
        actions={
          <Button size="sm" onClick={openNew}>
            <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
            {t("pages.campaigns.افزودن_جدید")}
          </Button>
        }
      />

      <PmAlerts error={error} success={success} />

      <Card>
        <CardContent className="pt-6">
          <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void load()} isRtl={isRtl}>
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
              <SelectTrigger className="w-[140px]">
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
              value={channelFilter === "" ? SELECT_ALL_VALUE : channelFilter}
              onValueChange={(v) => setChannelFilter(v === SELECT_ALL_VALUE ? "" : v)}
            >
              <SelectTrigger className="w-[130px]">
                <SelectValue placeholder={t("pages.campaigns.کانال")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                {channels.map((ch) => (
                  <SelectItem key={ch.slug} value={ch.slug}>{ch.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </PmFilterBar>

          {loading ? (


            <TableListSkeleton rows={8} columns={6} withAvatarColumn />


          ) : campaigns.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">{t("common.noResults")}</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.campaigns.عنوان")}</TableHead>
                  <TableHead>{t("pages.campaigns.کانال")}</TableHead>
                  <TableHead>{t("common.status")}</TableHead>
                  <TableHead>{t("pages.campaigns.بودجه")}</TableHead>
                  <TableHead>{t("pages.campaigns.تاریخ_شروع")}</TableHead>
                  <TableHead>{t("pages.campaigns.تاریخ_پایان")}</TableHead>
                  <TableHead>{t("pages.campaigns.تعداد_سرنخ")}</TableHead>
                  <TableHead>{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {campaigns.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">{c.title}</TableCell>
                    <TableCell>{c.channel_label}</TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[c.status] ?? "secondary"}>{c.status_label}</Badge>
                    </TableCell>
                    <TableCell dir="ltr">{formatNumber(c.budget)}</TableCell>
                    <TableCell>{c.start_date_display || "—"}</TableCell>
                    <TableCell>{c.end_date_display || "—"}</TableCell>
                    <TableCell>{c.lead_count}</TableCell>
                    <TableCell>
                      <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => openEdit(c)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          className="text-destructive"
                          onClick={() => setDeleteTarget(c)}
                          disabled={submitting}
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

          <PmPagination
            page={currentPage}
            totalPages={totalPages}
            onPageChange={setCurrentPage}
            prevLabel={t("common.prevPage")}
            nextLabel={t("common.nextPage")}
            isRtl={isRtl}
          />
        </CardContent>
      </Card>

      <CampaignFormDialog
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        editing={editingId !== null}
        form={form}
        setForm={setForm}
        channels={channels}
        statuses={statuses}
        submitting={submitting}
        onSubmit={handleSubmit}
      />

      <PmConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title={t("common.delete")}
        description={t("pages.campaigns.confirm.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        destructive
        onConfirm={async () => {
          if (deleteTarget) await runDelete(deleteTarget, false)
        }}
      />

      <PmConfirmDialog
        open={unlinkTarget !== null}
        onOpenChange={(open) => !open && setUnlinkTarget(null)}
        title={t("common.delete")}
        description={t("pages.campaigns.confirm.deleteWithLeads")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        destructive
        onConfirm={async () => {
          if (unlinkTarget) await runDelete(unlinkTarget, true)
        }}
      />
    </div>
  )
}
