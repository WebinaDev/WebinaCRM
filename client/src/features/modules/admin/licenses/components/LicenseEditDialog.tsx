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
import type { LicenseEditFormState } from "../types"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  form: LicenseEditFormState
  onFormChange: (updater: (prev: LicenseEditFormState) => LicenseEditFormState) => void
  onSubmit: (e: React.FormEvent) => void
  submitting: boolean
}

export function LicenseEditDialog({
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
          <DialogTitle>{t("pages.licenses.edit_title")}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="edit_project_name">{t("pages.licenses.نام_مجموعه")}</Label>
            <Input
              id="edit_project_name"
              value={form.project_name}
              onChange={(e) => onFormChange((f) => ({ ...f, project_name: e.target.value }))}
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="edit_domain">{t("pages.licenses.دامنه")}</Label>
            <Input
              id="edit_domain"
              dir="ltr"
              value={form.domain}
              onChange={(e) => onFormChange((f) => ({ ...f, domain: e.target.value }))}
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="edit_expiry">{t("pages.licenses.تاریخ_انقضا_15")}</Label>
            <DatePicker
              id="edit_expiry"
              value={form.expiry_date}
              onChange={(v) => onFormChange((f) => ({ ...f, expiry_date: v }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="edit_logo">{t("pages.licenses.آدرس_لوگو")}</Label>
            <Input
              id="edit_logo"
              dir="ltr"
              value={form.logo_url}
              onChange={(e) => onFormChange((f) => ({ ...f, logo_url: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="edit_status">{t("common.status")}</Label>
            <Select
              value={form.status}
              onValueChange={(v) => onFormChange((f) => ({ ...f, status: v }))}
            >
              <SelectTrigger id="edit_status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">{t("pages.licenses.status.active")}</SelectItem>
                <SelectItem value="inactive">{t("pages.licenses.status.inactive")}</SelectItem>
                <SelectItem value="expired">{t("pages.licenses.status.expired")}</SelectItem>
                <SelectItem value="cancelled">{t("pages.licenses.status.cancelled")}</SelectItem>
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
              {t("common.save")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
