import { useCallback, useState } from "react"
import { Link } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import { edgeField, edgeListNumbers } from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { useModirPayamakEdge } from "./hooks/useModirPayamakEdge"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { Copy, Phone } from "lucide-react"
import { toast } from "sonner"

export function ModirPayamakNumbersPage() {
  const { t } = useLocale()
  const { layoutProps, setSuccess } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const loader = useCallback(() => edgeListNumbers(), [])
  const { items, raw, loading } = useModirPayamakEdge(loader)
  const [copied, setCopied] = useState<string | null>(null)

  const copyNumber = async (num: string) => {
    try {
      await navigator.clipboard.writeText(num)
      setCopied(num)
      setSuccess(t("pages.modirpayamak.copied"))
      toast.success(t("pages.modirpayamak.copied"))
      setTimeout(() => setCopied(null), 2000)
    } catch {
      /* ignore */
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.numbersTitle")}
      {...layoutProps}
      actions={
        <Button variant="outline" size="sm" asChild>
          <Link to="/admin/integrations/modirpayamak/settings">{t("pages.modirpayamak.defaultLineSettings")}</Link>
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.numbersTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={6} columns={4} />
          </CardContent>
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState icon={Phone} message={t("pages.modirpayamak.numbersEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.fromNumber")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.lineType")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead className="w-[80px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => {
                  const num = edgeField(row, "number", "line", "from_number", "sender")
                  return (
                    <TableRow key={`${num}-${i}`}>
                      <TableCell dir="ltr" className="font-mono">
                        {num}
                      </TableCell>
                      <TableCell>{edgeField(row, "type", "line_type", "title", "name")}</TableCell>
                      <TableCell>
                        <ModirPayamakStatusBadge status={edgeField(row, "status", "state")} />
                      </TableCell>
                      <TableCell>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() => void copyNumber(num)}
                          title={t("pages.modirpayamak.copyLine")}
                        >
                          <Copy className={`h-4 w-4 ${copied === num ? "text-success" : ""}`} />
                        </Button>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
