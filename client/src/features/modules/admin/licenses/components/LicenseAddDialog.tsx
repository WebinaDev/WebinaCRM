import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Alert, AlertDescription } from "@/components/ui/alert"
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
import type { LicenseAddFormState } from "../types"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  form: LicenseAddFormState
  onFormChange: (updater: (prev: LicenseAddFormState) => LicenseAddFormState) => void
  onSubmit: (e: React.FormEvent) => void
  submitting: boolean
}

export function LicenseAddDialog({
  open,
  onOpenChange,
  form,
  onFormChange,
  onSubmit,
  submitting,
}: Props) {
  const { t, isRtl } = useLocale()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("pages.licenses.افزودن_لایسنس_جدید")}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="project_name">{t("pages.licenses.نام_مجموعه")}</Label>
            <Input
              id="project_name"
              value={form.project_name}
              onChange={(e) => onFormChange((f) => ({ ...f, project_name: e.target.value }))}
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="domain">{t("pages.licenses.دامنه")}</Label>
            <Input
              id="domain"
              dir="ltr"
              placeholder="example.com"
              value={form.domain}
              onChange={(e) => onFormChange((f) => ({ ...f, domain: e.target.value }))}
              required
            />
          </div>
          <Alert>
            <AlertDescription>{t("pages.licenses.domain_is_license")}</AlertDescription>
          </Alert>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="start_date">{t("pages.licenses.تاریخ_شروع")}</Label>
              <DatePicker
                id="start_date"
                value={form.start_date}
                onChange={(v) => onFormChange((f) => ({ ...f, start_date: v }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="expiry_date">{t("pages.licenses.تاریخ_انقضا_15")}</Label>
              <DatePicker
                id="expiry_date"
                value={form.expiry_date}
                onChange={(v) => onFormChange((f) => ({ ...f, expiry_date: v }))}
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="logo_url">{t("pages.licenses.آدرس_لوگو")}</Label>
            <Input
              id="logo_url"
              type="url"
              dir="ltr"
              placeholder="https://example.com/logo.png"
              value={form.logo_url}
              onChange={(e) => onFormChange((f) => ({ ...f, logo_url: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="status">{t("common.status")}</Label>
            <Select
              value={form.status}
              onValueChange={(v) => onFormChange((f) => ({ ...f, status: v }))}
            >
              <SelectTrigger id="status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">{t("common.active")}</SelectItem>
                <SelectItem value="inactive">{t("common.inactive")}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting && (
                <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />
              )}
              {t("common.add")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
