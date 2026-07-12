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

export type CampaignFormState = {
  title: string
  description: string
  channel: string
  status: string
  budget: string
  start_date: string
  end_date: string
}

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  editing: boolean
  form: CampaignFormState
  setForm: React.Dispatch<React.SetStateAction<CampaignFormState>>
  channels: { slug: string; name: string }[]
  statuses: { slug: string; name: string }[]
  submitting: boolean
  onSubmit: (e: React.FormEvent) => void
}

export function CampaignFormDialog({
  open,
  onOpenChange,
  editing,
  form,
  setForm,
  channels,
  statuses,
  submitting,
  onSubmit,
}: Props) {
  const { t, isRtl } = useLocale()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg" dir={isRtl ? "rtl" : "ltr"}>
        <DialogHeader>
          <DialogTitle>
            {editing ? t("pages.campaigns.ویرایش") : t("pages.campaigns.افزودن_جدید")}
          </DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="campaign_title">{t("pages.campaigns.عنوان")} *</Label>
            <Input
              id="campaign_title"
              value={form.title}
              onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="campaign_description">{t("common.description")}</Label>
            <textarea
              id="campaign_description"
              className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
              value={form.description}
              onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              rows={4}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("pages.campaigns.کانال")}</Label>
              <Select value={form.channel} onValueChange={(v) => setForm((f) => ({ ...f, channel: v }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {channels.map((ch) => (
                    <SelectItem key={ch.slug} value={ch.slug}>{ch.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("common.status")}</Label>
              <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  {statuses.map((s) => (
                    <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="campaign_budget">{t("pages.campaigns.بودجه")}</Label>
            <Input
              id="campaign_budget"
              dir="ltr"
              type="number"
              min={0}
              value={form.budget}
              onChange={(e) => setForm((f) => ({ ...f, budget: e.target.value }))}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("pages.campaigns.تاریخ_شروع")}</Label>
              <DatePicker value={form.start_date} onChange={(v) => setForm((f) => ({ ...f, start_date: v }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.campaigns.تاریخ_پایان")}</Label>
              <DatePicker value={form.end_date} onChange={(v) => setForm((f) => ({ ...f, end_date: v }))} />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting && <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
