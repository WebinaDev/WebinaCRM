import { crmGet, crmPost } from "./client"

export type CoreUpdateStatus = {
  version: string
  latest_version: string
  update_available: boolean
  release_notes?: string
  package_available?: boolean
}

export type CoreUpdateResult = {
  ok: boolean
  version: string
  previous_version: string
  reload_required: boolean
}

export async function getCrmCoreUpdateStatus(refresh = false) {
  return crmGet<CoreUpdateStatus>("core/update-status", refresh ? { refresh: "1" } : undefined)
}

export async function runCrmCoreUpdate(version?: string) {
  return crmPost<CoreUpdateResult>("core/update", version ? { version } : {})
}
