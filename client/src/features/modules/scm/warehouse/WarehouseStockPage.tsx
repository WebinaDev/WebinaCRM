import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useState, useEffect, useCallback } from "react"
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Badge } from "@/components/ui/badge"
import type { StatusBadgeInfo } from "@/lib/badge-variants"
import { listWarehouses, listWarehouseStock, unwrapListPayload } from "@/api/warehouses"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import { Package, TrendingDown } from "lucide-react"

interface WarehouseOption {
  id: number
  name: string
}

interface StockItem {
  id: number
  product_id: number
  product_name: string
  product_code: string
  warehouse_id: number
  warehouse_name: string
  current_quantity: number
  unit_name: string
  reorder_point: number
  last_updated: string
}

const PER_PAGE = 20

export function WarehouseStockPage() {
  const { t, isRtl, formatDate, formatNumber } = useLocale()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [items, setItems] = useState<StockItem[]>([])
  const [warehouses, setWarehouses] = useState<WarehouseOption[]>([])
  const [loading, setLoading] = useState(true)
  const [warehousesReady, setWarehousesReady] = useState(false)
  const [selectedWarehouse, setSelectedWarehouse] = useState<string>("")
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [filterLowStock, setFilterLowStock] = useState(false)
  const [error, setError] = useState<string | null>(null)

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

  const fetchStock = useCallback(async () => {
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
      if (filterLowStock) params.low_stock = 1

      const response = await listWarehouseStock(params as Record<string, string | number>)
      const data = unwrapListPayload<StockItem>(response.data)
      setItems(data)
      const total = response.total || 0
      setTotalPages(Math.max(1, Math.ceil(total / PER_PAGE)))
    } catch {
      setError(t("pages.accounting.shared.toast.warehouseSaveFailed"))
    } finally {
      setLoading(false)
    }
  }, [warehousesReady, selectedWarehouse, debouncedSearch, currentPage, filterLowStock, setTotalPages, t])

  useEffect(() => {
    fetchWarehouses()
  }, [fetchWarehouses])

  useEffect(() => {
    resetPage()
  }, [selectedWarehouse, debouncedSearch, filterLowStock, resetPage])

  useEffect(() => {
    fetchStock()
  }, [fetchStock])

  const getStockStatus = (current: number, reorder: number): StatusBadgeInfo => {
    if (current === 0) return { label: t("pages.accounting.warehouseStock.ناموجود"), color: "destructive" }
    if (current <= reorder) return { label: t("pages.accounting.warehouseStock.کمموجود"), color: "secondary" }
    return { label: t("pages.accounting.warehouseStock.موجود"), color: "default" }
  }

  const totalValue = items.reduce((sum, item) => sum + (item.current_quantity || 0), 0)

  return (
    <AccountingPageLayout
      title={t("pages.accounting.warehouseStock.موجودی_کالا")}
      description={t("pages.accounting.warehouseStock.فیلتر_و_جستجو")}
      error={error}
      onDismissError={() => setError(null)}
    >
      <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 flex-1">
            <div>
              <Label>{t("pages.accounting.warehouseStock.انبار")}</Label>
              <Select value={selectedWarehouse} onValueChange={setSelectedWarehouse}>
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
              <Label>{t("common.search")}</Label>
              <Input
                placeholder={t("pages.accounting.warehouseStock.نام_یا_کد_کالا")}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="text-start"
                dir={isRtl ? "rtl" : "ltr"}
              />
            </div>
            <div className="flex items-end">
              <Button
                variant={filterLowStock ? "default" : "outline"}
                onClick={() => setFilterLowStock(!filterLowStock)}
                className="w-full"
              >
                <TrendingDown className="w-4 h-4 ml-2" />
                {t("pages.accounting.warehouseStock.فیلتر_کمموجود")}
              </Button>
            </div>
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
        <>
          <Card>
            <CardHeader>
              <div className="flex justify-between items-center">
                <CardTitle>{t("pages.accounting.warehouseStock.موجودی_totalItems")}</CardTitle>
                <div className="text-sm text-muted-foreground">
{t("pages.accounting.shared.totalUnits", { value: formatNumber(totalValue) })}
                </div>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="text-start">{t("pages.accounting.warehouseStock.نام_کالا")}</TableHead>
                    <TableHead className="text-start">{t("pages.accounting.accountingReports.کد")}</TableHead>
                    <TableHead className="text-center">{t("pages.accounting.warehouseStock.موجودی")}</TableHead>
                    <TableHead className="text-center">{t("pages.accounting.warehouseStock.واحد")}</TableHead>
                    <TableHead className="text-center">{t("pages.accounting.warehouseStock.نقطه_سفارش")}</TableHead>
                    <TableHead className="text-center">{t("common.status")}</TableHead>
                    <TableHead className="text-start">{t("pages.accounting.warehouseStock.آخرین_بهروزرسانی")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                        <Package className="w-8 h-8 mx-auto mb-2 opacity-50" />
                        {t("pages.accounting.warehouseStock.موجودی_یافت_نشد")}
                      </TableCell>
                    </TableRow>
                  ) : (
                    items.map((item) => {
                      const status = getStockStatus(item.current_quantity, item.reorder_point)
                      return (
                        <TableRow
                          key={item.id}
                          className={
                            item.current_quantity <= item.reorder_point ? "bg-orange-50" : ""
                          }
                        >
                          <TableCell className="font-semibold">
                            {item.product_name}
                          </TableCell>
                          <TableCell>{item.product_code}</TableCell>
                          <TableCell className="text-center font-bold">
                            {formatNumber(item.current_quantity)}
                          </TableCell>
                          <TableCell className="text-center">{item.unit_name}</TableCell>
                          <TableCell className="text-center">
                            {formatNumber(item.reorder_point)}
                          </TableCell>
                          <TableCell className="text-center">
                            <Badge variant={status.color}>
                              {status.label}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-sm text-muted-foreground">
                            {formatDate(item.last_updated)}
                          </TableCell>
                        </TableRow>
                      )
                    })
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

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
    </AccountingPageLayout>
  )
}
