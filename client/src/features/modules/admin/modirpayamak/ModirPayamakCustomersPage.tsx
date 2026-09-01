import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
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
import {
  adjustModirPayamakBalance,
  ensureModirPayamakCustomersFromLicenses,
  getModirPayamakCustomerLedger,
  getModirPayamakCustomers,
  type ModirPayamakAccount,
  type ModirPayamakLedgerRow,
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
import { ExternalLink, MinusCircle, PlusCircle, ScrollText, Users } from "lucide-react"

type CreditDialog = {
  domain: string
  mode: "credit" | "debit"
} | null

export function ModirPayamakCustomersPage() {
  const { t, formatNumber, formatDateTime } = useLocale()
  const { layoutProps, setError, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const [accounts, setAccounts] = useState<ModirPayamakAccount[]>([])
  const [loading, setLoading] = useState(true)
  const [syncing, setSyncing] = useState(false)
  const [creditDlg, setCreditDlg] = useState<CreditDialog>(null)
  const [amount, setAmount] = useState("")
  const [note, setNote] = useState("")
  const [saving, setSaving] = useState(false)
  const [ledgerDomain, setLedgerDomain] = useState<string | null>(null)
  const [ledger, setLedger] = useState<ModirPayamakLedgerRow[]>([])
  const [ledgerLoading, setLedgerLoading] = useState(false)

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

  const openCredit = (domain: string, mode: "credit" | "debit") => {
    setCreditDlg({ domain, mode })
    setAmount("")
    setNote("")
  }

  const submitCredit = async () => {
    if (!creditDlg) return
    const raw = Math.abs(parseFloat(amount) || 0)
    if (raw <= 0) {
      setError(t("pages.modirpayamak.amountRequired"))
      return
    }
    setSaving(true)
    setError(null)
    const signed = creditDlg.mode === "debit" ? -raw : raw
    const res = await adjustModirPayamakBalance(creditDlg.domain, signed, note)
    setSaving(false)
    if (applyResponse(res, { successMessage: res.data?.message ?? t("common.saved") })) {
      setCreditDlg(null)
      void load()
    }
  }

  const syncFromLicenses = async () => {
    setSyncing(true)
    setError(null)
    const res = await ensureModirPayamakCustomersFromLicenses()
    setSyncing(false)
    if (applyResponse(res, { successMessage: res.data?.message ?? t("pages.modirpayamak.ensureCustomersDone") })) {
      if (res.data?.accounts) setAccounts(res.data.accounts)
      else void load()
    }
  }

  const openLedger = async (domain: string) => {
    setLedgerDomain(domain)
    setLedgerLoading(true)
    const res = await getModirPayamakCustomerLedger(domain)
    setLedgerLoading(false)
    if (res.success && res.data) {
      setLedger(res.data.ledger ?? [])
    } else {
      setLedger([])
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.loadError"))
    }
  }

  const lineLabel = (a: ModirPayamakAccount) => {
    const nums = a.numbers ?? []
    if (!nums.length) return "—"
    return nums.map((n) => `${n.role}: ${n.number}`).join(" · ")
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.customersTitle")}
      {...layoutProps}
      actions={
        <Button size="sm" variant="outline" disabled={syncing} onClick={() => void syncFromLicenses()}>
          {syncing ? t("common.loading") : t("pages.modirpayamak.ensureFromLicenses")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.customersTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={7} />
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
                  <TableHead>{t("pages.modirpayamak.attachedLines")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.creditExpiry")}</TableHead>
                  <TableHead className="w-[200px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {accounts.map((a) => (
                  <TableRow key={a.id}>
                    <TableCell className="font-mono">{a.domain}</TableCell>
                    <TableCell>{formatNumber(a.balance)}</TableCell>
                    <TableCell className="max-w-xs truncate text-sm" dir="ltr" title={lineLabel(a)}>
                      {lineLabel(a)}
                    </TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={a.status} />
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {a.expires_at ? formatDateTime(a.expires_at.replace(/-/g, "/").slice(0, 16)) : "—"}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          title={t("pages.modirpayamak.creditAction")}
                          onClick={() => openCredit(a.domain, "credit")}
                        >
                          <PlusCircle className="h-4 w-4 text-success" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          title={t("pages.modirpayamak.debitAction")}
                          onClick={() => openCredit(a.domain, "debit")}
                        >
                          <MinusCircle className="h-4 w-4 text-destructive" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          title={t("pages.modirpayamak.ledgerTitle")}
                          onClick={() => void openLedger(a.domain)}
                        >
                          <ScrollText className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" asChild title={t("pages.modirpayamak.viewReports")}>
                          <Link to={`/admin/integrations/modirpayamak/reports?domain=${encodeURIComponent(a.domain)}`}>
                            <ExternalLink className="h-4 w-4" />
                          </Link>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <Dialog open={!!creditDlg} onOpenChange={(o) => !o && setCreditDlg(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {creditDlg?.mode === "debit"
                ? t("pages.modirpayamak.debitAction")
                : t("pages.modirpayamak.creditAction")}
              {creditDlg ? ` — ${creditDlg.domain}` : ""}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t("pages.modirpayamak.amount")}</Label>
              <Input
                className="mt-1"
                type="number"
                min={1}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </div>
            <div>
              <Label>{t("pages.modirpayamak.adjustNote")}</Label>
              <Textarea className="mt-1" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCreditDlg(null)}>
              {t("common.cancel")}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void submitCredit()}>
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Sheet open={!!ledgerDomain} onOpenChange={(o) => !o && setLedgerDomain(null)}>
        <SheetContent className="w-full sm:max-w-lg overflow-y-auto">
          <SheetHeader>
            <SheetTitle>
              {t("pages.modirpayamak.ledgerTitle")}
              {ledgerDomain ? ` — ${ledgerDomain}` : ""}
            </SheetTitle>
          </SheetHeader>
          {ledgerLoading ? (
            <p className="mt-4 text-sm text-muted-foreground">{t("common.loading")}</p>
          ) : ledger.length === 0 ? (
            <p className="mt-4 text-sm text-muted-foreground">{t("pages.modirpayamak.ledgerEmpty")}</p>
          ) : (
            <ul className="mt-4 space-y-2 text-sm">
              {ledger.map((row) => (
                <li key={row.id} className="rounded border px-3 py-2">
                  <div className="flex justify-between gap-2">
                    <span className="font-medium">{row.type}</span>
                    <span dir="ltr">{formatNumber(row.amount)}</span>
                  </div>
                  <div className="text-muted-foreground flex justify-between gap-2 text-xs">
                    <span>{row.note || "—"}</span>
                    <span>{row.created_at ? formatDateTime(row.created_at.replace(/-/g, "/").slice(0, 16)) : ""}</span>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </SheetContent>
      </Sheet>
    </CrmPageLayout>
  )
}
