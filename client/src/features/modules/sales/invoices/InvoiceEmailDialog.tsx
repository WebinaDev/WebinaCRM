import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useLocale } from "@/hooks/use-locale"
import { Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  email: string
  setEmail: (v: string) => void
  submitting: boolean
  onSend: () => void
}

export function InvoiceEmailDialog({
  open,
  onOpenChange,
  email,
  setEmail,
  submitting,
  onSend,
}: Props) {
  const { t, isRtl } = useLocale()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent dir={isRtl ? "rtl" : "ltr"}>
        <DialogHeader>
          <DialogTitle>{t("pages.invoices.ارسال_ایمیل")}</DialogTitle>
        </DialogHeader>
        <div className="space-y-2">
          <Label htmlFor="invoice_email">{t("pages.consultations.ایمیل")}</Label>
          <Input
            id="invoice_email"
            type="email"
            dir="ltr"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder={t("pages.invoices.ایمیل_اختیاری")}
          />
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t("common.cancel")}
          </Button>
          <Button onClick={onSend} disabled={submitting}>
            {submitting && <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />}
            {t("pages.invoices.ارسال")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
