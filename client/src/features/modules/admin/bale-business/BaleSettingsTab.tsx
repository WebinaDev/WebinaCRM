import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Loader2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import type { BaleSettings } from "./types"

type Props = {
  settings: BaleSettings | null
  saving: boolean
  onChange: (key: string, value: string | number) => void
  onSave: () => void
  t: (key: string) => string
}

function FlagSelect({
  id,
  label,
  value,
  onChange,
  t,
}: {
  id: string
  label: string
  value: string
  onChange: (v: string) => void
  t: (key: string) => string
}) {
  return (
    <div className="grid gap-2">
      <Label htmlFor={id}>{label}</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger id={id}>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="1">{t("common.active")}</SelectItem>
          <SelectItem value="0">{t("common.inactive")}</SelectItem>
        </SelectContent>
      </Select>
    </div>
  )
}

export function BaleSettingsTab({ settings, saving, onChange, onSave, t }: Props) {
  const { isRtl } = useLocale()

  return (
    <div className="space-y-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
    <Card className="text-start">
      <CardHeader className="text-start">
        <CardTitle>{t("pages.balebusiness.اتصال_و_فروش")}</CardTitle>
      </CardHeader>
      <CardContent className="grid gap-4 text-start">
        <div className="grid gap-2">
          <Label htmlFor="bot_token">{t("pages.balebusiness.bot_token")}</Label>
          <Input
            id="bot_token"
            value={String(settings?.bot_token ?? "")}
            onChange={(e) => onChange("bot_token", e.target.value)}
            autoComplete="off"
          />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="provider_token">{t("pages.balebusiness.provider_token")}</Label>
          <Input
            id="provider_token"
            value={String(settings?.provider_token ?? "")}
            onChange={(e) => onChange("provider_token", e.target.value)}
            autoComplete="off"
          />
        </div>
        <FlagSelect
          id="enable_auto_register_user"
          label={t("pages.balebusiness.ثبت_خودکار_کاربر_از_start")}
          value={String(settings?.enable_auto_register_user ?? "1")}
          onChange={(v) => onChange("enable_auto_register_user", v)}
          t={t}
        />
        <FlagSelect
          id="enable_auto_lead_from_business"
          label={t("pages.balebusiness.تبدیل_خودکار_کسبوکار_به_سرنخ")}
          value={String(settings?.enable_auto_lead_from_business ?? "1")}
          onChange={(v) => onChange("enable_auto_lead_from_business", v)}
          t={t}
        />
        <div className="grid gap-2">
          <Label htmlFor="channel_id">{t("pages.balebusiness.شناسه_کانال")}</Label>
          <Input
            id="channel_id"
            value={String(settings?.channel_id ?? "")}
            onChange={(e) => onChange("channel_id", e.target.value)}
          />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="sales_wc_product_id">{t("pages.balebusiness.شناسه_محصول_ووکامرس_فروش")}</Label>
          <Input
            id="sales_wc_product_id"
            type="number"
            value={String(settings?.sales_wc_product_id ?? 0)}
            onChange={(e) => onChange("sales_wc_product_id", Number(e.target.value))}
          />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="welcome_text">{t("pages.balebusiness.متن_خوشآمد")}</Label>
          <Textarea
            id="welcome_text"
            rows={3}
            value={String(settings?.welcome_text ?? "")}
            onChange={(e) => onChange("welcome_text", e.target.value)}
          />
        </div>
        <Button type="button" onClick={onSave} disabled={saving || !settings}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin me-2" /> : null}
          {t("pages.balebusiness.ذخیره_تنظیمات")}
        </Button>
      </CardContent>
    </Card>
    </div>
  )
}
