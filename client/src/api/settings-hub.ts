import { crmGet, crmPost, type AjaxResponse } from "./client"
import type { ErpHubModuleId } from "@/config/erp-settings-hub"
import type { SettingsHubModuleId } from "@/config/settings-modules"

export type HubModuleKey = SettingsHubModuleId | ErpHubModuleId | "dashboard" | "hrm" | "finance" | "pm" | "scm" | "sales" | "mfg" | "docs" | "admin"

export interface SettingsHubState {
  modules: Partial<Record<string, boolean>>
}

export interface SettingsHubResponse {
  hub: SettingsHubState
}

export async function getSettingsHub(): Promise<AjaxResponse<SettingsHubResponse>> {
  return crmGet<SettingsHubResponse>("settings/hub")
}

export async function saveSettingsHubToggle(
  moduleId: string,
  enabled: boolean,
): Promise<AjaxResponse<{ message?: string }>> {
  return crmPost("settings/hub/toggle", {
    module_id: moduleId,
    enabled: enabled ? "1" : "0",
  })
}
