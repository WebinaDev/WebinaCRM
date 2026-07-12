import { FormSettingsSkeleton } from "@/components/skeletons"
import { Link, NavLink, useNavigate } from "react-router-dom"
import { ChevronRight } from "lucide-react"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import { Button } from "@/components/ui/button"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import type { SettingsHubModule } from "@/config/settings-modules"
import { renderIcon } from "@/lib/react-icon"

interface SettingsModuleLayoutProps {
  module: SettingsHubModule
  children: React.ReactNode
  loading?: boolean
  error?: string | null
  success?: string | null
  onDismissError?: () => void
  onDismissSuccess?: () => void
}

export function SettingsModuleLayout({
  module,
  children,
  loading,
  error,
  success,
  onDismissError,
  onDismissSuccess,
}: SettingsModuleLayoutProps) {
  const { t, isRtl } = useLocale()
  const navigate = useNavigate()
  const moduleIcon = renderIcon(module.icon, "h-6 w-6")

  return (
    <div className="space-y-6" dir={isRtl ? "rtl" : "ltr"}>
      <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
        <Button
          variant="link"
          className="h-auto p-0 text-muted-foreground"
          onClick={() => navigate("/settings")}
        >
          {t("pages.settings.hub.back")}
        </Button>
        <ChevronRight className={cn("h-4 w-4", isRtl && "rotate-180")} />
        <span className="text-foreground font-medium">{t(module.titleKey)}</span>
      </div>

      <div className="flex items-center gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-muted">
          {moduleIcon}
        </div>
        <div>
          <h2 className="text-xl font-semibold tracking-tight">{t(module.titleKey)}</h2>
          <p className="text-muted-foreground text-sm">{t(module.descriptionKey)}</p>
        </div>
      </div>

      <PmAlerts
        error={error}
        success={success}
        onDismissError={onDismissError}
        onDismissSuccess={onDismissSuccess}
      />

      <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
        {module.tabs.length > 0 && (
          <nav className="w-full shrink-0 lg:w-56">
            <ul className="flex flex-col gap-1 rounded-lg border bg-muted/30 p-2">
              {module.tabs.map((tab) => {
                const tabIcon = renderIcon(tab.icon, "h-4 w-4 shrink-0")
                if (tab.id === "accounting_full") {
                  return (
                    <li key={tab.path}>
                      <Link
                        to="/accounting/settings"
                        className={cn(
                          "flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors hover:bg-accent",
                        )}
                      >
                        {tabIcon}
                        {t(tab.titleKey)}
                      </Link>
                    </li>
                  )
                }
                return (
                  <li key={tab.path}>
                    <NavLink
                      to={`${module.detailPath}/${tab.path}`}
                      className={({ isActive }) =>
                        cn(
                          "flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors",
                          isActive
                            ? "bg-accent text-accent-foreground font-medium"
                            : "hover:bg-accent/60 text-muted-foreground",
                        )
                      }
                    >
                      {tabIcon}
                      {t(tab.titleKey)}
                    </NavLink>
                  </li>
                )
              })}
            </ul>
          </nav>
        )}

        <div className="min-w-0 flex-1">          {loading ? (
            <FormSettingsSkeleton />
          ) : (
            children
          )}
        </div>
      </div>
    </div>
  )
}
