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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { DatePicker } from "@/components/ui/date-picker"
import { useLocale } from "@/hooks/use-locale"
import { Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

export type ConsultationFormState = {
  name: string
  phone: string
  email: string
  type: string
  date: string
  time: string
  status: string
  notes: string
}

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  editing: boolean
  form: ConsultationFormState
  setForm: React.Dispatch<React.SetStateAction<ConsultationFormState>>
  statuses: { slug: string; name: string }[]
  submitting: boolean
  onSubmit: (e: React.FormEvent) => void
}

export function ConsultationFormDialog({
  open,
  onOpenChange,
  editing,
  form,
  setForm,
  statuses,
  submitting,
  onSubmit,
}: Props) {
  const { t, isRtl } = useLocale()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent dir={isRtl ? "rtl" : "ltr"}>
        <DialogHeader>
          <DialogTitle>
            {editing ? t("pages.consultations.ویرایش_عنوان") : t("pages.consultations.ثبت_جدید")}
          </DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="consult_name">{t("pages.consultations.نام_درخواست_کننده_7")}</Label>
              <Input
                id="consult_name"
                value={form.name}
                onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="consult_phone">{t("pages.consultations.شماره_تماس_8")}</Label>
              <Input
                id="consult_phone"
                dir="ltr"
                value={form.phone}
                onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="consult_email">{t("pages.consultations.ایمیل")}</Label>
              <Input
                id="consult_email"
                type="email"
                dir="ltr"
                value={form.email}
                onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.consultations.نوع_مشاوره")}</Label>
              <Select value={form.type} onValueChange={(v) => setForm((f) => ({ ...f, type: v }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="phone">{t("pages.consultations.تلفنی")}</SelectItem>
                  <SelectItem value="in-person">{t("pages.consultations.حضوری")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.consultations.تاریخ_قرار")}</Label>
              <DatePicker value={form.date} onChange={(v) => setForm((f) => ({ ...f, date: v }))} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="consult_time">{t("pages.consultations.ساعت_قرار")}</Label>
              <Input
                id="consult_time"
                type="time"
                value={form.time}
                onChange={(e) => setForm((f) => ({ ...f, time: e.target.value }))}
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label>{t("pages.consultations.نتیجه_مشاوره")}</Label>
            <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {statuses.map((s) => (
                  <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="consult_notes">{t("pages.consultations.یادداشتها")}</Label>
            <textarea
              id="consult_notes"
              className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
              value={form.notes}
              onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
              rows={4}
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting && <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />}
              {editing ? t("common.save") : t("pages.consultations.ثبت_مشاوره")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
