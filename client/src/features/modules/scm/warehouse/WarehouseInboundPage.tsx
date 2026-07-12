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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Badge } from "@/components/ui/badge"
import type { StatusBadgeInfo } from "@/lib/badge-variants"
import {
  listWarehouses,
  listWarehouseInbound,
  getWarehouseInbound,
  createWarehouseInbound,
  postWarehouseInbound,
  listProducts,
  unwrapListPayload,
} from "@/api/warehouses"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import { Plus, Loader2, Eye, Check } from "lucide-react"

const PER_PAGE = 15


interface InboundItem {
  id: number
  product_id: number
  product_name: string
  quantity_ordered: number
  quantity_received: number
  unit_name: string
  unit_price: number
  location: string
}

interface WarehouseOption {
  id: number
  name: string
}

interface ProductOption {
  id: number
  name: string
}

interface Inbound {
  id: number
  warehouse_id: number
  inbound_no: string
  reference_doc: string
  status: string
  created_date: string
  posted_date: string
  notes: string
  total_quantity: number
  total_amount: number
  items?: InboundItem[]
}

export function WarehouseInboundPage() {
  const { t, isRtl, formatDate, formatNumber } = useLocale()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [items, setItems] = useState<Inbound[]>([])
  const [warehouses, setWarehouses] = useState<WarehouseOption[]>([])
  const [products, setProducts] = useState<ProductOption[]>([])
  const [loading, setLoading] = useState(true)
  const [warehousesReady, setWarehousesReady] = useState(false)
  const [selectedWarehouse, setSelectedWarehouse] = useState<string>("")
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [postConfirmId, setPostConfirmId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [detailsOpen, setDetailsOpen] = useState(false)
  const [selectedInbound, setSelectedInbound] = useState<Inbound | null>(null)
  const [saving, setSaving] = useState(false)
  const [formItems, setFormItems] = useState<Partial<InboundItem>[]>([
    { product_id: 0, quantity_ordered: 0, unit_price: 0, location: "" },
  ])
  const [form, setForm] = useState<{ warehouse_id: string; reference_doc: string; notes: string }>({
    warehouse_id: "",
    reference_doc: "",
    notes: "",
  })

  const fetchWarehouses = useCallback(async () => {
    try {
      const response = await listWarehouses({
        per_page: 100,
      })
      const data = unwrapListPayload<WarehouseOption>(response.data)
      setWarehouses(data)
      if (data.length > 0) {
        setSelectedWarehouse(data[0].id.toString())
      }
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setWarehousesReady(true)
    }
  }, [t])

  const fetchInbounds = useCallback(async () => {
    if (!warehousesReady) return
    if (!selectedWarehouse) {
      setLoading(false)
      setItems([])
      setTotalPages(1)
      return
    }

    setLoading(true)
    try {
      const params: Record<string, string | number> = {
        warehouse_id: selectedWarehouse,
        page: currentPage,
        per_page: PER_PAGE,
      }
      if (debouncedSearch) params.search = debouncedSearch

      const response = await listWarehouseInbound(params as Record<string, string | number>)
      const data = unwrapListPayload<Inbound>(response.data)
      setItems(data)
      const total = response.total || 0
      setTotalPages(Math.max(1, Math.ceil(total / PER_PAGE)))
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setLoading(false)
    }
  }, [warehousesReady, selectedWarehouse, debouncedSearch, currentPage, setTotalPages, t])

  useEffect(() => {
    fetchWarehouses()
    const fetchProd = async () => {
      try {
        const response = await listProducts({ per_page: 1000 })
        const data = unwrapListPayload<ProductOption>(response.data)
        setProducts(data)
      } catch {
        setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
      }
    }
    fetchProd()
  }, [fetchWarehouses])

  useEffect(() => {
    resetPage()
  }, [selectedWarehouse, debouncedSearch, resetPage])

  useEffect(() => {
    fetchInbounds()
  }, [fetchInbounds])

  const handleCreateInbound = async () => {
    setError(null)
    setSuccess(null)
    if (!form.warehouse_id) {
      setError(t("pages.accounting.shared.selectWarehouse"))
      return
    }

    if (formItems.some((i) => !i.product_id || !i.quantity_ordered)) {
      setError(t("pages.accounting.shared.fillRequiredFields"))
      return
    }

    setSaving(true)
    try {
      await createWarehouseInbound({
        warehouse_id: form.warehouse_id,
        reference_doc: form.reference_doc,
        notes: form.notes,
        items: JSON.stringify(formItems),
      })

      setDialogOpen(false)
      setForm({ warehouse_id: "", reference_doc: "", notes: "" })
      setFormItems([
        { product_id: 0, quantity_ordered: 0, unit_price: 0, location: "" },
      ])
      setSuccess(t("pages.accounting.shared.saved"))
      await fetchInbounds()
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setSaving(false)
    }
  }

  const handlePostInbound = async (id: number) => {
    setError(null)
    setSuccess(null)
    try {
      await postWarehouseInbound(id)
      setSuccess(t("pages.accounting.warehouseInbound.confirm.confirmInbound"))
      await fetchInbounds()
      setDetailsOpen(false)
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    }
  }

  const handleViewDetails = async (id: number) => {
    try {
      const response = await getWarehouseInbound(id)
      if (response.data && typeof response.data === 'object' && 'id' in response.data) {
        setSelectedInbound(response.data as Inbound)
        setDetailsOpen(true)
      }
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    }
  }

  const getStatusBadge = (status: string): StatusBadgeInfo => {
    const statuses: Record<string, StatusBadgeInfo> = {
      draft: { label: t("pages.accounting.warehouseAudit.پیشنویس"), color: "secondary" },
      received: { label: t("pages.accounting.receipts.دریافت"), color: "default" },
      posted: { label: t("pages.accounting.warehouseAudit.ثبتشده"), color: "default" },
    }
    return statuses[status] ?? { label: status, color: "outline" }
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.warehouseInbound.رسید_انبار")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <Button onClick={() => setDialogOpen(true)}>
          <Plus className="w-4 h-4 ml-2" />
          {t("pages.accounting.warehouseInbound.رسید_جدید")}
        </Button>
      }
    >
      <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
          <div className="flex gap-4 flex-wrap">
            {selectedWarehouse && (
              <Select value={selectedWarehouse} onValueChange={setSelectedWarehouse}>
                <SelectTrigger className="w-48 text-start">
                  <SelectValue placeholder={t("pages.accounting.warehouseAudit.انتخاب_انبار")} />
                </SelectTrigger>
                <SelectContent>
                  {warehouses.map((w) => (
                    <SelectItem key={w.id} value={w.id.toString()}>
                      {w.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
            <Input
              placeholder={t("pages.accounting.warehouseAudit.جستجو")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="text-start"
              dir={isRtl ? "rtl" : "ltr"}
            />
          </div>
      </PmFilterBar>          {(!warehousesReady || loading) ? (
            <Card><CardContent className="p-0"><TableListSkeleton rows={8} columns={6} /></CardContent></Card>
          ) : !selectedWarehouse ? (
        <Card>
          <CardContent className="py-8 text-center text-muted-foreground">
            {t("pages.accounting.shared.selectWarehouse")}
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="text-start">{t("pages.accounting.invoices.شماره")}</TableHead>
                  <TableHead className="text-start">{t("pages.accounting.warehouseInbound.مرجع")}</TableHead>
                  <TableHead className="text-center">{t("pages.accounting.invoices.تعداد")}</TableHead>
                  <TableHead className="text-start">{t("common.date")}</TableHead>
                  <TableHead className="text-center">{t("common.status")}</TableHead>
                  <TableHead className="text-center">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                      {t("pages.accounting.warehouseInbound.رسیدی_یافت_نشد")}
                    </TableCell>
                  </TableRow>
                ) : (
                  items.map((inbound) => {
                    const status = getStatusBadge(inbound.status)
                    return (
                      <TableRow key={inbound.id}>
                        <TableCell className="font-semibold">{inbound.inbound_no}</TableCell>
                        <TableCell>{inbound.reference_doc}</TableCell>
                        <TableCell className="text-center">
                          {formatNumber(inbound.total_quantity)}
                        </TableCell>
                        <TableCell>
                          {formatDate(inbound.created_date)}
                        </TableCell>
                        <TableCell className="text-center">
                          <Badge variant={status.color}>{status.label}</Badge>
                        </TableCell>
                        <TableCell className="text-center">
                          <div className="flex justify-center gap-2">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleViewDetails(inbound.id)}
                            >
                              <Eye className="w-4 h-4" />
                            </Button>
                            {inbound.status === "draft" && (
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setPostConfirmId(inbound.id)}
                              >
                                <Check className="w-4 h-4 text-green-600" />
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <PmPagination
        page={currentPage}
        totalPages={totalPages}
        onPageChange={setCurrentPage}
        prevLabel={t("common.prevPage")}
        nextLabel={t("common.nextPage")}
        isRtl={isRtl}
      />

      <PmConfirmDialog
        open={!!postConfirmId}
        onOpenChange={() => setPostConfirmId(null)}
        title={t("pages.accounting.warehouseInbound.confirm.confirmInbound")}
        description={t("pages.accounting.warehouseInbound.confirm.confirmInbound")}
        confirmLabel={t("common.confirm")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (postConfirmId) await handlePostInbound(postConfirmId)
          setPostConfirmId(null)
        }}
      />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"} className="w-full max-h-screen overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t("pages.accounting.warehouseInbound.رسید_انبار_جدید")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>{t("pages.accounting.warehouseAudit.انبار")}</Label>
              <Select value={form.warehouse_id} onValueChange={(value) => setForm({ ...form, warehouse_id: value })}>
                <SelectTrigger className="text-start">
                  <SelectValue placeholder={t("pages.accounting.warehouseAudit.انتخاب_انبار")} />
                </SelectTrigger>
                <SelectContent>
                  {warehouses.map((w) => (
                    <SelectItem key={w.id} value={w.id.toString()}>
                      {w.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>{t("pages.accounting.warehouseInbound.مرجع_سند")}</Label>
              <Input
                value={form.reference_doc}
                onChange={(e) => setForm({ ...form, reference_doc: e.target.value })}
                placeholder={t("pages.accounting.warehouseInbound.مثلاً_PO001")}
                dir={isRtl ? "rtl" : "ltr"}
              />
            </div>
            <div>
              <Label>{t("common.description")}</Label>
              <textarea
                value={form.notes}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                placeholder={t("pages.accounting.warehouseInbound.توضیحات_رسید")}
                className="w-full border rounded-md p-2 text-start"
                dir={isRtl ? "rtl" : "ltr"}
                rows={2}
              />
            </div>

            <div className="space-y-2">
              <Label>{t("pages.accounting.warehouseInbound.ردیفهای_رسید")}</Label>
              {formItems.map((item, idx) => (
                <div key={idx} className="grid grid-cols-4 gap-2 p-2 border rounded">
                  <Select value={((item.product_id) || 0).toString()} onValueChange={(value) => {
                    const newItems = [...formItems]
                    newItems[idx].product_id = parseInt(value)
                    setFormItems(newItems)
                  }}>
                    <SelectTrigger className="text-start">
                      <SelectValue placeholder={t("pages.accounting.warehouseAudit.محصول")} />
                    </SelectTrigger>
                    <SelectContent>
                      {products.map((p) => (
                        <SelectItem key={p.id} value={p.id.toString()}>
                          {p.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Input
                    type="number"
                    placeholder={t("pages.accounting.invoices.تعداد")}
                    value={String(item.quantity_ordered || "")}
                    onChange={(e) => {
                      const newItems = [...formItems]
                      newItems[idx] = { ...newItems[idx], quantity_ordered: parseInt(e.target.value) || 0 }
                      setFormItems(newItems)
                    }}
                    className="text-start"
                  />
                  <Input
                    type="number"
                    placeholder={t("pages.accounting.warehouseInbound.قیمت")}
                    value={String(item.unit_price || "")}
                    onChange={(e) => {
                      const newItems = [...formItems]
                      newItems[idx] = { ...newItems[idx], unit_price: parseFloat(e.target.value) || 0 }
                      setFormItems(newItems)
                    }}
                    className="text-start"
                  />
                  <Input
                    placeholder={t("pages.accounting.warehouseInbound.مکان")}
                    value={item.location || ""}
                    onChange={(e) => {
                      const newItems = [...formItems]
                      newItems[idx].location = e.target.value
                      setFormItems(newItems)
                    }}
                    className="text-start"
                  />
                </div>
              ))}
              <Button
                variant="outline"
                onClick={() =>
                  setFormItems([...formItems, { product_id: 0, quantity_ordered: 0, unit_price: 0, location: "" }])
                }
              >
                {t("pages.accounting.shared.addRow")}
              </Button>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={handleCreateInbound} disabled={saving}>
              {saving && <Loader2 className="w-4 h-4 ml-2 animate-spin" />}
              {t("pages.accounting.shared.create")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={detailsOpen} onOpenChange={setDetailsOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"} className="max-w-2xl">
          <DialogHeader>
<DialogTitle>{t("pages.accounting.shared.detailsInbound", { no: selectedInbound?.inbound_no })}</DialogTitle>
          </DialogHeader>
          {selectedInbound && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label className="text-muted-foreground">{t("pages.accounting.invoices.شماره")}</Label>
                  <div className="font-bold">{selectedInbound.inbound_no}</div>
                </div>
                <div>
                  <Label className="text-muted-foreground">{t("common.status")}</Label>
                  <div>
                    <Badge variant={getStatusBadge(selectedInbound.status).color}>
                      {getStatusBadge(selectedInbound.status).label}
                    </Badge>
                  </div>
                </div>
              </div>

              {selectedInbound.items && selectedInbound.items.length > 0 && (
                <div>
                  <Label className="text-muted-foreground">{t("pages.accounting.invoices.ردیفها")}</Label>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="text-start">{t("pages.accounting.warehouseInbound.کالا")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.invoices.تعداد")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.warehouseInbound.قیمت")}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {selectedInbound.items.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell>{item.product_name}</TableCell>
                          <TableCell className="text-center">
                            {formatNumber(item.quantity_ordered)}
                          </TableCell>
                          <TableCell className="text-center">
                            {formatNumber(item.unit_price)}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}

              <DialogFooter>
                {selectedInbound.status === "draft" && (
                  <Button onClick={() => setPostConfirmId(selectedInbound.id)}>
                    <Check className="w-4 h-4 ml-2" />
                    {t("pages.accounting.shared.confirmInbound")}
                  </Button>
                )}
              </DialogFooter>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </AccountingPageLayout>
  )
}
