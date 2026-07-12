import { CardGridSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { useLocale } from "@/hooks/use-locale"
import { ERP_SETTINGS_HUB_MODULES, ERP_SETTINGS_HUB_SUBMODULES } from "@/config/erp-settings-hub"
import { SETTINGS_HUB_MODULES, type SettingsHubModuleId } from "@/config/settings-modules"
import { getSettingsHub, saveSettingsHubToggle, type SettingsHubState } from "@/api/settings-hub"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { SettingsHubCard } from "./components/SettingsHubCard"

function defaultModules(): SettingsHubState["modules"] {
  const m: SettingsHubState["modules"] = {
    dashboard: true,
    general: true,
    projects: true,
    crm: true,
    bots: true,
    accounting: true,
  }
  for (const mod of ERP_SETTINGS_HUB_MODULES) {
    m[mod.settingsKey] = mod.settingsKey !== "mfg"
  }
  for (const sub of ERP_SETTINGS_HUB_SUBMODULES) {
    m[sub.settingsKey] = true
  }
  return m
}

export function SettingsHubPage() {
  const { t } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const [modules, setModules] = useState(defaultModules)
  const [loading, setLoading] = useState(true)
  const [togglingId, setTogglingId] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getSettingsHub()
      if (res.success && res.data?.hub?.modules) {
        setModules({ ...defaultModules(), ...res.data.hub.modules })
      } else {
        setError(res.message ?? t("pages.settings.hub.load_error"))
      }
    } catch {
      setError(t("pages.settings.hub.load_error"))
    } finally {
      setLoading(false)
    }
  }, [t, setError])

  useEffect(() => {
    void load()
  }, [load])

  const handleToggle = async (settingsKey: string, enabled: boolean) => {
    const prev = modules[settingsKey]
    setModules((s) => ({ ...s, [settingsKey]: enabled }))
    setTogglingId(settingsKey)
    try {
      const res = await saveSettingsHubToggle(settingsKey, enabled)
      if (!res.success) {
        setModules((s) => ({ ...s, [settingsKey]: prev }))
        setError(res.message ?? t("pages.settings.hub.save_error"))
      }
    } catch {
      setModules((s) => ({ ...s, [settingsKey]: prev }))
      setError(t("pages.settings.hub.save_error"))
    } finally {
      setTogglingId(null)
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.settings.hub.title")}
      description={t("pages.settings.hub.subtitle")}
      {...layoutProps}
    >          {loading ? (
            <CardGridSkeleton showPageHeader={false} tabs={0} cards={6} />
          ) : (
        <div className="space-y-8">
          <section>
            <h2 className="mb-3 text-sm font-semibold">{t("nav.erp.hubSection")}</h2>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {ERP_SETTINGS_HUB_MODULES.map((mod) => (
                <SettingsHubCard
                  key={mod.id}
                  module={{
                    id: mod.id as SettingsHubModuleId,
                    settingsKey: mod.settingsKey,
                    titleKey: mod.titleKey,
                    descriptionKey: mod.descriptionKey,
                    icon: mod.icon,
                    detailPath: "/admin/settings",
                    defaultTab: "",
                    tabs: [],
                  }}
                  enabled={modules[mod.settingsKey] ?? mod.settingsKey !== "mfg"}
                  toggling={togglingId === mod.settingsKey}
                  onToggle={(enabled) => void handleToggle(mod.settingsKey, enabled)}
                />
              ))}
            </div>
          </section>
          <section>
            <h2 className="mb-3 text-sm font-semibold">{t("nav.erp.hubSubmoduleSection")}</h2>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {ERP_SETTINGS_HUB_SUBMODULES.map((mod) => (
                <SettingsHubCard
                  key={mod.id}
                  module={{
                    id: mod.id as SettingsHubModuleId,
                    settingsKey: mod.settingsKey,
                    titleKey: mod.titleKey,
                    descriptionKey: mod.descriptionKey,
                    icon: mod.icon,
                    detailPath: "/admin/settings",
                    defaultTab: "",
                    tabs: [],
                  }}
                  enabled={modules[mod.settingsKey] ?? true}
                  toggling={togglingId === mod.settingsKey}
                  onToggle={(enabled) => void handleToggle(mod.settingsKey, enabled)}
                />
              ))}
            </div>
          </section>
          <section>
            <h2 className="mb-3 text-sm font-semibold">{t("nav.erp.configSection")}</h2>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {SETTINGS_HUB_MODULES.map((mod) => (
                <SettingsHubCard
                  key={mod.id}
                  module={mod}
                  enabled={modules[mod.id] ?? modules[mod.settingsKey] ?? true}
                  toggling={togglingId === mod.settingsKey || togglingId === mod.id}
                  onToggle={(enabled) => void handleToggle(mod.settingsKey, enabled)}
                />
              ))}
            </div>
          </section>
        </div>
      )}
    </CrmPageLayout>
  )
}
