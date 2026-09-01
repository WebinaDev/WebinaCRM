import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Badge } from "@/components/ui/badge"
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import {
  attachModirPayamakNumber,
  detachModirPayamakNumber,
  getModirPayamakCustomers,
  type ModirPayamakDomainNumber,
} from "@/api/modirpayamak"
import { edgeField, edgeListNumbers } from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { useModirPayamakEdge } from "./hooks/useModirPayamakEdge"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { Copy, Link2, Phone, Unlink } from "lucide-react"
import { toast } from "sonner"

type AttachmentMap = Record<string, ModirPayamakDomainNumber[]>

function roleLabel(t: (key: string) => string, role: string): string {
  if (role === "service") return t("pages.modirpayamak.roleService")
  if (role === "personal" || role === "marketing") return t("pages.modirpayamak.rolePersonal")
  return role
}

export function ModirPayamakNumbersPage() {
  const { t } = useLocale()
  const { layoutProps, setSuccess, setError, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const loader = useCallback(() => edgeListNumbers(), [])
  const { items, raw, loading } = useModirPayamakEdge(loader)
  const [copied, setCopied] = useState<string | null>(null)
  const [attachOpen, setAttachOpen] = useState(false)
  const [detachOpen, setDetachOpen] = useState(false)
  const [attachNumber, setAttachNumber] = useState("")
  const [attachDomain, setAttachDomain] = useState("")
  const [attachRole, setAttachRole] = useState<"service" | "personal">("service")
  const [detachDomain, setDetachDomain] = useState("")
  const [detachRole, setDetachRole] = useState<"service" | "personal">("service")
  const [detachNumber, setDetachNumber] = useState("")
  const [domains, setDomains] = useState<string[]>([])
  const [attachmentsByNumber, setAttachmentsByNumber] = useState<AttachmentMap>({})
  const [saving, setSaving] = useState(false)

  const refreshAttachments = useCallback(async () => {
    const res = await getModirPayamakCustomers()
    if (!res.success || !res.data?.accounts) {
      setAttachmentsByNumber({})
      return
    }
    const map: AttachmentMap = {}
    for (const account of res.data.accounts) {
      const domain = account.domain
      if (!domain) continue
      for (const n of account.numbers ?? []) {
        const num = (n.number ?? "").trim()
        if (!num) continue
        const row: ModirPayamakDomainNumber = { ...n, domain }
        if (!map[num]) map[num] = []
        map[num].push(row)
      }
    }
    setAttachmentsByNumber(map)
    setDomains(res.data.accounts.map((a) => a.domain).filter(Boolean))
  }, [])

  useEffect(() => {
    void refreshAttachments()
  }, [refreshAttachments, items])

  const copyNumber = async (num: string) => {
    try {
      await navigator.clipboard.writeText(num)
      setCopied(num)
      setSuccess(t("pages.modirpayamak.copied"))
      toast.success(t("pages.modirpayamak.copied"))
      setTimeout(() => setCopied(null), 2000)
    } catch {
      /* ignore */
    }
  }

  const openAttach = async (num: string) => {
    setAttachNumber(num)
    setAttachRole("service")
    setAttachDomain("")
    setAttachOpen(true)
    const res = await getModirPayamakCustomers()
    if (res.success && res.data?.accounts) {
      setDomains(res.data.accounts.map((a) => a.domain).filter(Boolean))
    }
  }

  const openDetach = async (num?: string) => {
    setDetachDomain("")
    setDetachRole("service")
    setDetachNumber(num ?? "")
    setDetachOpen(true)
    const res = await getModirPayamakCustomers()
    if (res.success && res.data?.accounts) {
      setDomains(res.data.accounts.map((a) => a.domain).filter(Boolean))
    }
  }

  const submitDetach = async () => {
    if (!detachDomain.trim()) {
      setError(t("pages.modirpayamak.domainRequired"))
      return
    }
    setSaving(true)
    const res = await detachModirPayamakNumber(detachDomain.trim(), detachRole, detachNumber.trim() || undefined)
    setSaving(false)
    if (applyResponse(res, { successMessage: res.data?.message ?? t("pages.modirpayamak.numberDetached") })) {
      setDetachOpen(false)
      void refreshAttachments()
    }
  }

  const submitAttach = async () => {
    if (!attachDomain.trim() || !attachNumber.trim()) {
      setError(t("pages.modirpayamak.domainRequired"))
      return
    }
    setSaving(true)
    const res = await attachModirPayamakNumber({
      domain: attachDomain.trim(),
      number: attachNumber.trim(),
      role: attachRole,
    })
    setSaving(false)
    if (applyResponse(res, { successMessage: res.data?.message ?? t("pages.modirpayamak.numberAttached") })) {
      setAttachOpen(false)
      void refreshAttachments()
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.numbersTitle")}
      {...layoutProps}
      actions={
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" type="button" onClick={() => void openDetach()}>
            <Unlink className="me-1 h-4 w-4" />
            {t("pages.modirpayamak.detachLine")}
          </Button>
          <Button variant="outline" size="sm" asChild>
            <Link to="/admin/integrations/modirpayamak/customers">{t("pages.modirpayamak.customersTitle")}</Link>
          </Button>
        </div>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.numbersTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />
      <p className="text-sm text-muted-foreground mb-2">{t("pages.modirpayamak.numbersPoolHint")}</p>

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={6} columns={5} />
          </CardContent>
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState icon={Phone} message={t("pages.modirpayamak.numbersEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.fromNumber")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.lineType")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.attachedDomains")}</TableHead>
                  <TableHead className="w-[120px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => {
                  const num = edgeField(row, "number", "line", "from_number", "sender")
                  const attached = attachmentsByNumber[num] ?? []
                  return (
                    <TableRow key={`${num}-${i}`}>
                      <TableCell dir="ltr" className="font-mono">
                        {num}
                      </TableCell>
                      <TableCell>{edgeField(row, "type", "line_type", "title", "name")}</TableCell>
                      <TableCell>
                        <ModirPayamakStatusBadge status={edgeField(row, "status", "state")} />
                      </TableCell>
                      <TableCell>
                        {attached.length === 0 ? (
                          <span className="text-muted-foreground text-xs">—</span>
                        ) : (
                          <div className="flex max-w-md flex-wrap gap-1">
                            {attached.map((a) => (
                              <Badge key={`${a.domain}-${a.role}-${a.number}`} variant="secondary" className="font-normal">
                                <span dir="ltr">{a.domain}</span>
                                <span className="text-muted-foreground ms-1">
                                  ({roleLabel(t, String(a.role ?? ""))})
                                </span>
                              </Badge>
                            ))}
                          </div>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => void copyNumber(num)}
                            title={t("pages.modirpayamak.copyLine")}
                          >
                            <Copy className={`h-4 w-4 ${copied === num ? "text-success" : ""}`} />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => void openAttach(num)}
                            title={t("pages.modirpayamak.attachToDomain")}
                          >
                            <Link2 className="h-4 w-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => void openDetach(num)}
                            title={t("pages.modirpayamak.detachLine")}
                          >
                            <Unlink className="h-4 w-4" />
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

      <Dialog open={attachOpen} onOpenChange={setAttachOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("pages.modirpayamak.attachToDomain")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t("pages.modirpayamak.fromNumber")}</Label>
              <Input className="mt-1 font-mono" dir="ltr" value={attachNumber} readOnly />
            </div>
            <div>
              <Label>{t("pages.modirpayamak.domain")}</Label>
              {domains.length > 0 ? (
                <Select value={attachDomain} onValueChange={setAttachDomain}>
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
                  placeholder="example.com"
                  value={attachDomain}
                  onChange={(e) => setAttachDomain(e.target.value)}
                />
              )}
            </div>
            <div>
              <Label>{t("pages.modirpayamak.lineRole")}</Label>
              <Select value={attachRole} onValueChange={(v) => setAttachRole(v as "service" | "personal")}>
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="service">{t("pages.modirpayamak.roleService")}</SelectItem>
                  <SelectItem value="personal">{t("pages.modirpayamak.rolePersonal")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setAttachOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void submitAttach()}>
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={detachOpen} onOpenChange={setDetachOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("pages.modirpayamak.detachLine")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t("pages.modirpayamak.domain")}</Label>
              {domains.length > 0 ? (
                <Select value={detachDomain} onValueChange={setDetachDomain}>
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
                  placeholder="example.com"
                  value={detachDomain}
                  onChange={(e) => setDetachDomain(e.target.value)}
                />
              )}
            </div>
            <div>
              <Label>{t("pages.modirpayamak.lineNumber")}</Label>
              <Input
                className="mt-1 font-mono"
                dir="ltr"
                value={detachNumber}
                onChange={(e) => setDetachNumber(e.target.value)}
                placeholder="+98…"
              />
            </div>
            <div>
              <Label>{t("pages.modirpayamak.lineRole")}</Label>
              <Select value={detachRole} onValueChange={(v) => setDetachRole(v as "service" | "personal")}>
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="service">{t("pages.modirpayamak.roleService")}</SelectItem>
                  <SelectItem value="personal">{t("pages.modirpayamak.rolePersonal")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDetachOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void submitDetach()}>
              {t("pages.modirpayamak.detachLine")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
