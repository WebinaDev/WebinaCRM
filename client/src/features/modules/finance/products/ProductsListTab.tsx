import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
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
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  accountingProductsList,
  accountingProductGet,
  accountingProductSave,
  accountingProductDelete,
  accountingProductCategories,
  accountingUnitsList,
} from "@/api/accounting"
import type { Product, ProductCategory, Unit } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import type { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"

const PER_PAGE = 15

type ProductsListTabProps = {
  applyResponse: ReturnType<typeof useAccountingFeedback>["applyResponse"]
}

export function ProductsListTab({ applyResponse }: ProductsListTabProps) {
  const { t, isRtl, formatNumber } = useLocale()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [items, setItems] = useState<Product[]>([])
  const [categories, setCategories] = useState<ProductCategory[]>([])
  const [units, setUnits] = useState<Unit[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<Partial<Product> & { code: string; name: string }>({
    code: "",
    name: "",
    main_unit_id: 0,
    is_active: 1,
  })
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    const params: Record<string, string | number> = { page: currentPage, per_page: PER_PAGE }
    if (debouncedSearch.trim()) params.search = debouncedSearch.trim()
    const res = await accountingProductsList(params)
    if (res.success && res.data) {
      setItems(res.data.items ?? [])
      setTotalPages(Math.max(1, Math.ceil((res.data.total ?? 0) / PER_PAGE)))
    }
    setLoading(false)
  }, [currentPage, debouncedSearch, setTotalPages])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    resetPage()
  }, [debouncedSearch, resetPage])

  useEffect(() => {
    accountingProductCategories().then((res) => {
      if (res.success && res.data?.items) setCategories(res.data.items)
    })
    accountingUnitsList().then((res) => {
      if (res.success && res.data?.items) setUnits(res.data.items)
    })
  }, [])

  const openCreate = () => {
    setEditingId(null)
    setForm({
      code: "",
      name: "",
      main_unit_id: units[0]?.id ?? 0,
      is_active: 1,
    })
    setDialogOpen(true)
  }

  const openEdit = async (id: number) => {
    const res = await accountingProductGet(id)
    if (res.success && res.data?.product) {
      setForm(res.data.product)
      setEditingId(id)
      setDialogOpen(true)
    }
  }

  const handleSave = async () => {
    if (!form.code?.trim() || !form.name?.trim()) return
    setSaving(true)
    const res = await accountingProductSave({ ...form, id: editingId ?? undefined })
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
      setDialogOpen(false)
      void load()
    }
  }

  return (
    <>
      <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
        <CardHeader className="space-y-4 text-start">
          <div
            className={cn(
              "flex flex-wrap items-center justify-between gap-2",
              isRtl && "flex-row-reverse",
            )}
          >
            <CardTitle className="text-base">{t("pages.accounting.products.لیست_کالا_و_خدمات")}</CardTitle>
            <Button onClick={openCreate} className="gap-2">
              <Plus className="h-4 w-4" />
              {t("pages.accounting.products.کالا_جدید")}
            </Button>
          </div>
          <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
            <Input
              placeholder={t("pages.accounting.products.جستجو_نام،_کد،_بارکد")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="max-w-xs"
            />
          </PmFilterBar>
        </CardHeader>
        <CardContent>
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <p className="text-muted-foreground text-start">{t("pages.accounting.products.کالا_یا_خدماتی_یافت_نشد")}</p>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.accounting.products.کد")}</TableHead>
                    <TableHead>{t("common.name")}</TableHead>
                    <TableHead>{t("pages.accounting.products.قیمت_خرید")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell className="font-mono">{row.code}</TableCell>
                      <TableCell className="font-medium">{row.name}</TableCell>
                      <TableCell>
                        {row.purchase_price != null ? formatNumber(row.purchase_price) : "—"}
                      </TableCell>
                      <TableCell>
                        {row.is_active ? (
                          <Badge>{t("common.active")}</Badge>
                        ) : (
                          <Badge variant="secondary">{t("common.inactive")}</Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon" onClick={() => void openEdit(row.id)}>
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
              <PmPagination
                page={currentPage}
                totalPages={totalPages}
                onPageChange={setCurrentPage}
                prevLabel={t("common.prevPage")}
                nextLabel={t("common.nextPage")}
                isRtl={isRtl}
              />
            </>
          )}
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.products.ویرایش_کالا") : t("pages.accounting.products.کالا_جدید")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.کد")}</Label>
                <Input
                  value={form.code ?? ""}
                  onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("common.name")}</Label>
                <Input
                  value={form.name ?? ""}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.واحد_اصلی")}</Label>
                <Select
                  value={String(form.main_unit_id ?? 0)}
                  onValueChange={(v) => setForm((f) => ({ ...f, main_unit_id: Number(v) }))}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {units.map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>
                        {u.name} {u.symbol ? `(${u.symbol})` : ""}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.دستهبندی")}</Label>
                <Select
                  value={form.category_id ? String(form.category_id) : "0"}
                  onValueChange={(v) =>
                    setForm((f) => ({ ...f, category_id: v === "0" ? null : Number(v) }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">{t("pages.accounting.persons.بدون_دسته")}</SelectItem>
                    {categories.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.قیمت_خرید")}</Label>
                <Input
                  type="number"
                  min={0}
                  step={0.01}
                  value={form.purchase_price ?? ""}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      purchase_price: e.target.value ? Number(e.target.value) : null,
                    }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.بارکد")}</Label>
                <Input
                  value={form.barcode ?? ""}
                  onChange={(e) => setForm((f) => ({ ...f, barcode: e.target.value }))}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.taxSales")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.tax_rate_sales ?? ""}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      tax_rate_sales: e.target.value ? Number(e.target.value) : null,
                    }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.taxPurchase")}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.tax_rate_purchase ?? ""}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      tax_rate_purchase: e.target.value ? Number(e.target.value) : null,
                    }))
                  }
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.products.reorderPoint")}</Label>
              <Input
                type="number"
                min={0}
                value={form.reorder_point ?? ""}
                onChange={(e) =>
                  setForm((f) => ({
                    ...f,
                    reorder_point: e.target.value ? Number(e.target.value) : null,
                  }))
                }
              />
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={!!form.inventory_controlled}
                onChange={(e) =>
                  setForm((f) => ({ ...f, inventory_controlled: e.target.checked ? 1 : 0 }))
                }
              />
              {t("pages.accounting.products.کنترل_موجودی")}
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={!!form.is_active}
                onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked ? 1 : 0 }))}
              />
              {t("common.active")}
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => void handleSave()} disabled={saving || !form.code?.trim() || !form.name?.trim()}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId !== null}
        onOpenChange={() => setDeleteId(null)}
        title={t("pages.accounting.products.حذف_کالا_خدمات")}
        description={t("pages.accounting.products.آیا_از_حذف_این_مورد_اطمینان_دارید؟")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingProductDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.shared.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </>
  )
}
