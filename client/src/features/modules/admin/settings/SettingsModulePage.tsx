import { useState } from "react"
import { Navigate, useParams } from "react-router-dom"
import { getHubModule, type SettingsHubModule } from "@/config/settings-modules"
import { useLocale } from "@/hooks/use-locale"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { SettingsModuleLayout } from "./components/SettingsModuleLayout"
import { useSettingsModuleTab } from "./hooks/useSettingsModuleTab"
import { SettingsTabContent } from "./SettingsTabContent"

interface SettingsModulePageProps {
  moduleId: string
}

export function SettingsModulePage({ moduleId }: SettingsModulePageProps) {
  const module = getHubModule(moduleId)
  if (!module) {
    return <Navigate to="/admin/settings" replace />
  }
  return <SettingsModulePageInner module={module} />
}

function SettingsModulePageInner({ module }: { module: SettingsHubModule }) {
  const { isRtl } = useLocale()
  const { layoutProps, setError, setSuccess, dismissError, dismissSuccess } = useCrmFeedback()
  const params = useParams<{ tab?: string }>()
  const tab = useSettingsModuleTab(module)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)

  if (module.tabs.length > 0 && params.tab === "accounting-full") {
    return <Navigate to="/finance/settings" replace />
  }

  if (module.tabs.length > 0 && !params.tab) {
    return <Navigate to={`${module.detailPath}/${module.defaultTab}`} replace />
  }

  if (module.tabs.length > 0 && !tab) {
    return <Navigate to={`${module.detailPath}/${module.defaultTab}`} replace />
  }

  return (
    <SettingsModuleLayout
      module={module}
      error={layoutProps.error}
      success={layoutProps.success}
      onDismissError={dismissError}
      onDismissSuccess={dismissSuccess}
      loading={loading && !tab}
    >
      {tab ? (
        <SettingsTabContent
          key={tab}
          tab={tab}
          isRtl={isRtl}
          onError={setError}
          onMessage={setSuccess}
          loading={loading}
          saving={saving}
          setLoading={setLoading}
          setSaving={setSaving}
        />
      ) : null}
    </SettingsModuleLayout>
  )
}
