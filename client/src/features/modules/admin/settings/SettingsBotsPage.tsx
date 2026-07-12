import { Link } from "react-router-dom"
import { Bot, ExternalLink } from "lucide-react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"
import { getHubModule } from "@/config/settings-modules"
import { SettingsModuleLayout } from "./components/SettingsModuleLayout"

export function SettingsBotsPage() {
  const { t, isRtl } = useLocale()
  const module = getHubModule("bots")!

  return (
    <SettingsModuleLayout module={module}>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Bot className="h-5 w-5" />
            {t("pages.settings.hub.modules.bots.title")}
          </CardTitle>
          <CardDescription>{t("pages.settings.hub.bots_hint")}</CardDescription>
        </CardHeader>
        <CardContent>
          <Button asChild>
            <Link to="/bale-business?tab=settings">
              <ExternalLink className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.settings.hub.open_bale_settings")}
            </Link>
          </Button>
        </CardContent>
      </Card>
    </SettingsModuleLayout>
  )
}
