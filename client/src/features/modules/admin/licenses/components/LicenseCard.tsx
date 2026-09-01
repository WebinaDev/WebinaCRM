import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Progress } from "@/components/ui/progress"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import type { License } from "@/api/licenses"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import {
  Building2,
  MoreVertical,
  Pencil,
  RefreshCw,
  Trash2,
  XCircle,
} from "lucide-react"

type Props = {
  license: License
  onEdit: (license: License) => void
  onRenew: (id: number) => void
  onCancel: (id: number) => void
  onDelete: (id: number) => void
}

export function LicenseCard({ license, onEdit, onRenew, onCancel, onDelete }: Props) {
  const { t, isRtl, formatDateTime } = useLocale()

  const statusLabel = (status: string) => {
    const map: Record<string, string> = {
      active: t("pages.licenses.status.active"),
      inactive: t("pages.licenses.status.inactive"),
      expired: t("pages.licenses.status.expired"),
      cancelled: t("pages.licenses.status.cancelled"),
    }
    return map[status] ?? status
  }

  const formatExpiry = (d: string | null) =>
    !d || d.startsWith("0000-00-00")
      ? t("pages.licenses.unlimited")
      : formatDateTime(d)

  return (
    <Card
      className={cn(
        "overflow-hidden",
        license.card_color === "red" && "border-destructive/40",
        license.card_color === "yellow" && "border-yellow-500/40",
        license.card_color === "green" && "border-green-500/40",
        license.card_color === "black" && "border-foreground/30",
      )}
    >
      <CardContent className="pt-6">
        <div className="mb-4 flex flex-col items-center text-center">
          {license.logo_url ? (
            <img
              src={license.logo_url}
              alt={license.project_name}
              className="mb-2 h-16 w-16 object-contain"
            />
          ) : (
            <div className="mb-2 flex h-16 w-16 items-center justify-center rounded-lg bg-muted">
              <Building2 className="h-8 w-8 text-muted-foreground" />
            </div>
          )}
          <h5 className="font-medium">{license.project_name}</h5>
          <p className="text-sm text-muted-foreground" dir="ltr">
            {license.domain}
          </p>
        </div>
        <div className="mb-4 space-y-2">
          <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">{t("pages.licenses.باقیمانده")}</span>
            <span className="font-medium">{license.remaining_percentage.toFixed(1)}٪</span>
          </div>
          <Progress value={license.remaining_percentage} className="h-1.5" />
        </div>
        <div className="mb-2 flex justify-between text-sm">
          <span className="text-muted-foreground">{t("pages.licenses.تاریخ_انقضا")}</span>
          <span className="font-medium">{formatExpiry(license.expiry_date)}</span>
        </div>
        <div className="mb-4 flex justify-between text-sm">
          <span className="text-muted-foreground">{t("pages.licenses.روزهای_باقیمانده")}</span>
          {license.remaining_days === null ? (
            <Badge variant="secondary">{t("pages.licenses.vip")}</Badge>
          ) : (
            <Badge variant="outline">
              {`${license.remaining_days} ${t("pages.licenses.day_unit")}`}
            </Badge>
          )}
        </div>
        <div className="flex items-center justify-between">
          <Badge
            variant={
              license.status === "active"
                ? "default"
                : license.status === "expired"
                  ? "destructive"
                  : "secondary"
            }
          >
            {statusLabel(license.status)}
          </Badge>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreVertical className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onEdit(license)}>
                <Pencil className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                {t("common.edit")}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onRenew(license.id)}>
                <RefreshCw className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                {t("pages.licenses.تمدید_لایسنس")}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onCancel(license.id)}>
                <XCircle className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                {t("pages.licenses.لغو")}
              </DropdownMenuItem>
              <DropdownMenuItem className="text-destructive" onClick={() => onDelete(license.id)}>
                <Trash2 className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
                {t("common.delete")}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </CardContent>
    </Card>
  )
}
