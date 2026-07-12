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
  listWarehouseAudits,
  getWarehouseAudit,
  createWarehouseAudit,
  recordWarehouseAuditLine,
  completeWarehouseAudit,
  postWarehouseAudit,
  unwrapListPayload,
} from "@/api/warehouses"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import { Plus, Loader2, Eye, Check, Clipboard } from "lucide-react"

const PER_PAGE = 15


interface AuditItem {
  id: number
  product_id: number
  product_name: string
  system_quantity: number
  physical_quantity: number
  variance_quantity: number
  variance_amount: number
  variance_type: string
  unit_name: string
}

interface WarehouseOption {
  id: number
  name: string
}

interface Audit {
  id: number
  warehouse_id: number
  audit_no: string
  status: string
  created_date: string
  completed_date: string
  posted_date: string
  total_variance: number
  notes: string
  items?: AuditItem[]
}

export function WarehouseAuditPage() {
  const { t, isRtl, formatDate, formatNumber } = useLocale()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [items, setItems] = useState<Audit[]>([])
  const [warehouses, setWarehouses] = useState<WarehouseOption[]>([])
  const [loading, setLoading] = useState(true)
  const [warehousesReady, setWarehousesReady] = useState(false)
  const [selectedWarehouse, setSelectedWarehouse] = useState<string>("")
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [completeConfirmId, setCompleteConfirmId] = useState<number | null>(null)
  const [postConfirmId, setPostConfirmId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [detailsOpen, setDetailsOpen] = useState(false)
  const [selectedAudit, setSelectedAudit] = useState<Audit | null>(null)
  const [saving, setSaving] = useState(false)
  const [auditItems, setAuditItems] = useState<Partial<AuditItem>[]>([])
  const [form, setForm] = useState<{ warehouse_id: string; notes: string }>({
    warehouse_id: "",
    notes: "",
  })
  const [step, setStep] = useState<"create" | "count">("create")

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

  const fetchAudits = useCallback(async () => {
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

      const response = await listWarehouseAudits(params)
      const data = unwrapListPayload<Audit>(response.data)
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
  }, [fetchWarehouses])

  useEffect(() => {
    resetPage()
  }, [selectedWarehouse, debouncedSearch, resetPage])

  useEffect(() => {
    fetchAudits()
  }, [fetchAudits])

  const handleCreateAudit = async () => {
    setError(null)
    setSuccess(null)
    if (!form.warehouse_id) {
      setError(t("pages.accounting.shared.selectWarehouse"))
      return
    }

    setSaving(true)
    try {
      const response = await createWarehouseAudit({
        warehouse_id: form.warehouse_id,
        notes: form.notes,
      })

      if (response.data && typeof response.data === 'object' && 'id' in response.data) {
        const audit = response.data as Audit
        setSelectedAudit(audit)
        setStep("count")
        setAuditItems(
          audit.items?.map((item: AuditItem) => ({
            ...item,
            physical_quantity: 0,
          })) || []
        )
      }
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setSaving(false)
    }
  }

  const handleRecordCount = async (auditId: number, items: Partial<AuditItem>[]) => {
    setError(null)
    setSuccess(null)
    if (items.some((i) => i.physical_quantity === undefined || i.physical_quantity === null)) {
      setError(t("pages.accounting.warehouseAudit.تمام_تعدادها_را_وارد_کنید"))
      return
    }

    setSaving(true)
    try {
      await recordWarehouseAuditLine({
        audit_id: auditId,
        items: JSON.stringify(items),
      })

      setDialogOpen(false)
      setStep("create")
      setForm({ warehouse_id: "", notes: "" })
      setAuditItems([])
      setSuccess(t("pages.accounting.shared.saved"))
      await fetchAudits()
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setSaving(false)
    }
  }

  const handleCompleteAudit = async (id: number) => {
    setError(null)
    setSuccess(null)
    try {
      await completeWarehouseAudit(id)
      setSuccess(t("pages.accounting.warehouseAudit.confirm.countComplete"))
      await fetchAudits()
      setDetailsOpen(false)
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    }
  }

  const handlePostAudit = async (id: number) => {
    setError(null)
    setSuccess(null)
    try {
      await postWarehouseAudit(id)
      setSuccess(t("pages.accounting.warehouseAudit.confirm.postDifferences"))
      await fetchAudits()
      setDetailsOpen(false)
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    }
  }

  const handleViewDetails = async (id: number) => {
    try {
      const response = await getWarehouseAudit(id)
      if (response.data && typeof response.data === 'object' && 'id' in response.data) {
        setSelectedAudit(response.data as Audit)
        setDetailsOpen(true)
      }
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    }
  }

  const getStatusBadge = (status: string): StatusBadgeInfo => {
    const statuses: Record<string, StatusBadgeInfo> = {
      draft: { label: t("pages.accounting.warehouseAudit.پیشنویس"), color: "secondary" },
      in_progress: { label: t("pages.accounting.warehouseAudit.درحال_شمارش"), color: "outline" },
      completed: { label: t("pages.accounting.warehouseAudit.تکمیل"), color: "default" },
      posted: { label: t("pages.accounting.warehouseAudit.ثبتشده"), color: "default" },
    }
    return statuses[status] ?? { label: status, color: "outline" }
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.warehouseAudit.انبارگردانی")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        <Button onClick={() => setDialogOpen(true)}>
          <Plus className="w-4 h-4 ml-2" />
          {t("pages.accounting.warehouseAudit.انبارگردانی_جدید")}
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
                  <TableHead className="text-start">{t("common.date")}</TableHead>
                  <TableHead className="text-center">{t("pages.accounting.warehouseAudit.اختلاف")}</TableHead>
                  <TableHead className="text-center">{t("common.status")}</TableHead>
                  <TableHead className="text-center">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                      {t("pages.accounting.warehouseAudit.انبارگردانی_یافت_نشد")}
                    </TableCell>
                  </TableRow>
                ) : (
                  items.map((audit) => {
                    const status = getStatusBadge(audit.status)
                    return (
                      <TableRow key={audit.id}>
                        <TableCell className="font-semibold">{audit.audit_no}</TableCell>
                        <TableCell>
                          {formatDate(audit.created_date)}
                        </TableCell>
                        <TableCell className="text-center">
                          {audit.total_variance !== 0 && (
                            <Badge
                              variant={audit.total_variance > 0 ? "default" : "destructive"}
                            >
                              {audit.total_variance > 0 ? "+" : ""}
                              {formatNumber(audit.total_variance)}
                            </Badge>
                          )}
                          {audit.total_variance === 0 && (
                            <span className="text-muted-foreground/70">{t("pages.accounting.warehouseAudit.بدون_اختلاف")}</span>
                          )}
                        </TableCell>
                        <TableCell className="text-center">
                          <Badge variant={status.color}>{status.label}</Badge>
                        </TableCell>
                        <TableCell className="text-center">
                          <div className="flex justify-center gap-2">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleViewDetails(audit.id)}
                            >
                              <Eye className="w-4 h-4" />
                            </Button>
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
        open={!!completeConfirmId}
        onOpenChange={() => setCompleteConfirmId(null)}
        title={t("pages.accounting.warehouseAudit.confirm.countComplete")}
        description={t("pages.accounting.warehouseAudit.confirm.countComplete")}
        confirmLabel={t("common.confirm")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (completeConfirmId) await handleCompleteAudit(completeConfirmId)
          setCompleteConfirmId(null)
        }}
      />

      <PmConfirmDialog
        open={!!postConfirmId}
        onOpenChange={() => setPostConfirmId(null)}
        title={t("pages.accounting.warehouseAudit.confirm.postDifferences")}
        description={t("pages.accounting.warehouseAudit.confirm.postDifferences")}
        confirmLabel={t("common.confirm")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (postConfirmId) await handlePostAudit(postConfirmId)
          setPostConfirmId(null)
        }}
      />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"} className="max-w-2xl max-h-screen overflow-y-auto">
          <DialogHeader>
            <DialogTitle>
              {step === "create" ? t("pages.accounting.warehouseAudit.انبارگردانی_جدید") : t("pages.accounting.warehouseAudit.شمارش_موجودی")}
            </DialogTitle>
          </DialogHeader>

          {step === "create" ? (
            <div className="space-y-4">
              <div>
                <Label>{t("pages.accounting.warehouseAudit.انبار")}</Label>
                <Select
                  value={form.warehouse_id}
                  onValueChange={(value) => setForm({ ...form, warehouse_id: value })}
                >
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
                <Label>{t("common.description")}</Label>
                <textarea
                  value={form.notes}
                  onChange={(e) => setForm({ ...form, notes: e.target.value })}
                  placeholder={t("pages.accounting.warehouseAudit.توضیحات_انبارگردانی")}
                  className="w-full border rounded-md p-2 text-start"
                  dir={isRtl ? "rtl" : "ltr"}
                  rows={3}
                />
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setDialogOpen(false)}>
                  {t("common.cancel")}
                </Button>
                <Button onClick={handleCreateAudit} disabled={saving}>
                  {saving && <Loader2 className="w-4 h-4 ml-2 animate-spin" />}
                  {t("pages.accounting.warehouseAudit.شروع_انبارگردانی")}
                </Button>
              </DialogFooter>
            </div>
          ) : (
            <div className="space-y-4">
              {selectedAudit && (
                <>
                  <div className="bg-blue-50 border border-blue-200 rounded-md p-3">
                    <div className="text-sm text-blue-800">
                      <div className="font-semibold">{t("pages.accounting.warehouseAudit.شماره_انبارگردانی", { no: selectedAudit.audit_no })}</div>
                      <div>{t("pages.accounting.warehouseAudit.لطفاً_موجودی_فیزیکی_هر_محصول_را_وارد_کنی")}</div>
                    </div>
                  </div>

                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="text-start">{t("pages.accounting.warehouseAudit.محصول")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.warehouseAudit.سیستم")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.warehouseAudit.فیزیکی")}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {auditItems.map((item, idx) => (
                        <TableRow key={idx}>
                          <TableCell>{item.product_name}</TableCell>
                          <TableCell className="text-center">
                            {formatNumber(item.system_quantity ?? 0)}
                          </TableCell>
                          <TableCell className="text-center">
                            <Input
                              type="number"
                              value={String(item.physical_quantity || "")}
                              onChange={(e) => {
                                const newItems = [...auditItems]
                                newItems[idx] = { ...newItems[idx], physical_quantity: parseInt(e.target.value) || 0 }
                                setAuditItems(newItems)
                              }}
                              className="w-24 text-center"
                            />
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>

                  <DialogFooter>
                    <Button variant="outline" onClick={() => setDialogOpen(false)}>
                      {t("common.cancel")}
                    </Button>
                    <Button
                      onClick={() => handleRecordCount(selectedAudit.id, auditItems)}
                      disabled={saving}
                    >
                      {saving && <Loader2 className="w-4 h-4 ml-2 animate-spin" />}
                      {t("pages.accounting.warehouseAudit.ثبت_شمارش")}
                    </Button>
                  </DialogFooter>
                </>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={detailsOpen} onOpenChange={setDetailsOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"} className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>{t("pages.accounting.warehouseAudit.جزئیات_انبارگردانی", { no: selectedAudit?.audit_no })}</DialogTitle>
          </DialogHeader>
          {selectedAudit && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label className="text-muted-foreground">{t("common.status")}</Label>
                  <div>
                    <Badge variant={getStatusBadge(selectedAudit.status).color}>
                      {getStatusBadge(selectedAudit.status).label}
                    </Badge>
                  </div>
                </div>
                <div>
                  <Label className="text-muted-foreground">{t("pages.accounting.warehouseAudit.کل_اختلاف")}</Label>
                  <div className="font-bold text-lg">
                    {selectedAudit.total_variance > 0 ? "+" : ""}
                    {formatNumber(selectedAudit.total_variance)}
                  </div>
                </div>
              </div>

              {selectedAudit.items && selectedAudit.items.length > 0 && (
                <div>
                  <Label className="text-muted-foreground">{t("pages.accounting.warehouseAudit.نتایج_شمارش")}</Label>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="text-start">{t("pages.accounting.warehouseAudit.محصول")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.warehouseAudit.سیستم")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.warehouseAudit.فیزیکی")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.warehouseAudit.اختلاف")}</TableHead>
                        <TableHead className="text-center">{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {selectedAudit.items.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell>{item.product_name}</TableCell>
                          <TableCell className="text-center">
                            {formatNumber(item.system_quantity)}
                          </TableCell>
                          <TableCell className="text-center">
                            {formatNumber(item.physical_quantity)}
                          </TableCell>
                          <TableCell
                            className={`text-center font-bold ${
                              item.variance_quantity > 0 ? "text-green-600" : "text-red-600"
                            }`}
                          >
                            {item.variance_quantity > 0 ? "+" : ""}
                            {formatNumber(item.variance_quantity)}
                          </TableCell>
                          <TableCell className="text-center">
                            <Badge
                              variant={
                                item.variance_type === "surplus" ? "default" : "destructive"
                              }
                            >
                              {item.variance_type === "surplus" ? t("pages.accounting.warehouseAudit.مازاد") : t("pages.accounting.warehouseAudit.کسری")}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}

              <DialogFooter>
                {selectedAudit.status === "in_progress" && (
                  <Button onClick={() => setCompleteConfirmId(selectedAudit.id)}>
                    <Check className="w-4 h-4 ml-2" />
                    {t("pages.accounting.warehouseAudit.تکمیل_شمارش")}
                  </Button>
                )}
                {selectedAudit.status === "completed" && (
                  <Button onClick={() => setPostConfirmId(selectedAudit.id)}>
                    <Clipboard className="w-4 h-4 ml-2" />
                    {t("pages.accounting.warehouseAudit.ثبت_اختلافات")}
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
