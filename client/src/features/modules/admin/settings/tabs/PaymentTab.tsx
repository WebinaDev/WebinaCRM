import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { Loader2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import type { PaymentSettings } from "@/api/settings"

type Props = {
  form: Record<string, unknown>
  setForm: React.Dispatch<React.SetStateAction<Record<string, unknown>>>
  saving: boolean
  onSave: () => void
}

export function PaymentTab({ form, setForm, saving, onSave }: Props) {
  const { t } = useLocale()
  const f = form as unknown as PaymentSettings

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("pages.settings.درگاه_پرداخت")}</CardTitle>
        <CardDescription>{t("pages.settings.تنظیمات_زرینپال_و_سایر_درگاهها")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <Label>{t("pages.settings.مرچنت_زرینپال")}</Label>
          <Input
            value={(f.zarinpal_merchant as string) ?? ""}
            onChange={(e) => setForm((p) => ({ ...p, zarinpal_merchant: e.target.value }))}
            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
          />
        </div>
        <div className="flex items-center gap-2">
          <Switch
            id="zarinpal_sandbox"
            checked={!!f.zarinpal_sandbox}
            onCheckedChange={(checked) => setForm((p) => ({ ...p, zarinpal_sandbox: checked }))}
          />
          <Label htmlFor="zarinpal_sandbox" className="text-sm font-normal">
            {t("pages.settings.حالت_سندباکس")}
          </Label>
        </div>
        <Button type="button" onClick={onSave} disabled={saving}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin me-2" /> : null}
          {t("common.save")}
        </Button>
      </CardContent>
    </Card>
  )
}
