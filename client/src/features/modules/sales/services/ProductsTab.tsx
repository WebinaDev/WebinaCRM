import { TableListSkeleton } from "@/components/TableListSkeleton"
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
import { Pencil } from "lucide-react"
import type { Product } from "@/api/services"

type ServiceType = { value: string; label: string }

type Props = {
  loading: boolean
  error: string | null
  wcActive: boolean
  products: Product[]
  serviceTypes: ServiceType[]
  onEdit: (p: Product) => void
}

export function ProductsTab({
  loading,
  error,
  wcActive,
  products,
  serviceTypes,
  onEdit,
}: Props) {
  const { t, isRtl } = useLocale()

  return (
    <div dir={isRtl ? "rtl" : "ltr"} className="text-start">
      <PmAlerts error={error} />
      {!wcActive && <PmAlerts error={t("pages.services.افزونه_ووکامرس_فعال_نیست")} />}
      {loading ? (

        <TableListSkeleton rows={8} columns={6} withAvatarColumn />

      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t("pages.accounting.warehouseAudit.محصول")}</TableHead>
              <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
              <TableHead>{t("pages.accounting.warehouseInbound.قیمت")}</TableHead>
              <TableHead>{t("pages.services.قالب_تسک")}</TableHead>
              <TableHead>{t("pages.services.نوع_خدمت")}</TableHead>
              <TableHead>{t("common.actions")}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {products.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-start py-8 text-muted-foreground">
                  {wcActive ? t("pages.services.هیچ_محصولی") : t("pages.services.ووکامرس_غیرفعال")}
                </TableCell>
              </TableRow>
            ) : (
              products.map((p) => (
                <TableRow key={p.id}>
                  <TableCell>{p.name}</TableCell>
                  <TableCell>
                    <Badge variant="outline">{p.type}</Badge>
                  </TableCell>
                  <TableCell>{p.price || "-"}</TableCell>
                  <TableCell>{p.task_template_title || "-"}</TableCell>
                  <TableCell>
                    {serviceTypes.find((s) => s.value === p.service_task_type)?.label ??
                      p.service_task_type}
                  </TableCell>
                  <TableCell>
                    <Button variant="outline" size="sm" onClick={() => onEdit(p)} className="gap-1">
                      <Pencil className="h-4 w-4" />
                      {t("common.edit")}
                    </Button>
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
