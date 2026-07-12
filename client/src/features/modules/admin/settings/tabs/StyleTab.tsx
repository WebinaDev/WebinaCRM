import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Loader2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import { useAccent } from "@/contexts/accent-context"
import type { StyleSettings } from "@/api/settings"

type Props = {
  form: Record<string, unknown>
  setForm: React.Dispatch<React.SetStateAction<Record<string, unknown>>>
  saving: boolean
  onSave: () => void
}

export function StyleTab({ form, setForm, saving, onSave }: Props) {
  const { t } = useLocale()
  const { hasUserPreset } = useAccent()
  const f = form as unknown as StyleSettings

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("pages.settings.ظاهر_و_استایل")}</CardTitle>
        <CardDescription>{t("pages.settings.رنگ_اصلی_و_فونت_داشبورد")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <p className="text-muted-foreground rounded-md border bg-muted/40 px-3 py-2 text-sm">
          {t("pages.settings.ui.style_accent_hint")}
          {hasUserPreset ? (
            <span className="mt-1 block font-medium text-foreground">
              {t("pages.settings.ui.style_accent_active")}
            </span>
          ) : null}
        </p>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>{t("pages.settings.رنگ_اصلی")}</Label>
            <div className="flex gap-2">
              <input
                type="color"
                value={(f.primary_color as string) ?? "#845adf"}
                onChange={(e) => setForm((p) => ({ ...p, primary_color: e.target.value }))}
                className="h-10 w-14 cursor-pointer rounded border"
              />
              <Input
                value={(f.primary_color as string) ?? "#845adf"}
                onChange={(e) => setForm((p) => ({ ...p, primary_color: e.target.value }))}
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label>{t("pages.settings.خانواده_فونت")}</Label>
            <Input
              value={(f.font_family as string) ?? "IRANSans"}
              onChange={(e) => setForm((p) => ({ ...p, font_family: e.target.value }))}
            />
          </div>
          <div className="space-y-2">
            <Label>{t("pages.settings.اندازه_فونت_پایه")}</Label>
            <Input
              type="number"
              value={(f.font_size as number) ?? 14}
              onChange={(e) =>
                setForm((p) => ({ ...p, font_size: parseInt(e.target.value, 10) || 14 }))
              }
            />
          </div>
        </div>
        <Button type="button" onClick={onSave} disabled={saving}>
          {saving ? <Loader2 className="h-4 w-4 animate-spin me-2" /> : null}
          {t("common.save")}
        </Button>
      </CardContent>
    </Card>
  )
}
