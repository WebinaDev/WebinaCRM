import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Loader2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import type { NotificationSettings } from "@/api/settings"

type Props = {
  form: Record<string, unknown>
  setForm: React.Dispatch<React.SetStateAction<Record<string, unknown>>>
  saving: boolean
  onSave: () => void
}

export function NotificationsTab({ form, setForm, saving, onSave }: Props) {
  const { t } = useLocale()
  const f = form as unknown as NotificationSettings

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("pages.settings.اطلاعرسانیها")}</CardTitle>
        <CardDescription>{t("pages.settings.تلگرام_و_سایر_کانالهای_اعلان")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <Label>{t("pages.settings.توکن_ربات_تلگرام")}</Label>
          <Input
            value={(f.telegram_bot_token as string) ?? ""}
            onChange={(e) => setForm((p) => ({ ...p, telegram_bot_token: e.target.value }))}
            placeholder="..."
          />
        </div>
        <div className="space-y-2">
          <Label>{t("pages.settings.شناسه_چت_تلگرام")}</Label>
          <Input
            value={(f.telegram_chat_id as string) ?? ""}
            onChange={(e) => setForm((p) => ({ ...p, telegram_chat_id: e.target.value }))}
            placeholder="..."
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
