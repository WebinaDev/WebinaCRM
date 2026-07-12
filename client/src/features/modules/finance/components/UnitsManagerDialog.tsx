import { useCallback, useEffect, useState } from "react"
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  accountingUnitsList,
  accountingUnitSave,
  accountingUnitDelete,
  type Unit,
} from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { Loader2, Plus, Trash2 } from "lucide-react"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function UnitsManagerDialog({ open, onOpenChange }: Props) {
  const { t, isRtl } = useLocale()
  const [items, setItems] = useState<Unit[]>([])
  const [loading, setLoading] = useState(false)
  const [name, setName] = useState("")
  const [symbol, setSymbol] = useState("")
  const [saving, setSaving] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    const res = await accountingUnitsList()
    if (res.success && res.data?.items) setItems(res.data.items)
    setLoading(false)
  }, [])

  useEffect(() => {
    if (open) void load()
  }, [open, load])

  const handleAdd = async () => {
    if (!name.trim()) return
    setSaving(true)
    const res = await accountingUnitSave({ name: name.trim(), symbol: symbol || undefined, is_main: 1 })
    setSaving(false)
    if (res.success) {
      setName("")
      setSymbol("")
      void load()
    }
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"} className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t("pages.accounting.products.tabUnits")}</DialogTitle>
          </DialogHeader>
          <div className="grid grid-cols-3 gap-2 items-end">
            <div className="col-span-2 space-y-1">
              <Label>{t("common.name")}</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>{t("pages.accounting.products.unitSymbol")}</Label>
              <Input value={symbol} onChange={(e) => setSymbol(e.target.value)} />
            </div>
            <Button onClick={() => void handleAdd()} disabled={saving || !name.trim()}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
            </Button>
          </div>
          {loading ? (
            <p className="text-sm text-muted-foreground">{t("common.loading")}</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.accounting.products.unitSymbol")}</TableHead>
                  <TableHead className="w-12" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((u) => (
                  <TableRow key={u.id}>
                    <TableCell>{u.name}</TableCell>
                    <TableCell>{u.symbol ?? "—"}</TableCell>
                    <TableCell>
                      <Button variant="ghost" size="icon" onClick={() => setDeleteId(u.id)}>
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              {t("common.cancel")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <PmConfirmDialog
        open={deleteId !== null}
        onOpenChange={() => setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.accounting.products.آیا_از_حذف_این_مورد_اطمینان_دارید؟")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingUnitDelete(deleteId)
          if (res.success) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </>
  )
}
