import { useCallback, useEffect, useState } from "react"
import { getSettings, saveSettings, saveAuthSettings, type SettingsTab } from "@/api/settings"
import { useLocale } from "@/hooks/use-locale"
import { useAccent } from "@/contexts/accent-context"

export function useSettingsTab(
  tab: SettingsTab,
  onError: (s: string | null) => void,
  onMessage: (s: string | null) => void,
  setLoading: (b: boolean) => void,
  setSaving: (b: boolean) => void,
) {
  const { t } = useLocale()
  const { applyServerColor } = useAccent()
  const [data, setData] = useState<Record<string, unknown> | null>(null)
  const [form, setForm] = useState<Record<string, unknown>>({})

  const load = useCallback(async () => {
    setLoading(true)
    onError(null)
    try {
      const res = await getSettings(tab)
      if (res.success && res.data) {
        setData(res.data as Record<string, unknown>)
        const items = (res.data as { items?: unknown[] }).items
        if (items) {
          setForm({})
        } else {
          setForm(res.data as Record<string, unknown>)
        }
      } else {
        onError(res.message ?? t("common.errors.loadFailed"))
      }
    } catch {
      onError(t("pages.settings.ui.load_settings_error"))
    } finally {
      setLoading(false)
    }
  }, [tab, onError, setLoading, t])

  useEffect(() => {
    void load()
  }, [load])

  const handleSave = useCallback(async () => {
    setSaving(true)
    onError(null)
    onMessage(null)
    try {
      if (tab === "authentication") {
        const res = await saveAuthSettings(form as Record<string, string | number | boolean | undefined>)
        if (res.success) {
          onMessage(res.message ?? t("pages.settings.ذخیره_شد"))
          await load()
        } else {
          onError(res.message ?? t("common.errors.saveFailed"))
        }
        return
      }
      if (tab === "visitor_tracking") {
        return
      }
      if (["style", "payment", "sms", "notifications"].includes(tab)) {
        const res = await saveSettings(tab, form as Record<string, string | number | boolean | undefined>)
        if (res.success) {
          if (tab === "style" && typeof form.primary_color === "string") {
            applyServerColor(form.primary_color)
            if (window.webinocrm) {
              window.webinocrm.primaryColor = form.primary_color
            }
          }
          onMessage(res.message ?? t("pages.settings.ذخیره_شد"))
          await load()
        } else {
          onError(res.message ?? t("common.errors.saveFailed"))
        }
        return
      }
      onMessage(t("pages.settings.ui.tab_inline_save_hint"))
    } finally {
      setSaving(false)
    }
  }, [tab, form, onError, onMessage, load, setSaving, applyServerColor, t])

  return { data, form, setForm, load, handleSave }
}
