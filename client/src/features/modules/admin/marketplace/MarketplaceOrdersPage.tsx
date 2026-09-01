import { CardListSkeleton } from "@/components/skeletons"
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
import { getMarketplaceOrders, type MarketplaceOrder } from "@/api/marketplace"
import { marketplaceError } from "./marketplace-messages"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { ShoppingCart } from "lucide-react"

export function MarketplaceOrdersPage() {
  const { t, formatDateTime, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const [orders, setOrders] = useState<MarketplaceOrder[]>([])
  const [entitlements, setEntitlements] = useState<Record<string, unknown>[]>([])
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getMarketplaceOrders()
    if (res.success && res.data) {
      setOrders(res.data.orders ?? [])
      setEntitlements(res.data.entitlements ?? [])
    } else {
      setError(marketplaceError(t, res, "pages.marketplace.loadError"))
      setOrders([])
      setEntitlements([])
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <CrmPageLayout
      title={t("pages.marketplace.ordersTitle")}
      description={t("pages.marketplace.ordersDesc")}
      {...layoutProps}
    >
      {loading ? (
        <CardListSkeleton rows={6} />
      ) : (
        <>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pages.marketplace.ordersSection")}</CardTitle>
            </CardHeader>
            <CardContent>
              {orders.length === 0 ? (
                <PmEmptyState icon={ShoppingCart} message={t("pages.marketplace.ordersEmpty")} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.marketplace.name")}</TableHead>
                      <TableHead>{t("pages.marketplace.colDomain")}</TableHead>
                      <TableHead>{t("pages.marketplace.releaseStatus")}</TableHead>
                      <TableHead>{t("pages.marketplace.colAmount")}</TableHead>
                      <TableHead>{t("pages.marketplace.colDate")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {orders.map((o) => (
                      <TableRow key={o.id}>
                        <TableCell className="font-medium">
                          {o.module_name ?? o.module_slug}
                        </TableCell>
                        <TableCell>{o.domain}</TableCell>
                        <TableCell>{o.status}</TableCell>
                        <TableCell>{formatNumber(o.amount)}</TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {o.created_at ? formatDateTime(o.created_at) : "—"}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">
                {t("pages.marketplace.entitlementsSection")}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {entitlements.length === 0 ? (
                <PmEmptyState
                  icon={ShoppingCart}
                  message={t("pages.marketplace.entitlementsEmpty")}
                />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.marketplace.name")}</TableHead>
                      <TableHead>{t("pages.marketplace.colDomain")}</TableHead>
                      <TableHead>{t("pages.marketplace.releaseStatus")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {entitlements.map((e, i) => (
                      <TableRow key={i}>
                        <TableCell className="font-medium">
                          {(e.module_name as string) ?? (e.module_slug as string)}
                        </TableCell>
                        <TableCell>{(e.domain as string) ?? "—"}</TableCell>
                        <TableCell>{(e.status as string) ?? "—"}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </CrmPageLayout>
  )
}
