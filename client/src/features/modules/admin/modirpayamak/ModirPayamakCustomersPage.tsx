import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  adjustModirPayamakBalance,
  getModirPayamakCustomers,
  type ModirPayamakAccount,
} from "@/api/modirpayamak"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { ExternalLink, Users } from "lucide-react"

export function ModirPayamakCustomersPage() {
  const { t, formatNumber, formatDateTime } = useLocale()
  const { layoutProps, setError, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const [accounts, setAccounts] = useState<ModirPayamakAccount[]>([])
  const [loading, setLoading] = useState(true)
  const [adjustDomain, setAdjustDomain] = useState("")
  const [adjustAmount, setAdjustAmount] = useState("")
  const [adjustNote, setAdjustNote] = useState("")

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getModirPayamakCustomers()
    if (res.success && res.data) {
      setAccounts(res.data.accounts ?? [])
    } else {
      setAccounts([])
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.loadError"))
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const adjust = async () => {
    if (!adjustDomain.trim()) {
      setError(t("pages.modirpayamak.domainRequired"))
      return
    }
    setError(null)
    const res = await adjustModirPayamakBalance(adjustDomain, parseFloat(adjustAmount) || 0, adjustNote)
    if (applyResponse(res, { successMessage: res.data?.message ?? t("common.saved") })) {
      setAdjustDomain("")
      setAdjustAmount("")
      setAdjustNote("")
      void load()
    }
  }

  return (
    <CrmPageLayout title={t("pages.modirpayamak.customersTitle")} {...layoutProps}>
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.customersTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      <Card className="max-w-lg">
        <CardContent className="space-y-3 pt-6">
          <Label>{t("pages.modirpayamak.adjustBalance")}</Label>
          <Input placeholder="example.com" value={adjustDomain} onChange={(e) => setAdjustDomain(e.target.value)} />
          <Input type="number" placeholder="10000" value={adjustAmount} onChange={(e) => setAdjustAmount(e.target.value)} />
          <Textarea rows={2} placeholder={t("pages.modirpayamak.adjustNote")} value={adjustNote} onChange={(e) => setAdjustNote(e.target.value)} />
          <Button type="button" onClick={() => void adjust()}>{t("common.save")}</Button>
        </CardContent>
      </Card>

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={6} />
          </CardContent>
        </Card>
      ) : accounts.length === 0 ? (
        <PmEmptyState icon={Users} message={t("pages.modirpayamak.customersEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.domain")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.balance")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.fromNumber")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.creditExpiry")}</TableHead>
                  <TableHead className="w-[80px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {accounts.map((a) => (
                  <TableRow key={a.id}>
                    <TableCell className="font-mono">{a.domain}</TableCell>
                    <TableCell>{formatNumber(a.balance)}</TableCell>
                    <TableCell dir="ltr" className="text-sm">{a.default_from || "—"}</TableCell>
                    <TableCell><ModirPayamakStatusBadge status={a.status} /></TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {a.expires_at ? formatDateTime(a.expires_at.replace(/-/g, "/").slice(0, 16)) : "—"}
                    </TableCell>
                    <TableCell>
                      <Button variant="ghost" size="icon" asChild title={t("pages.modirpayamak.viewReports")}>
                        <Link to={`/admin/integrations/modirpayamak/reports?domain=${encodeURIComponent(a.domain)}`}>
                          <ExternalLink className="h-4 w-4" />
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}
    </CrmPageLayout>
  )
}
