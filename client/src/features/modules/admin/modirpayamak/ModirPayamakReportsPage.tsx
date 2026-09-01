import { useCallback, useEffect, useState } from "react"
import { useSearchParams } from "react-router-dom"
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import { getModirPayamakMessages } from "@/api/modirpayamak"
import { getAjaxMessage } from "@/api/client"
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
  const { t, isRtl, formatDateTime } = useLocale()
  const [searchParams, setSearchParams] = useSearchParams()
  const domainFilter = searchParams.get("domain") ?? ""
  const { layoutProps, setError } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const showNotConfigured =
    configured === false || isModirPayamakNotConfiguredError(layoutProps.error)
  const [tab, setTab] = useState<"outbox" | "inbox" | "local">("outbox")
  const [page, setPage] = useState(1)
  const [recipient, setRecipient] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [domainInput, setDomainInput] = useState(domainFilter)
  const [items, setItems] = useState<EdgeRow[]>([])
  const [localMessages, setLocalMessages] = useState<Array<Record<string, unknown>>>([])
  const [raw, setRaw] = useState<unknown>(null)
  const [loading, setLoading] = useState(true)

  const [detailOpen, setDetailOpen] = useState(false)
  const [detail, setDetail] = useState<EdgeRow | null>(null)

  useEffect(() => {
    setDomainInput(domainFilter)
  }, [domainFilter])

  const applyDomain = () => {
    const next = domainInput.trim()
    const params = new URLSearchParams(searchParams)
    if (next) params.set("domain", next)
    else params.delete("domain")
    setSearchParams(params)
    setPage(1)
  }

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    if (tab === "local") {
      const res = await getModirPayamakMessages(domainFilter || undefined, page)
      if (res.success && res.data) {
        setLocalMessages(res.data.messages ?? [])
        setRaw(res.data)
        setItems([])
      } else {
        setLocalMessages([])
        setRaw(null)
        setError(mapModirPayamakError(getAjaxMessage(res), t) ?? t("pages.modirpayamak.loadError"))
      }
      setLoading(false)
      return
    }
    const filters: Record<string, unknown> = {}
    if (recipient.trim()) filters.recipient = recipient.trim()
    if (statusFilter.trim()) filters.status = statusFilter.trim()
    if (domainFilter.trim()) filters.domain = domainFilter.trim()
    const res =
      tab === "outbox"
        ? await edgeReportOutbox(page, 20, filters)
        : await edgeReportInbox(page, 20, filters)
    if (res.error) {
      setItems([])
      setLocalMessages([])
      setRaw(null)
      setError(mapModirPayamakError(res.error, t))
    } else {
      setItems(res.items)
      setLocalMessages([])
      setRaw(res.raw)
    }
    setLoading(false)
  }, [domainFilter, page, recipient, setError, statusFilter, tab, t])

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

      <Tabs
        value={tab}
        onValueChange={(v) => {
          setTab(v as "outbox" | "inbox" | "local")
          setPage(1)
        }}
      >
        <TabsList>
          <TabsTrigger value="outbox">{t("pages.modirpayamak.outbox")}</TabsTrigger>
          <TabsTrigger value="inbox">{t("pages.modirpayamak.inbox")}</TabsTrigger>
          <TabsTrigger value="local">{t("pages.modirpayamak.localMessages")}</TabsTrigger>
        </TabsList>

        <TabsContent value={tab} className="space-y-4 mt-4 text-start">
          <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => { applyDomain(); void load() }} isRtl={isRtl}>
            <div className="space-y-1">
              <Label>{t("pages.modirpayamak.domain")}</Label>
              <Input
                dir="ltr"
                value={domainInput}
                onChange={(e) => setDomainInput(e.target.value)}
                className="w-[200px]"
                placeholder="example.com"
              />
            </div>
            {tab !== "local" ? (
              <>
                <div className="space-y-1">
                  <Label>{t("pages.modirpayamak.colRecipient")}</Label>
                  <Input dir="ltr" value={recipient} onChange={(e) => setRecipient(e.target.value)} className="w-[180px]" />
                </div>
                <div className="space-y-1">
                  <Label>{t("pages.modirpayamak.status")}</Label>
                  <Input value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="w-[140px]" />
                </div>
              </>
            ) : null}
          </PmFilterBar>

          <Card>
            <CardHeader
              className={cn(
                "flex flex-row items-center justify-between gap-4 text-start",
                isRtl && "flex-row-reverse",
              )}
            >
              <CardTitle>
                {tab === "outbox"
                  ? t("pages.modirpayamak.outbox")
                  : tab === "inbox"
                    ? t("pages.modirpayamak.inbox")
                    : t("pages.modirpayamak.localMessages")}
                {domainFilter ? (
                  <span className="ms-2 font-mono text-sm font-normal text-muted-foreground" dir="ltr">
                    ({domainFilter})
                  </span>
                ) : null}
              </CardTitle>
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
              ) : tab === "local" ? (
                localMessages.length === 0 ? (
                  <div className="p-6">
                    <PmEmptyState message={t("pages.modirpayamak.reportsEmpty")} />
                  </div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t("pages.modirpayamak.domain")}</TableHead>
                        <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                        <TableHead>{t("pages.modirpayamak.cost")}</TableHead>
                        <TableHead>{t("pages.modirpayamak.date")}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {localMessages.map((m, i) => (
                        <TableRow key={String(m.id ?? i)}>
                          <TableCell className="font-mono text-xs" dir="ltr">
                            {String(m.domain ?? "—")}
                          </TableCell>
                          <TableCell>{String(m.status ?? m.sending_type ?? "—")}</TableCell>
                          <TableCell dir="ltr">{String(m.cost ?? "—")}</TableCell>
                          <TableCell className="text-muted-foreground text-xs">
                            {m.created_at ? formatDateTime(String(m.created_at).replace(/-/g, "/").slice(0, 16)) : "—"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )
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
