import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { useLocale } from "@/hooks/use-locale"
import type { SettingsTab } from "@/api/settings"

const PLACEHOLDER_KEYS: Partial<Record<SettingsTab, string>> = {
  workflow: "pages.settings.hub.tabs.workflow",
  automations: "pages.settings.hub.tabs.automations",
  forms: "pages.settings.hub.tabs.forms",
  leads: "pages.settings.hub.tabs.leads",
}

type Props = { tab: SettingsTab }

export function PlaceholderTab({ tab }: Props) {
  const { t } = useLocale()
  const titleKey = PLACEHOLDER_KEYS[tab]

  return (
    <Card>
      <CardHeader>
        <CardTitle>{titleKey ? t(titleKey) : tab}</CardTitle>
        <CardDescription>{t("pages.settings.hub.placeholder_description")}</CardDescription>
      </CardHeader>
      <CardContent>
        <p className="text-muted-foreground text-sm">{t("pages.settings.hub.placeholder_body")}</p>
      </CardContent>
    </Card>
  )
}
