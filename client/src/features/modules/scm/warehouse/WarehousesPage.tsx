import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useState, useEffect, useCallback } from "react"
import { Card, CardContent } from "@/components/ui/card"
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
import { Checkbox } from "@/components/ui/checkbox"
import {
  listWarehouses,
  createWarehouse,
  updateWarehouse,
  deleteWarehouse,
  type Warehouse,
} from "@/api/warehouses"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"

const PER_PAGE = 15

export function WarehousesPage() {
  const { t, isRtl } = useLocale()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [items, setItems] = useState<Warehouse[]>([])
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [form, setForm] = useState({
    name: "",
    code: "",
    description: "",
    location: "",
    is_default: false,
    is_active: true,
  })

  const fetchWarehouses = useCallback(async () => {
    setLoading(true)
    try {
      const response = await listWarehouses({
        search: debouncedSearch || "",
        page: currentPage,
        per_page: PER_PAGE,
      })
      const data = response.data?.items ?? []
      setItems(data)
      const total = response.data?.total ?? data.length
      setTotalPages(Math.max(1, Math.ceil(total / PER_PAGE)))
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setLoading(false)
    }
  }, [debouncedSearch, currentPage, setTotalPages, t])

  useEffect(() => {
    void fetchWarehouses()
  }, [fetchWarehouses])

  useEffect(() => {
    resetPage()
  }, [debouncedSearch, resetPage])

  const handleOpenDialog = (warehouse?: Warehouse) => {
    if (warehouse) {
      setEditingId(warehouse.id)
      setForm({
        name: warehouse.name,
        code: warehouse.code,
        description: warehouse.description,
        location: warehouse.location,
        is_default: warehouse.is_default,
        is_active: warehouse.is_active,
      })
    } else {
      setEditingId(null)
      setForm({
        name: "",
        code: "",
        description: "",
        location: "",
        is_default: false,
        is_active: true,
      })
    }
    setDialogOpen(true)
  }

  const handleSave = async () => {
    if (!form.name || !form.code) {
      setError(t("pages.accounting.shared.toast.warehouseNameCodeRequired"))
      return
    }
    setSaving(true)
    setError(null)
    setSuccess(null)
    try {
      if (editingId) {
        await updateWarehouse({ id: editingId, ...form })
        setSuccess(t("pages.accounting.shared.toast.warehouseUpdated"))
      } else {
        await createWarehouse(form)
        setSuccess(t("pages.accounting.shared.toast.warehouseCreated"))
      }
      setDialogOpen(false)
      await fetchWarehouses()
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.warehouses.انبارها")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <Button onClick={() => handleOpenDialog()}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.warehouses.انبار_جدید")}
        </Button>
      }
    >
      <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
        <Input
          placeholder={t("pages.accounting.warehouses.جستجو_در_نام_یا_کد")}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
      </PmFilterBar>

      <Card>
        <CardContent className="pt-6">
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.accounting.accountingReports.کد")}</TableHead>
                  <TableHead>{t("pages.accounting.warehouseInbound.مکان")}</TableHead>
                  <TableHead>{t("pages.accounting.warehouses.پیشفرض")}</TableHead>
                  <TableHead>{t("common.status")}</TableHead>
                  <TableHead>{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                      {t("pages.accounting.warehouses.انباری_یافت_نشد")}
                    </TableCell>
                  </TableRow>
                ) : (
                  items.map((warehouse) => (
                    <TableRow key={warehouse.id}>
                      <TableCell className="font-semibold">{warehouse.name}</TableCell>
                      <TableCell>{warehouse.code}</TableCell>
                      <TableCell>{warehouse.location}</TableCell>
                      <TableCell>
                        {warehouse.is_default ? (
                          <Badge>{t("pages.accounting.warehouses.پیشفرض")}</Badge>
                        ) : (
                          <Badge variant="outline">{t("pages.accounting.warehouses.عادی")}</Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge variant={warehouse.is_active ? "default" : "destructive"}>
                          {warehouse.is_active ? t("common.active") : t("common.inactive")}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button variant="ghost" size="sm" onClick={() => handleOpenDialog(warehouse)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="sm" onClick={() => setDeleteId(warehouse.id)}>
                            <Trash2 className="h-4 w-4 text-destructive" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          )}
          <PmPagination
            page={currentPage}
            totalPages={totalPages}
            onPageChange={setCurrentPage}
            prevLabel={t("common.prevPage")}
            nextLabel={t("common.nextPage")}
            isRtl={isRtl}
          />
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.warehouses.ویرایش_انبار") : t("pages.accounting.warehouses.انبار_جدید")}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="name">{t("pages.accounting.cashAccounts.نام_1")}</Label>
              <Input
                id="name"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder={t("pages.accounting.warehouses.نام_انبار")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="code">{t("pages.accounting.products.کد")}</Label>
              <Input
                id="code"
                value={form.code}
                onChange={(e) => setForm({ ...form, code: e.target.value })}
                placeholder={t("pages.accounting.warehouses.کد_انبار_مثلاً_WH01")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="location">{t("pages.accounting.warehouseInbound.مکان")}</Label>
              <Input
                id="location"
                value={form.location}
                onChange={(e) => setForm({ ...form, location: e.target.value })}
                placeholder={t("pages.accounting.warehouses.مکان_انبار")}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="description">{t("common.description")}</Label>
              <textarea
                id="description"
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                placeholder={t("pages.accounting.warehouses.توضیحات_انبار")}
                className="w-full border rounded-md p-2 min-h-[80px]"
                rows={3}
              />
            </div>
            <div className="flex gap-6">
              <div className="flex items-center gap-2">
                <Checkbox
                  id="is_default"
                  checked={form.is_default}
                  onCheckedChange={(checked) => setForm({ ...form, is_default: checked === true })}
                />
                <Label htmlFor="is_default" className="cursor-pointer">
                  {t("pages.accounting.warehouses.انبار_پیشفرض")}
                </Label>
              </div>
              <div className="flex items-center gap-2">
                <Checkbox
                  id="is_active"
                  checked={form.is_active}
                  onCheckedChange={(checked) => setForm({ ...form, is_active: checked === true })}
                />
                <Label htmlFor="is_active" className="cursor-pointer">
                  {t("common.active")}
                </Label>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => void handleSave()} disabled={saving}>
              {saving && <Loader2 className="h-4 w-4 ml-2 animate-spin" />}
              {editingId ? t("pages.accounting.shared.update") : t("pages.accounting.shared.create")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={!!deleteId}
        onOpenChange={() => setDeleteId(null)}
        title={t("pages.accounting.warehouses.حذف_انبار")}
        description={t("pages.accounting.warehouses.حذف_تأیید")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          setError(null)
          setSuccess(null)
          try {
            await deleteWarehouse(deleteId)
            setSuccess(t("pages.accounting.shared.toast.warehouseDeleted"))
            setDeleteId(null)
            await fetchWarehouses()
          } catch {
            setError(t("pages.accounting.shared.toast.warehouseDeleteFailed"))
          }
        }}
      />
    </AccountingPageLayout>
  )
}
