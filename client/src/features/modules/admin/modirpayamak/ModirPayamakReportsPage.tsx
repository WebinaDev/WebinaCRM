import { useCallback, useEffect, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import {
  edgeField,
  edgeReportInbox,
  edgeReportOutbox,
  edgeReportOutboxDetail,
  type EdgeRow,
} from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakOutboxTable } from "./components/ModirPayamakOutboxTable"
import { cn } from "@/lib/utils"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { isModirPayamakNotConfiguredError, mapModirPayamakError } from "./modirpayamak-errors"

export function ModirPayamakReportsPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const showNotConfigured =
    configured === false || isModirPayamakNotConfiguredError(layoutProps.error)
  const [tab, setTab] = useState<"outbox" | "inbox">("outbox")
  const [page, setPage] = useState(1)
  const [recipient, setRecipient] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [items, setItems] = useState<EdgeRow[]>([])
  const [raw, setRaw] = useState<unknown>(null)
  const [loading, setLoading] = useState(true)

  const [detailOpen, setDetailOpen] = useState(false)
  const [detail, setDetail] = useState<EdgeRow | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const filters: Record<string, unknown> = {}
    if (recipient.trim()) filters.recipient = recipient.trim()
    if (statusFilter.trim()) filters.status = statusFilter.trim()
    const res =
      tab === "outbox"
        ? await edgeReportOutbox(page, 20, filters)
        : await edgeReportInbox(page, 20, filters)
    if (res.error) {
      setItems([])
      setRaw(null)
      setError(mapModirPayamakError(res.error, t))
    } else {
      setItems(res.items)
      setRaw(res.raw)
    }
    setLoading(false)
  }, [page, recipient, statusFilter, setError, tab, t])

  useEffect(() => {
    void load()
  }, [load])

  const openDetail = async (row: EdgeRow) => {
    const id = edgeField(row, "id", "messages_outbox_id", "outbox_id")
    if (id === "—") {
      setDetail(row)
      setDetailOpen(true)
      return
    }
    const res = await edgeReportOutboxDetail(id)
    setDetail(res.item ?? row)
    setDetailOpen(true)
  }

  return (
    <CrmPageLayout title={t("pages.modirpayamak.reportsTitle")} {...layoutProps}>
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.reportsTitle")} />
      <ModirPayamakNotConfigured configured={!showNotConfigured} />

      <Tabs value={tab} onValueChange={(v) => { setTab(v as "outbox" | "inbox"); setPage(1) }}>
        <TabsList>
          <TabsTrigger value="outbox">{t("pages.modirpayamak.outbox")}</TabsTrigger>
          <TabsTrigger value="inbox">{t("pages.modirpayamak.inbox")}</TabsTrigger>
        </TabsList>

        <TabsContent value={tab} className="space-y-4 mt-4 text-start">
          <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void load()} isRtl={isRtl}>
            <div className="space-y-1">
              <Label>{t("pages.modirpayamak.colRecipient")}</Label>
              <Input dir="ltr" value={recipient} onChange={(e) => setRecipient(e.target.value)} className="w-[180px]" />
            </div>
            <div className="space-y-1">
              <Label>{t("pages.modirpayamak.status")}</Label>
              <Input value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="w-[140px]" />
            </div>
          </PmFilterBar>

          <Card>
            <CardHeader
              className={cn(
                "flex flex-row items-center justify-between gap-4 text-start",
                isRtl && "flex-row-reverse",
              )}
            >
              <CardTitle>{tab === "outbox" ? t("pages.modirpayamak.outbox") : t("pages.modirpayamak.inbox")}</CardTitle>
              <div className="flex gap-2">
                <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  {t("common.prevPage")}
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => setPage((p) => p + 1)}>
                  {t("common.nextPage")}
                </Button>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              {loading ? (
                <TableListSkeleton rows={8} columns={4} />
              ) : items.length === 0 ? (
                <div className="p-6">
                  <PmEmptyState message={t("pages.modirpayamak.reportsEmpty")} />
                </div>
              ) : (
                <ModirPayamakOutboxTable items={items} onRowClick={(row) => void openDetail(row)} />
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent side={isRtl ? "left" : "right"} className="sm:max-w-lg overflow-y-auto">
          <SheetHeader>
            <SheetTitle>{t("pages.modirpayamak.reportDetail")}</SheetTitle>
          </SheetHeader>
          {detail ? (
            <dl className="mt-4 space-y-2 text-sm">
              <div><dt className="text-muted-foreground">{t("pages.modirpayamak.colRecipient")}</dt><dd dir="ltr">{edgeField(detail, "recipient", "to", "mobile")}</dd></div>
              <div><dt className="text-muted-foreground">{t("pages.modirpayamak.status")}</dt><dd>{edgeField(detail, "status", "state")}</dd></div>
              <div><dt className="text-muted-foreground">{t("pages.modirpayamak.message")}</dt><dd className="whitespace-pre-wrap">{edgeField(detail, "message", "text", "body")}</dd></div>
            </dl>
          ) : null}
        </SheetContent>
      </Sheet>

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
