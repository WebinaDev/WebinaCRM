import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
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
  accountingPriceLists,
  accountingPriceListGet,
  accountingPriceListSave,
  accountingPriceListDelete,
  accountingPriceListItemsSave,
  accountingProductsList,
  type PriceList,
  type Product,
} from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { DatePicker } from "@/components/ui/date-picker"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"

export function PriceListsTab() {
  const { t, isRtl, formatNumber, formatDate } = useLocale()
  const [lists, setLists] = useState<PriceList[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [itemsDialogOpen, setItemsDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [activeListId, setActiveListId] = useState<number | null>(null)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    name: "",
    description: "",
    is_default: 0,
    valid_from: "",
    valid_to: "",
  })
  const [itemPrices, setItemPrices] = useState<Record<number, string>>({})

  const load = useCallback(async () => {
    setLoading(true)
    const [listRes, prodRes] = await Promise.all([
      accountingPriceLists(),
      accountingProductsList({ per_page: 500 }),
    ])
    if (listRes.success && listRes.data?.items) setLists(listRes.data.items)
    if (prodRes.success && prodRes.data?.items) setProducts(prodRes.data.items)
    setLoading(false)
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const openCreate = () => {
    setEditingId(null)
    setForm({ name: "", description: "", is_default: 0, valid_from: "", valid_to: "" })
    setDialogOpen(true)
  }

  const openEdit = (pl: PriceList) => {
    setEditingId(pl.id)
    setForm({
      name: pl.name,
      description: pl.description ?? "",
      is_default: pl.is_default,
      valid_from: pl.valid_from?.slice(0, 10) ?? "",
      valid_to: pl.valid_to?.slice(0, 10) ?? "",
    })
    setDialogOpen(true)
  }

  const handleSave = async () => {
    if (!form.name.trim()) return
    setSaving(true)
    const res = await accountingPriceListSave({
      id: editingId ?? undefined,
      name: form.name.trim(),
      description: form.description || undefined,
      is_default: form.is_default,
      valid_from: form.valid_from || undefined,
      valid_to: form.valid_to || undefined,
    })
    setSaving(false)
    if (res.success) {
      setDialogOpen(false)
      void load()
    }
  }

  const openItems = async (id: number) => {
    setActiveListId(id)
    const res = await accountingPriceListGet(id)
    const prices: Record<number, string> = {}
    if (res.success && res.data?.items) {
      Object.values(res.data.items).forEach((row) => {
        prices[row.product_id] = String(row.price)
      })
    }
    setItemPrices(prices)
    setItemsDialogOpen(true)
  }

  const saveItems = async () => {
    if (!activeListId) return
    setSaving(true)
    const items = Object.entries(itemPrices)
      .filter(([, price]) => price !== "" && Number(price) >= 0)
      .map(([productId, price]) => ({
        product_id: Number(productId),
        price: Number(price),
        min_quantity: 1,
      }))
    const res = await accountingPriceListItemsSave(activeListId, items)
    setSaving(false)
    if (res.success) {
      setItemsDialogOpen(false)
    }
  }

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle className="text-base">{t("pages.accounting.products.tabPriceLists")}</CardTitle>
        <Button size="sm" onClick={openCreate} className="gap-2">
          <Plus className="h-4 w-4" />
          {t("pages.accounting.products.priceListNew")}
        </Button>
      </CardHeader>
      <CardContent>
        {loading ? (

          <TableListSkeleton rows={8} columns={5} />

        ) : lists.length === 0 ? (
          <p className="text-muted-foreground text-sm">{t("common.noData")}</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t("common.name")}</TableHead>
                <TableHead>{t("pages.accounting.products.validFrom")}</TableHead>
                <TableHead>{t("pages.accounting.products.validTo")}</TableHead>
                <TableHead className="w-[120px]">{t("common.actions")}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {lists.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-medium">
                    {row.name}
                    {row.is_default ? ` (${t("pages.accounting.products.defaultList")})` : ""}
                  </TableCell>
                  <TableCell>{row.valid_from ? formatDate(row.valid_from) : "—"}</TableCell>
                  <TableCell>{row.valid_to ? formatDate(row.valid_to) : "—"}</TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      <Button variant="ghost" size="icon" onClick={() => void openItems(row.id)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon" onClick={() => openEdit(row)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon" onClick={() => setDeleteId(row.id)}>
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.products.priceListEdit") : t("pages.accounting.products.priceListNew")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-2">
            <div className="space-y-2">
              <Label>{t("common.name")}</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.cashAccounts.توضیحات")}</Label>
              <Input
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.validFrom")}</Label>
                <DatePicker value={form.valid_from} onChange={(v) => setForm((f) => ({ ...f, valid_from: v }))} />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.validTo")}</Label>
                <DatePicker value={form.valid_to} onChange={(v) => setForm((f) => ({ ...f, valid_to: v }))} />
              </div>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={!!form.is_default}
                onChange={(e) => setForm((f) => ({ ...f, is_default: e.target.checked ? 1 : 0 }))}
              />
              {t("pages.accounting.products.defaultList")}
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => void handleSave()} disabled={saving || !form.name.trim()}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={itemsDialogOpen} onOpenChange={setItemsDialogOpen}>
        <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{t("pages.accounting.products.priceListItems")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2 max-h-[50vh] overflow-y-auto">
            {products.map((p) => (
              <div key={p.id} className="flex items-center gap-2 text-sm">
                <span className="flex-1 truncate">{p.name}</span>
                <Input
                  type="number"
                  className="w-28"
                  min={0}
                  step={0.01}
                  value={itemPrices[p.id] ?? ""}
                  onChange={(e) => setItemPrices((prev) => ({ ...prev, [p.id]: e.target.value }))}
                  placeholder={formatNumber(0)}
                />
              </div>
            ))}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setItemsDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => void saveItems()} disabled={saving}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {t("common.save")}
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
          const res = await accountingPriceListDelete(deleteId)
          if (res.success) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </Card>
  )
}
