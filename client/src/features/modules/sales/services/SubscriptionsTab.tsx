import { TableListSkeleton } from "@/components/TableListSkeleton"
import { Link } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { useLocale } from "@/hooks/use-locale"
import { Loader2, FileText } from "lucide-react"
import type { Subscription } from "@/api/services"

type Props = {
  loading: boolean
  error: string | null
  wcActive: boolean
  subscriptions: Subscription[]
  convertingId: number | null
  onConvert: (sub: Subscription) => void
}

export function SubscriptionsTab({
  loading,
  error,
  wcActive,
  subscriptions,
  convertingId,
  onConvert,
}: Props) {
  const { t, isRtl, formatDate } = useLocale()

  return (
    <div dir={isRtl ? "rtl" : "ltr"} className="text-start">
      <PmAlerts error={error} />
      {!wcActive && (
        <PmAlerts error={t("pages.services.افزونه_اشتراک_غیرفعال")} />
      )}
      {loading ? (

        <TableListSkeleton rows={8} columns={6} withAvatarColumn />

      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t("pages.services.اشتراک")}</TableHead>
              <TableHead>{t("pages.accounting.persons.مشتری")}</TableHead>
              <TableHead>{t("common.status")}</TableHead>
              <TableHead>{t("pages.accounting.checks.مبلغ")}</TableHead>
              <TableHead>{t("pages.licenses.تاریخ_شروع")}</TableHead>
              <TableHead>{t("pages.services.پرداخت_بعدی")}</TableHead>
              <TableHead>{t("common.actions")}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {subscriptions.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                  {wcActive ? t("pages.services.هیچ_اشتراکی") : t("pages.services.اشتراک_غیرفعال")}
                </TableCell>
              </TableRow>
            ) : (
              subscriptions.map((sub) => (
                <TableRow key={sub.id}>
                  <TableCell>#{sub.id}</TableCell>
                  <TableCell>
                    {sub.customer_id ? (
                      <Link
                        to={`/customers?edit=${sub.customer_id}`}
                        className="text-primary hover:underline"
                      >
                        {sub.customer_name}
                      </Link>
                    ) : (
                      sub.customer_name
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge variant={sub.status === "active" ? "default" : "secondary"}>
                      {sub.status_name}
                    </Badge>
                  </TableCell>
                  <TableCell>{sub.total_formatted}</TableCell>
                  <TableCell>{sub.start_date ? formatDate(sub.start_date) : "-"}</TableCell>
                  <TableCell>{sub.next_payment || "-"}</TableCell>
                  <TableCell>
                    {sub.already_converted ? (
                      <Link to="/contracts">
                        <Button variant="outline" size="sm" className="gap-1">
                          <FileText className="h-4 w-4" />
                          {t("pages.services.مشاهده_قراردادها")}
                        </Button>
                      </Link>
                    ) : (
                      <Button
                        size="sm"
                        onClick={() => onConvert(sub)}
                        disabled={convertingId === sub.id}
                        className="gap-1"
                      >
                        {convertingId === sub.id ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <FileText className="h-4 w-4" />
                        )}
                        {t("pages.services.تبدیل_به_قرارداد")}
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
