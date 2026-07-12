import type { SettingsTab } from "@/api/settings"

export type SettingsTabShellProps = {
  tab: SettingsTab
  isRtl: boolean
  onError: (s: string | null) => void
  onMessage: (s: string | null) => void
  loading: boolean
  saving: boolean
  setLoading: (b: boolean) => void
  setSaving: (b: boolean) => void
}
