import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { getModirPayamakOrders, type ModirPayamakOrder } from "@/api/modirpayamak"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { ShoppingCart } from "lucide-react"

export function ModirPayamakOrdersPage() {
  const { t, isRtl, formatDateTime, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const [orders, setOrders] = useState<ModirPayamakOrder[]>([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getModirPayamakOrders(page)
    if (res.success && res.data) {
      setOrders(res.data.orders ?? [])
    } else {
      setOrders([])
      setError(getAjaxMessage(res) ?? t("pages.modirpayamak.loadError"))
    }
    setLoading(false)
  }, [page, setError, t])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <CrmPageLayout title={t("pages.modirpayamak.ordersTitle")} {...layoutProps}>
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.ordersTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={5} />
          </CardContent>
        </Card>
      ) : orders.length === 0 ? (
        <PmEmptyState icon={ShoppingCart} message={t("pages.modirpayamak.ordersEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>{t("pages.modirpayamak.domain")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.amount")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.creditAmount")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.colDate")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {orders.map((o) => (
                  <TableRow key={o.id}>
                    <TableCell>{o.id}</TableCell>
                    <TableCell className="font-mono">{o.domain}</TableCell>
                    <TableCell>{formatNumber(o.amount)}</TableCell>
                    <TableCell>{formatNumber(o.credit_amount ?? 0)}</TableCell>
                    <TableCell><ModirPayamakStatusBadge status={o.status} /></TableCell>
                    <TableCell className="text-muted-foreground text-sm">
                      {o.created_at ? formatDateTime(o.created_at) : "—"}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <PmPagination
        page={page}
        totalPages={Math.max(1, page + (orders.length >= 20 ? 1 : 0))}
        onPageChange={setPage}
        prevLabel={t("common.prevPage")}
        nextLabel={t("common.nextPage")}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  )
}
