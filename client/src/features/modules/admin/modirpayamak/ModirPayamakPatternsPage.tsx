import { useCallback, useEffect, useState } from "react"
import { Link, useNavigate } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
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
import {
  attachModirPayamakPattern,
  detachModirPayamakPattern,
  getModirPayamakCustomers,
  getModirPayamakDomainPatternRegistry,
  type ModirPayamakPatternScope,
  type ModirPayamakRegistryRow,
} from "@/api/modirpayamak"
import {
  edgeDeletePattern,
  edgeField,
  edgeGetPattern,
  edgeListPatterns,
  edgeSavePattern,
  type EdgeRow,
} from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { useModirPayamakEdge } from "./hooks/useModirPayamakEdge"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import {
  emptyPatternForm,
  ModirPayamakPatternForm,
  patternFormToPayload,
  validatePatternForm,
  type PatternFormValue,
} from "./components/ModirPayamakPatternForm"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { Badge } from "@/components/ui/badge"
import { Code, Eye, Link2, Pencil, Plus, Send, Trash2 } from "lucide-react"

const ORDER_EVENTS = [
  "pending_on_create",
  "pending_on_status",
  "on-hold",
  "processing",
  "packaged",
  "sent-to-warehouse",
  "courier",
  "post",
  "tipax",
  "completed",
  "cancelled",
  "failed",
  "refunded",
  "checkout-draft",
  "cart-abandoned",
  "order-abandoned",
  "user-welcome",
  "stock-low",
  "stock-out",
]

const SITE_EVENTS = ["otp_login", "otp_register"]

function eventLabel(t: (key: string) => string, key: string): string {
  const i18nKey = `settings.shopSms.events.${key}`
  const translated = t(i18nKey)
  if (translated !== i18nKey) return translated
  if (key === "otp_login") return t("pages.modirpayamak.eventOtpLogin")
  if (key === "otp_register") return t("pages.modirpayamak.eventOtpRegister")
  return key
}

function SyncBadge({ status }: { status?: string }) {
  const { t } = useLocale()
  if (status === "synced") return <Badge>{t("pages.modirpayamak.syncSynced")}</Badge>
  if (status === "pending") return <Badge variant="secondary">{t("pages.modirpayamak.syncPending")}</Badge>
  if (status === "failed") return <Badge variant="destructive">{t("pages.modirpayamak.syncFailed")}</Badge>
  return <Badge variant="outline">{t("pages.modirpayamak.syncNone")}</Badge>
}

export function ModirPayamakPatternsPage() {
  const { t, isRtl, formatDateTime } = useLocale()
  const navigate = useNavigate()
  const { layoutProps, setError, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const [page, setPage] = useState(1)
  const loader = useCallback(() => edgeListPatterns(page, 20), [page])
  const { items, raw, loading, error, reload } = useModirPayamakEdge(loader, { deps: [page] })

  const [detailOpen, setDetailOpen] = useState(false)
  const [detailRow, setDetailRow] = useState<EdgeRow | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [editCode, setEditCode] = useState<string | null>(null)
  const [form, setForm] = useState<PatternFormValue>(emptyPatternForm())
  const [saving, setSaving] = useState(false)
  const [deleteCode, setDeleteCode] = useState<string | null>(null)
  const [deleting, setDeleting] = useState(false)

  const [attachOpen, setAttachOpen] = useState(false)
  const [attachCode, setAttachCode] = useState("")
  const [attachDomain, setAttachDomain] = useState("")
  const [attachScope, setAttachScope] = useState<ModirPayamakPatternScope>("order_customer")
  const [attachEvent, setAttachEvent] = useState("processing")
  const [domains, setDomains] = useState<string[]>([])
  const [attaching, setAttaching] = useState(false)
  const [domainRegistry, setDomainRegistry] = useState<ModirPayamakRegistryRow[]>([])
  const [registryFilter, setRegistryFilter] = useState("")
  const [allRegistry, setAllRegistry] = useState<ModirPayamakRegistryRow[]>([])
  const [registryLoading, setRegistryLoading] = useState(false)

  const eventOptions = attachScope === "site" ? SITE_EVENTS : ORDER_EVENTS

  const loadAllRegistry = useCallback(async (domain = "") => {
    setRegistryLoading(true)
    const res = await getModirPayamakDomainPatternRegistry(domain.trim())
    setRegistryLoading(false)
    if (res.success && res.data?.registry) {
      setAllRegistry(res.data.registry)
    } else {
      setAllRegistry([])
    }
  }, [])

  useEffect(() => {
    void loadAllRegistry("")
    void (async () => {
      const res = await getModirPayamakCustomers()
      if (res.success && res.data?.accounts) {
        setDomains(res.data.accounts.map((a) => a.domain).filter(Boolean))
      }
    })()
  }, [loadAllRegistry])

  const openDetail = async (row: EdgeRow) => {
    const code = edgeField(row, "code", "pattern_code", "id")
    if (code === "—") {
      setDetailRow(row)
      setDetailOpen(true)
      return
    }
    const res = await edgeGetPattern(code)
    setDetailRow(res.item ?? row)
    setDetailOpen(true)
  }

  const openCreate = () => {
    setEditCode(null)
    setForm(emptyPatternForm())
    setEditOpen(true)
  }

  const openEdit = (row: EdgeRow) => {
    setEditCode(edgeField(row, "code", "pattern_code", "id"))
    const message = edgeField(row, "message", "text", "body", "pattern")
    const vars: PatternFormValue["variable"] = []
    const rawVars = row.variable ?? row.vars
    if (Array.isArray(rawVars)) {
      for (const v of rawVars) {
        if (v && typeof v === "object") {
          const obj = v as Record<string, unknown>
          vars.push({
            name: String(obj.name ?? obj.var ?? ""),
            type: String(obj.type ?? "string") === "integer" ? "integer" : "string",
          })
        }
      }
    }
    setForm({
      ...emptyPatternForm(),
      title: edgeField(row, "title", "name"),
      description: edgeField(row, "description", "title", "name"),
      website: edgeField(row, "website") === "—" ? "" : edgeField(row, "website"),
      message: message === "—" ? "" : message,
      is_share: Boolean(row.is_share),
      variable: vars,
      checklist: {
        brandInMessage: true,
        nonPromotional: true,
        enamadIfLink: true,
        testWordIfTest: true,
      },
    })
    setEditOpen(true)
  }

  const openAttach = async (row: EdgeRow) => {
    const code = edgeField(row, "code", "pattern_code", "id")
    setAttachCode(code !== "—" ? code : "")
    setAttachDomain("")
    setAttachScope("order_customer")
    setAttachEvent("processing")
    setDomainRegistry([])
    setAttachOpen(true)
    const res = await getModirPayamakCustomers()
    if (res.success && res.data?.accounts) {
      setDomains(res.data.accounts.map((a) => a.domain).filter(Boolean))
    }
  }

  const loadDomainRegistry = async (domain: string) => {
    if (!domain) {
      setDomainRegistry([])
      return
    }
    const res = await getModirPayamakDomainPatternRegistry(domain)
    if (res.success && res.data?.registry) {
      setDomainRegistry(res.data.registry)
    } else {
      setDomainRegistry([])
    }
  }

  const submitAttach = async () => {
    if (!attachDomain.trim() || !attachCode.trim() || !attachEvent.trim()) {
      setError(t("pages.modirpayamak.domainRequired"))
      return
    }
    setAttaching(true)
    const res = await attachModirPayamakPattern({
      domain: attachDomain.trim(),
      scope: attachScope,
      event_key: attachEvent,
      pattern_code: attachCode.trim(),
    })
    setAttaching(false)
    if (applyResponse(res, { successMessage: res.data?.message ?? t("pages.modirpayamak.patternAttached") })) {
      if (res.data?.registry) setDomainRegistry(res.data.registry)
      else void loadDomainRegistry(attachDomain.trim())
      void loadAllRegistry(registryFilter)
    }
  }

  const save = async () => {
    const err = validatePatternForm(form, t)
    if (err) {
      setError(err)
      return
    }
    setSaving(true)
    setError(null)
    const body = patternFormToPayload(form)
    const res = await edgeSavePattern(editCode, body)
    setSaving(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setEditOpen(false)
      void reload()
    }
  }

  const confirmDelete = async () => {
    if (!deleteCode) return
    setDeleting(true)
    const res = await edgeDeletePattern(deleteCode)
    setDeleting(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.deleted") })) {
      setDeleteCode(null)
      void reload()
    }
  }

  const useInSend = (row: EdgeRow) => {
    const code = edgeField(row, "code", "pattern_code", "id")
    navigate(`/admin/integrations/modirpayamak/send?pattern=${encodeURIComponent(code)}&mode=pattern`)
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.patternsTitle")}
      {...layoutProps}
      error={error ?? layoutProps.error}
      actions={
        <Button size="sm" onClick={openCreate}>
          <Plus className="me-2 h-4 w-4" />
          {t("pages.modirpayamak.addPattern")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.patternsTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      <Card className="mb-4">
        <CardContent className="space-y-3 p-4">
          <div className="flex flex-wrap items-end gap-2">
            <div className="min-w-[14rem] flex-1">
              <Label>{t("pages.modirpayamak.filterByDomain")}</Label>
              {domains.length > 0 ? (
                <Select
                  value={registryFilter || "__all__"}
                  onValueChange={(v) => {
                    const next = v === "__all__" ? "" : v
                    setRegistryFilter(next)
                    void loadAllRegistry(next)
                  }}
                >
                  <SelectTrigger className="mt-1">
                    <SelectValue placeholder={t("pages.modirpayamak.allDomains")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__all__">{t("pages.modirpayamak.allDomains")}</SelectItem>
                    {domains.map((d) => (
                      <SelectItem key={d} value={d}>
                        {d}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <div className="mt-1 flex gap-2">
                  <Input
                    value={registryFilter}
                    onChange={(e) => setRegistryFilter(e.target.value)}
                    placeholder="example.com"
                  />
                  <Button type="button" variant="secondary" onClick={() => void loadAllRegistry(registryFilter)}>
                    {t("common.filter")}
                  </Button>
                </div>
              )}
            </div>
            <Button type="button" variant="outline" onClick={() => void loadAllRegistry(registryFilter)}>
              {t("common.refresh")}
            </Button>
          </div>
          <p className="text-sm font-medium">{t("pages.modirpayamak.registryAllTitle")}</p>
          {registryLoading ? (
            <TableListSkeleton rows={4} columns={5} />
          ) : allRegistry.length === 0 ? (
            <p className="text-muted-foreground text-sm">{t("pages.modirpayamak.registryEmpty")}</p>
          ) : (
            <div className="max-h-72 overflow-auto rounded border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.modirpayamak.domain")}</TableHead>
                    <TableHead>{t("pages.modirpayamak.patternScope")}</TableHead>
                    <TableHead>{t("pages.modirpayamak.patternEvent")}</TableHead>
                    <TableHead>{t("pages.modirpayamak.patternCode")}</TableHead>
                    <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                    <TableHead className="w-[90px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {allRegistry.map((r, i) => (
                    <TableRow key={`${r.domain}-${r.scope}-${r.event_key}-${i}`}>
                      <TableCell className="font-mono text-xs" dir="ltr">
                        {r.domain || "—"}
                      </TableCell>
                      <TableCell className="text-xs">{r.scope}</TableCell>
                      <TableCell className="text-xs">{eventLabel(t, r.event_key ?? "")}</TableCell>
                      <TableCell className="font-mono text-xs" dir="ltr">
                        {r.ippanel_code || "—"}
                      </TableCell>
                      <TableCell>
                        <SyncBadge status={r.sync_status} />
                      </TableCell>
                      <TableCell>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          disabled={attaching || !r.domain || !r.scope || !r.event_key}
                          onClick={() => {
                            void (async () => {
                              setAttaching(true)
                              const res = await detachModirPayamakPattern({
                                domain: r.domain || "",
                                scope: (r.scope as ModirPayamakPatternScope) || "order_customer",
                                event_key: r.event_key || "",
                              })
                              setAttaching(false)
                              if (
                                applyResponse(res, {
                                  successMessage: res.data?.message ?? t("pages.modirpayamak.patternDetached"),
                                })
                              ) {
                                void loadAllRegistry(registryFilter)
                              }
                            })()
                          }}
                        >
                          {t("pages.modirpayamak.detachPattern")}
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={5} />
          </CardContent>
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState icon={Code} message={t("pages.modirpayamak.patternsEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.patternCode")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.name")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.patternStatus")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.colDate")}</TableHead>
                  <TableHead className="w-[180px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => {
                  const code = edgeField(row, "code", "pattern_code", "id")
                  return (
                    <TableRow key={code !== "—" ? code : i}>
                      <TableCell className="font-mono">{code}</TableCell>
                      <TableCell className="max-w-xs truncate">
                        {edgeField(row, "title", "name", "description")}
                      </TableCell>
                      <TableCell>
                        <ModirPayamakStatusBadge
                          status={edgeField(row, "pattern_status", "status", "state")}
                        />
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {formatDateTime(edgeField(row, "created_at", "updated_at").replace(/-/g, "/").slice(0, 16))}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button type="button" variant="ghost" size="icon" onClick={() => void openDetail(row)}>
                            <Eye className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(row)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title={t("pages.modirpayamak.attachPatternToShop")}
                            onClick={() => void openAttach(row)}
                          >
                            <Link2 className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="ghost" size="icon" onClick={() => useInSend(row)}>
                            <Send className="h-4 w-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-destructive"
                            onClick={() => setDeleteCode(code !== "—" ? code : null)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <PmPagination
        page={page}
        totalPages={Math.max(1, page + (items.length >= 20 ? 1 : 0))}
        onPageChange={setPage}
        prevLabel={t("common.prevPage")}
        nextLabel={t("common.nextPage")}
        isRtl={isRtl}
      />

      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent side={isRtl ? "left" : "right"} className="overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>{edgeField(detailRow, "code", "pattern_code")}</SheetTitle>
          </SheetHeader>
          {detailRow ? (
            <div className="mt-4 space-y-3 text-sm">
              <p>
                <span className="text-muted-foreground">{t("pages.modirpayamak.name")}: </span>
                {edgeField(detailRow, "title", "name")}
              </p>
              <p>
                <span className="text-muted-foreground">{t("pages.modirpayamak.patternStatus")}: </span>
                <ModirPayamakStatusBadge status={edgeField(detailRow, "pattern_status", "status")} />
              </p>
              {edgeField(detailRow, "reject_text", "rejection_reason") !== "—" ? (
                <p>
                  <span className="text-muted-foreground">{t("pages.modirpayamak.rejectText")}: </span>
                  {edgeField(detailRow, "reject_text", "rejection_reason")}
                </p>
              ) : null}
              <div>
                <Label>{t("pages.modirpayamak.message")}</Label>
                <pre className="mt-1 whitespace-pre-wrap rounded bg-muted p-3 text-xs">
                  {edgeField(detailRow, "message", "text", "body", "pattern")}
                </pre>
              </div>
              <Button size="sm" asChild>
                <Link
                  to={`/admin/integrations/modirpayamak/send?pattern=${encodeURIComponent(edgeField(detailRow, "code", "pattern_code"))}&mode=pattern`}
                >
                  {t("pages.modirpayamak.useInSend")}
                </Link>
              </Button>
            </div>
          ) : null}
        </SheetContent>
      </Sheet>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editCode ? t("common.edit") : t("pages.modirpayamak.addPattern")}</DialogTitle>
          </DialogHeader>
          <ModirPayamakPatternForm value={form} onChange={setForm} disabled={saving} />
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => void save()} disabled={saving}>
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={attachOpen} onOpenChange={setAttachOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{t("pages.modirpayamak.attachPatternToShop")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t("pages.modirpayamak.patternCode")}</Label>
              <Input className="mt-1 font-mono" dir="ltr" value={attachCode} onChange={(e) => setAttachCode(e.target.value)} />
            </div>
            <div>
              <Label>{t("pages.modirpayamak.domain")}</Label>
              {domains.length > 0 ? (
                <Select
                  value={attachDomain}
                  onValueChange={(d) => {
                    setAttachDomain(d)
                    void loadDomainRegistry(d)
                  }}
                >
                  <SelectTrigger className="mt-1">
                    <SelectValue placeholder="example.com" />
                  </SelectTrigger>
                  <SelectContent>
                    {domains.map((d) => (
                      <SelectItem key={d} value={d}>
                        {d}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <Input
                  className="mt-1"
                  value={attachDomain}
                  onChange={(e) => setAttachDomain(e.target.value)}
                  onBlur={() => void loadDomainRegistry(attachDomain)}
                />
              )}
            </div>
            <div>
              <Label>{t("pages.modirpayamak.patternScope")}</Label>
              <Select
                value={attachScope}
                onValueChange={(v) => {
                  const scope = v as ModirPayamakPatternScope
                  setAttachScope(scope)
                  setAttachEvent(scope === "site" ? "otp_login" : "processing")
                }}
              >
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="order_customer">{t("pages.modirpayamak.scopeOrderCustomer")}</SelectItem>
                  <SelectItem value="order_admin">{t("pages.modirpayamak.scopeOrderAdmin")}</SelectItem>
                  <SelectItem value="site">{t("pages.modirpayamak.scopeSiteOtp")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>{t("pages.modirpayamak.patternEvent")}</Label>
              <Select value={attachEvent} onValueChange={setAttachEvent}>
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {eventOptions.map((ev) => (
                    <SelectItem key={ev} value={ev}>
                      {eventLabel(t, ev)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {domainRegistry.length > 0 ? (
              <div className="max-h-48 overflow-auto rounded border">
                <p className="border-b px-3 py-2 text-sm font-medium">{t("pages.modirpayamak.domainRegistry")}</p>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.modirpayamak.patternScope")}</TableHead>
                      <TableHead>{t("pages.modirpayamak.patternEvent")}</TableHead>
                      <TableHead>{t("pages.modirpayamak.patternCode")}</TableHead>
                      <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                      <TableHead className="w-[90px]">{t("common.actions")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {domainRegistry.map((r, i) => (
                      <TableRow key={`${r.scope}-${r.event_key}-${i}`}>
                        <TableCell className="text-xs">{r.scope}</TableCell>
                        <TableCell className="text-xs">{eventLabel(t, r.event_key ?? "")}</TableCell>
                        <TableCell className="font-mono text-xs" dir="ltr">
                          {r.ippanel_code || "—"}
                        </TableCell>
                        <TableCell>
                          <SyncBadge status={r.sync_status} />
                        </TableCell>
                        <TableCell>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={
                              attaching ||
                              !attachDomain.trim() ||
                              !r.scope ||
                              !r.event_key
                            }
                            onClick={() => {
                              void (async () => {
                                setAttaching(true)
                                const res = await detachModirPayamakPattern({
                                  domain: attachDomain.trim(),
                                  scope: (r.scope as ModirPayamakPatternScope) || "order_customer",
                                  event_key: r.event_key || "",
                                })
                                setAttaching(false)
                                if (
                                  applyResponse(res, {
                                    successMessage: res.data?.message ?? t("pages.modirpayamak.patternDetached"),
                                  })
                                ) {
                                  if (res.data?.registry) setDomainRegistry(res.data.registry)
                                  else void loadDomainRegistry(attachDomain.trim())
                                }
                              })()
                            }}
                          >
                            {t("pages.modirpayamak.detachPattern")}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setAttachOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="button" disabled={attaching} onClick={() => void submitAttach()}>
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteCode != null}
        onOpenChange={(open) => !open && setDeleteCode(null)}
        title={t("common.delete")}
        description={t("pages.modirpayamak.confirm.deletePattern")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
