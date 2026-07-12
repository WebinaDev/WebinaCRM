import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"
import { Loader2 } from "lucide-react"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  renewDate: string
  onRenewDateChange: (value: string) => void
  onSubmit: (e: React.FormEvent) => void
  submitting: boolean
}

export function LicenseRenewDialog({
  open,
  onOpenChange,
  renewDate,
  onRenewDateChange,
  onSubmit,
  submitting,
}: Props) {
  const { t, isRtl } = useLocale()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("pages.licenses.تمدید_لایسنس")}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="renew_expiry_date">{t("pages.licenses.تاریخ_انقضای_جدید")}</Label>
            <DatePicker
              id="renew_expiry_date"
              value={renewDate}
              onChange={onRenewDateChange}
              required
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting && (
                <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />
              )}
              {t("pages.licenses.تمدید_لایسنس")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
