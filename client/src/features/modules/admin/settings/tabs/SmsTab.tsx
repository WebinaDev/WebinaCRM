import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Loader2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import type { SmsSettings } from "@/api/settings"

type Props = {
  form: Record<string, unknown>
  setForm: React.Dispatch<React.SetStateAction<Record<string, unknown>>>
  saving: boolean
  onSave: () => void
}

export function SmsTab({ form, setForm, saving, onSave }: Props) {
  const { t } = useLocale()
  const f = form as unknown as SmsSettings

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("pages.settings.سامانه_پیامک")}</CardTitle>
        <CardDescription>{t("pages.settings.سرویس_و_اطلاعات_اتصال_پیامک")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <Label>{t("pages.settings.سرویس_پیامک")}</Label>
          <Input
            value={(f.sms_service as string) ?? ""}
            onChange={(e) => setForm((p) => ({ ...p, sms_service: e.target.value }))}
            placeholder="melipayamak, parsgreen, ..."
          />
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>{t("pages.settings.نام_کاربری")}</Label>
            <Input
              value={(f.sms_username as string) ?? ""}
              onChange={(e) => setForm((p) => ({ ...p, sms_username: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label>{t("pages.settings.رمز_عبور")}</Label>
            <Input
              type="password"
              value={(f.sms_password as string) ?? ""}
              onChange={(e) => setForm((p) => ({ ...p, sms_password: e.target.value }))}
            />
          </div>
        </div>
        <div className="space-y-2">
          <Label>{t("pages.settings.شماره_فرستنده")}</Label>
          <Input
            value={(f.sms_sender as string) ?? ""}
            onChange={(e) => setForm((p) => ({ ...p, sms_sender: e.target.value }))}
          />
        </div>
        <Button type="button" onClick={onSave} disabled={saving}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin me-2" /> : null}
          {t("common.save")}
        </Button>
      </CardContent>
    </Card>
  )
}
