import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
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
import {
  deleteModirPayamakPackage,
  getModirPayamakPackages,
  getModirPayamakTariffs,
  saveModirPayamakPackage,
  type ModirPayamakPackage,
  type ModirPayamakTariff,
} from "@/api/modirpayamak"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { Package, Pencil, Plus, Trash2 } from "lucide-react"

export function ModirPayamakPackagesPage() {
  const { t, isRtl, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const [packages, setPackages] = useState<ModirPayamakPackage[]>([])
  const [tariffs, setTariffs] = useState<ModirPayamakTariff[]>([])
  const [taxPercent, setTaxPercent] = useState(10)
  const [surcharge, setSurcharge] = useState(40)
  const [tariffId, setTariffId] = useState("")
  const [tariffParts, setTariffParts] = useState("1")
  const [tariffEncoding, setTariffEncoding] = useState<"fa" | "la">("fa")
  const [loading, setLoading] = useState(true)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState({ name: "", amount: "0", bonus: "0", sort: "0", status: "active" })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const [pkgRes, tarRes] = await Promise.all([getModirPayamakPackages(), getModirPayamakTariffs()])
    if (pkgRes.success && pkgRes.data) {
      setPackages(pkgRes.data.packages ?? [])
    } else {
      setPackages([])
      setError(getAjaxMessage(pkgRes) ?? t("pages.modirpayamak.loadError"))
    }
    if (tarRes.success && tarRes.data) {
      setTariffs(tarRes.data.tariffs ?? [])
      setTaxPercent(Number(tarRes.data.tax_percent ?? 10))
      setSurcharge(Number(tarRes.data.surcharge_rial ?? 40))
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const openCreate = () => {
    setEditId(null)
    setForm({ name: "", amount: "0", bonus: "0", sort: "0", status: "active" })
    setTariffId("")
    setTariffParts("1")
    setTariffEncoding("fa")
    setDialogOpen(true)
  }

  const applyTariffAmount = () => {
    const row = tariffs.find((x) => String(x.id) === tariffId)
    if (!row) return
    const parts = Math.max(1, parseInt(tariffParts, 10) || 1)
    const rate = tariffEncoding === "la" ? Number(row.rate_la) : Number(row.rate_fa)
    const rial = (rate * (1 + taxPercent / 100) + surcharge) * parts
    const toman = Math.round((rial / 10) * 10000) / 10000
    setForm((f) => ({ ...f, amount: String(toman) }))
  }

  const openEdit = (p: ModirPayamakPackage) => {
    setEditId(p.id)
    setForm({
      name: p.name,
      amount: String(p.amount),
      bonus: String(p.bonus),
      sort: String(p.sort),
      status: p.status,
    })
    setDialogOpen(true)
  }

  const save = async () => {
    setError(null)
    const res = await saveModirPayamakPackage({
      id: editId ?? undefined,
      name: form.name,
      amount: parseFloat(form.amount) || 0,
      bonus: parseFloat(form.bonus) || 0,
      sort: parseInt(form.sort, 10) || 0,
      status: form.status,
    })
    if (res.success) {
      setDialogOpen(false)
      void load()
    } else {
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.saveError"))
    }
  }

  const confirmDelete = async () => {
    if (deleteId == null) return
    setDeleting(true)
    const res = await deleteModirPayamakPackage(deleteId)
    setDeleting(false)
    if (res.success) {
      setDeleteId(null)
      void load()
    } else {
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.saveError"))
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.packagesTitle")}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={openCreate}>
          <Plus className="me-2 h-4 w-4" />
          {t("pages.modirpayamak.addPackage")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.packagesTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={6} columns={5} />
          </CardContent>
        </Card>
      ) : packages.length === 0 ? (
        <PmEmptyState icon={Package} message={t("pages.modirpayamak.packagesEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.name")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.amount")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.bonus")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.sort")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {packages.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>{p.name}</TableCell>
                    <TableCell>{formatNumber(p.amount)}</TableCell>
                    <TableCell>{formatNumber(p.bonus)}</TableCell>
                    <TableCell>{p.sort}</TableCell>
                    <TableCell><ModirPayamakStatusBadge status={p.status} /></TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(p)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button type="button" variant="ghost" size="icon" className="text-destructive" onClick={() => setDeleteId(p.id)}>
                          <Trash2 className="h-4 w-4" />
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

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editId ? t("common.edit") : t("pages.modirpayamak.addPackage")}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2 sm:col-span-2">
              <Label>{t("pages.modirpayamak.name")}</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div className="space-y-2 sm:col-span-2 rounded-md border border-dashed p-3">
              <Label className="mb-2 block">{t("pages.modirpayamak.useTariffForAmount")}</Label>
              <div className="grid gap-2 sm:grid-cols-2">
                <Select value={tariffId || undefined} onValueChange={setTariffId}>
                  <SelectTrigger>
                    <SelectValue placeholder={t("pages.modirpayamak.tariffsTitle")} />
                  </SelectTrigger>
                  <SelectContent>
                    {tariffs.map((tr) => (
                      <SelectItem key={tr.id} value={String(tr.id)}>
                        {tr.line_type} /{" "}
                        {tr.operator === "mci"
                          ? t("pages.modirpayamak.operatorMci")
                          : t("pages.modirpayamak.operatorOther")}{" "}
                        ({formatNumber(tr.rate_fa)} / {formatNumber(tr.rate_la)})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select
                  value={tariffEncoding}
                  onValueChange={(v) => setTariffEncoding(v as "fa" | "la")}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="fa">{t("pages.modirpayamak.encodingFa")}</SelectItem>
                    <SelectItem value="la">{t("pages.modirpayamak.encodingLa")}</SelectItem>
                  </SelectContent>
                </Select>
                <div className="space-y-1">
                  <Label className="text-xs">{t("pages.modirpayamak.tariffParts")}</Label>
                  <Input
                    value={tariffParts}
                    onChange={(e) => setTariffParts(e.target.value)}
                    dir="ltr"
                  />
                </div>
                <div className="flex items-end">
                  <Button type="button" variant="secondary" className="w-full" onClick={applyTariffAmount}>
                    {t("pages.modirpayamak.applyTariffAmount")}
                  </Button>
                </div>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.amount")}</Label>
              <Input value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.bonus")}</Label>
              <Input value={form.bonus} onChange={(e) => setForm((f) => ({ ...f, bonus: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.sort")}</Label>
              <Input value={form.sort} onChange={(e) => setForm((f) => ({ ...f, sort: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.status")}</Label>
              <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">{t("pages.modirpayamak.statusActive")}</SelectItem>
                  <SelectItem value="inactive">{t("pages.modirpayamak.statusInactive")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{t("common.cancel")}</Button>
            <Button onClick={() => void save()}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.modirpayamak.confirm.deletePackage")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  )
}
