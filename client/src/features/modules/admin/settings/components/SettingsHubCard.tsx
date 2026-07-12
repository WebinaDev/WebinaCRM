import { useNavigate } from "react-router-dom"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Settings } from "lucide-react"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import type { SettingsHubModule } from "@/config/settings-modules"
import { renderIcon } from "@/lib/react-icon"

interface SettingsHubCardProps {
  module: SettingsHubModule
  enabled: boolean
  toggling?: boolean
  onToggle: (enabled: boolean) => void
}

export function SettingsHubCard({
  module,
  enabled,
  toggling,
  onToggle,
}: SettingsHubCardProps) {
  const navigate = useNavigate()
  const { t } = useLocale()
  const goToDetail = () => {
    const base = module.detailPath
    const path = module.id === "bots" ? base : `${base}/${module.defaultTab}`
    navigate(path)
  }

  return (
    <Card
      className={cn(
        "transition-shadow hover:shadow-md cursor-pointer",
        !enabled && "opacity-75",
      )}
      onClick={goToDetail}
    >
      <CardHeader className="flex flex-row items-start justify-between gap-3 pb-2">
        <div className="flex items-start gap-3 min-w-0">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-muted">
            {renderIcon(module.icon, "h-5 w-5 text-muted-foreground")}
          </div>
          <div className="min-w-0">
            <CardTitle className="text-base">{t(module.titleKey)}</CardTitle>
            <CardDescription className="mt-1 line-clamp-2">
              {t(module.descriptionKey)}
            </CardDescription>
          </div>
        </div>
        <div
          className="flex items-center gap-1 shrink-0"
          onClick={(e) => e.stopPropagation()}
        >
          <Switch
            checked={enabled}
            disabled={toggling}
            onCheckedChange={onToggle}
            aria-label={t("pages.settings.hub.toggle_module")}
          />
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="h-9 w-9"
            onClick={(e) => {
              e.stopPropagation()
              goToDetail()
            }}
            title={t("pages.settings.hub.open_settings")}
          >
            <Settings className="h-4 w-4" />
          </Button>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        <p className="text-xs text-muted-foreground">
          {module.tabs.length > 0
            ? t("pages.settings.hub.tab_count", { count: module.tabs.length })
            : t("pages.settings.hub.external_config")}
        </p>
      </CardContent>
    </Card>
  )
}
