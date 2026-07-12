import { Link } from "react-router-dom"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { LineChart, ExternalLink } from "lucide-react"
import { saveSettings } from "@/api/settings"
import { useLocale } from "@/hooks/use-locale"

type Props = {
  form: Record<string, unknown>
  setForm: React.Dispatch<React.SetStateAction<Record<string, unknown>>>
  saving: boolean
  setSaving: (b: boolean) => void
  onError: (s: string | null) => void
  onMessage: (s: string | null) => void
}

export function VisitorTrackingTab({ form, setForm, saving, setSaving, onError, onMessage }: Props) {
  const { t } = useLocale()
  const enabled = Boolean(form.tracking_enabled)

  const handleTrackingToggle = async (checked: boolean) => {
    const prev = enabled
    setForm({ tracking_enabled: checked })
    setSaving(true)
    onError(null)
    onMessage(null)
    try {
      const res = await saveSettings("visitor_tracking", {
        enable_visitor_statistics: checked ? "1" : "0",
      })
      if (res.success) {
        onMessage(res.message ?? t("pages.settings.ذخیره_شد"))
      } else {
        setForm({ tracking_enabled: prev })
        onError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setForm({ tracking_enabled: prev })
      onError(t("common.errors.saveFailed"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <LineChart className="h-5 w-5" />
          {t("pages.settings.hub.tabs.visitor_tracking")}
        </CardTitle>
        <CardDescription>{t("pages.settings.hub.visitor_tracking_description")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
          <div className="space-y-1">
            <Label htmlFor="visitor-tracking-settings-toggle" className="text-base">
              {t("pages.visitorstatistics.enable_tracking")}
            </Label>
            <p className="text-muted-foreground text-sm">
              {t("pages.settings.hub.visitor_tracking_hint")}
            </p>
          </div>
          <Switch
            id="visitor-tracking-settings-toggle"
            checked={enabled}
            disabled={saving}
            onCheckedChange={handleTrackingToggle}
          />
        </div>
        <Button variant="outline" asChild>
          <Link to="/visitor-statistics" className="gap-2">
            <ExternalLink className="h-4 w-4" />
            {t("pages.settings.hub.open_visitor_stats")}
          </Link>
        </Button>
      </CardContent>
    </Card>
  )
}
