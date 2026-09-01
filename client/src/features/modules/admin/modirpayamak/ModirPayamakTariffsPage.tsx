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
  deleteModirPayamakTariff,
  getModirPayamakTariffs,
  saveModirPayamakTariff,
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
import { Pencil, Plus, Tags, Trash2 } from "lucide-react"

function emptyForm() {
  return {
    line_type: "1000",
    operator: "mci",
    rate_fa: "1936",
    rate_la: "4840",
    sort: "0",
    status: "active",
  }
}

export function ModirPayamakTariffsPage() {
  const { t, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const [tariffs, setTariffs] = useState<ModirPayamakTariff[]>([])
  const [taxPercent, setTaxPercent] = useState(10)
  const [surcharge, setSurcharge] = useState(40)
  const [loading, setLoading] = useState(true)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState(emptyForm())

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getModirPayamakTariffs()
    if (res.success && res.data) {
      setTariffs(res.data.tariffs ?? [])
      setTaxPercent(Number(res.data.tax_percent ?? 10))
      setSurcharge(Number(res.data.surcharge_rial ?? 40))
    } else {
      setTariffs([])
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.loadError"))
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const openCreate = () => {
    setEditId(null)
    setForm(emptyForm())
    setDialogOpen(true)
  }

  const openEdit = (row: ModirPayamakTariff) => {
    setEditId(row.id)
    setForm({
      line_type: row.line_type,
      operator: row.operator === "mci" ? "mci" : "other",
      rate_fa: String(row.rate_fa),
      rate_la: String(row.rate_la),
      sort: String(row.sort ?? 0),
      status: row.status || "active",
    })
    setDialogOpen(true)
  }

  const save = async () => {
    const res = await saveModirPayamakTariff({
      id: editId ?? undefined,
      line_type: form.line_type.trim(),
      operator: form.operator,
      rate_fa: parseFloat(form.rate_fa) || 0,
      rate_la: parseFloat(form.rate_la) || 0,
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
    const res = await deleteModirPayamakTariff(deleteId)
    setDeleting(false)
    if (res.success) {
      setDeleteId(null)
      void load()
    } else {
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.saveError"))
    }
  }

  const billable = (rate: number) => rate * (1 + taxPercent / 100) + surcharge

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.tariffsTitle")}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={openCreate}>
          <Plus className="me-2 h-4 w-4" />
          {t("pages.modirpayamak.addTariff")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.tariffsTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />
      <p className="mb-3 text-sm text-muted-foreground">
        {t("pages.modirpayamak.tariffsHint", {
          tax: String(taxPercent),
          surcharge: formatNumber(surcharge),
        })}
      </p>

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={7} />
          </CardContent>
        </Card>
      ) : tariffs.length === 0 ? (
        <PmEmptyState icon={Tags} message={t("pages.modirpayamak.tariffsEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.lineType")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.operator")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.rateFa")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.rateLa")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.billableFa")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {tariffs.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-mono">{row.line_type}</TableCell>
                    <TableCell>
                      {row.operator === "mci"
                        ? t("pages.modirpayamak.operatorMci")
                        : t("pages.modirpayamak.operatorOther")}
                    </TableCell>
                    <TableCell dir="ltr">{formatNumber(row.rate_fa)}</TableCell>
                    <TableCell dir="ltr">{formatNumber(row.rate_la)}</TableCell>
                    <TableCell dir="ltr">{formatNumber(billable(Number(row.rate_fa)))}</TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={row.status} />
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(row)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="text-destructive"
                          onClick={() => setDeleteId(row.id)}
                        >
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
            <DialogTitle>{editId ? t("common.edit") : t("pages.modirpayamak.addTariff")}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.lineType")}</Label>
              <Input
                value={form.line_type}
                onChange={(e) => setForm((f) => ({ ...f, line_type: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.operator")}</Label>
              <Select value={form.operator} onValueChange={(v) => setForm((f) => ({ ...f, operator: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="mci">{t("pages.modirpayamak.operatorMci")}</SelectItem>
                  <SelectItem value="other">{t("pages.modirpayamak.operatorOther")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.rateFa")}</Label>
              <Input
                dir="ltr"
                value={form.rate_fa}
                onChange={(e) => setForm((f) => ({ ...f, rate_fa: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.rateLa")}</Label>
              <Input
                dir="ltr"
                value={form.rate_la}
                onChange={(e) => setForm((f) => ({ ...f, rate_la: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.sort")}</Label>
              <Input value={form.sort} onChange={(e) => setForm((f) => ({ ...f, sort: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.status")}</Label>
              <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">{t("pages.modirpayamak.statusActive")}</SelectItem>
                  <SelectItem value="inactive">{t("pages.modirpayamak.statusInactive")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="button" onClick={() => void save()}>
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.modirpayamak.confirmDeleteTariff")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
      />
    </CrmPageLayout>
  )
}
