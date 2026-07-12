import { useParams } from "react-router-dom"
import type { SettingsHubModule } from "@/config/settings-modules"
import type { SettingsTab } from "@/api/settings"

export function useSettingsModuleTab(module: SettingsHubModule): SettingsTab | null {
  const params = useParams<{ tab?: string }>()
  const tab = params.tab ?? module.defaultTab
  if (tab === "accounting-full") return null
  const found = module.tabs.find((t) => t.path === tab)
  if (!found || found.id === "accounting_full") return null
  return found.id as SettingsTab
}
