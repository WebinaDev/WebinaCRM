import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Loader2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import type { AuthSettings } from "@/api/settings"

type Props = {
  form: Record<string, unknown>
  setForm: React.Dispatch<React.SetStateAction<Record<string, unknown>>>
  saving: boolean
  onSave: () => void
}

export function AuthenticationTab({ form, setForm, saving, onSave }: Props) {
  const { t } = useLocale()
  const f = form as unknown as AuthSettings

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("pages.settings.احراز_هویت")}</CardTitle>
        <CardDescription>{t("pages.settings.تنظیمات_ورود،_OTP_و_مسیرهای_بازگردانی")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>{t("pages.settings.پترن_شماره_موبایل_Regex")}</Label>
            <Input
              value={(f.login_phone_pattern as string) ?? ""}
              onChange={(e) => setForm((p) => ({ ...p, login_phone_pattern: e.target.value }))}
              placeholder="^09[0-9]{9}$"
            />
          </div>
          <div className="space-y-2">
            <Label>{t("pages.settings.طول_شماره_موبایل")}</Label>
            <Input
              type="number"
              value={(f.login_phone_length as number) ?? 11}
              onChange={(e) =>
                setForm((p) => ({ ...p, login_phone_length: parseInt(e.target.value, 10) || 11 }))
              }
            />
          </div>
        </div>
        <div className="space-y-2">
          <Label>{t("pages.settings.قالب_پیامک_ورود_CODE_برای_کد")}</Label>
          <Input
            value={(f.login_sms_template as string) ?? ""}
            onChange={(e) => setForm((p) => ({ ...p, login_sms_template: e.target.value }))}
            placeholder={t("pages.settings.کد_ورود_شما_CODE")}
          />
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <div className="space-y-2">
            <Label>{t("pages.settings.انقضای_OTP_دقیقه")}</Label>
            <Input
              type="number"
              value={(f.otp_expiry_minutes as number) ?? 5}
              onChange={(e) =>
                setForm((p) => ({ ...p, otp_expiry_minutes: parseInt(e.target.value, 10) || 5 }))
              }
            />
          </div>
          <div className="space-y-2">
            <Label>{t("pages.settings.حداکثر_تلاش_OTP")}</Label>
            <Input
              type="number"
              value={(f.otp_max_attempts as number) ?? 3}
              onChange={(e) =>
                setForm((p) => ({ ...p, otp_max_attempts: parseInt(e.target.value, 10) || 3 }))
              }
            />
          </div>
          <div className="space-y-2">
            <Label>{t("pages.settings.طول_کد_OTP")}</Label>
            <Input
              type="number"
              value={(f.otp_length as number) ?? 6}
              onChange={(e) =>
                setForm((p) => ({ ...p, otp_length: parseInt(e.target.value, 10) || 6 }))
              }
            />
          </div>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>{t("pages.settings.آدرس_بازگردانی_پس_از_ورود")}</Label>
            <Input
              value={(f.login_redirect_url as string) ?? ""}
              onChange={(e) => setForm((p) => ({ ...p, login_redirect_url: e.target.value }))}
              placeholder="https://..."
            />
          </div>
          <div className="space-y-2">
            <Label>{t("pages.settings.آدرس_بازگردانی_پس_از_خروج")}</Label>
            <Input
              value={(f.logout_redirect_url as string) ?? ""}
              onChange={(e) => setForm((p) => ({ ...p, logout_redirect_url: e.target.value }))}
              placeholder="https://..."
            />
          </div>
        </div>
        <div className="flex flex-wrap gap-6">
          <div className="flex items-center gap-2">
            <Switch
              id="enable_password_login"
              checked={!!f.enable_password_login}
              onCheckedChange={(checked) =>
                setForm((p) => ({ ...p, enable_password_login: checked }))
              }
            />
            <Label htmlFor="enable_password_login" className="text-sm font-normal">
              {t("pages.settings.ورود_با_رمز_عبور")}
            </Label>
          </div>
          <div className="flex items-center gap-2">
            <Switch
              id="enable_sms_login"
              checked={!!f.enable_sms_login}
              onCheckedChange={(checked) => setForm((p) => ({ ...p, enable_sms_login: checked }))}
            />
            <Label htmlFor="enable_sms_login" className="text-sm font-normal">
              {t("pages.settings.ورود_با_پیامک_OTP")}
            </Label>
          </div>
          <div className="flex items-center gap-2">
            <Switch
              id="force_logout_inactive"
              checked={!!f.force_logout_inactive}
              onCheckedChange={(checked) =>
                setForm((p) => ({ ...p, force_logout_inactive: checked }))
              }
            />
            <Label htmlFor="force_logout_inactive" className="text-sm font-normal">
              {t("pages.settings.خروج_خودکار_در_صورت_عدم_فعالیت")}
            </Label>
          </div>
        </div>
        <div className="space-y-2">
          <Label>{t("pages.settings.مدت_عدم_فعالیت_قبل_از_خروج_دقیقه")}</Label>
          <Input
            type="number"
            value={(f.inactive_timeout_minutes as number) ?? 30}
            onChange={(e) =>
              setForm((p) => ({
                ...p,
                inactive_timeout_minutes: parseInt(e.target.value, 10) || 30,
              }))
            }
          />
        </div>
        <Button type="button" onClick={onSave} disabled={saving}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin me-2" /> : null}
          {t("pages.settings.ui.save_auth")}
        </Button>
      </CardContent>
    </Card>
  )
}
