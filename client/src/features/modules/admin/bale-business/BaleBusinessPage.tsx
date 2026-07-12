import { FormSettingsSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { useSearchParams } from "react-router-dom"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { BALE_TABS, type BaleBotStats, type BaleKpi, type BaleLogRow, type BaleSettings, type BaleTab } from "./types"
import {
  getBaleWebhookInfo,
  loadBaleData,
  postBaleTestLog,
  saveBaleSettings,
  setBaleWebhook,
} from "./api"
import { BaleSettingsTab } from "./BaleSettingsTab"
import { BaleWebhookTab } from "./BaleWebhookTab"
import { BaleLogsTab } from "./BaleLogsTab"

export function BaleBusinessPage() {
  const { t, formatDateTime, isRtl } = useLocale()
  const { layoutProps, setError, setSuccess } = useCrmFeedback()
  const [searchParams, setSearchParams] = useSearchParams()
  const tabParam = searchParams.get("tab")
  const activeTab: BaleTab = BALE_TABS.includes(tabParam as BaleTab)
    ? (tabParam as BaleTab)
    : "settings"

  const [settings, setSettings] = useState<BaleSettings | null>(null)
  const [webhookUrl, setWebhookUrl] = useState("")
  const [webhookNote, setWebhookNote] = useState("")
  const [logs, setLogs] = useState<BaleLogRow[]>([])
  const [stats, setStats] = useState<{
    support_opened: number
    support_item_clicked: number
  } | null>(null)
  const [botStats, setBotStats] = useState<BaleBotStats | null>(null)
  const [kpi, setKpi] = useState<BaleKpi | null>(null)
  const [campaigns, setCampaigns] = useState<Array<Record<string, unknown>>>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [diagLoading, setDiagLoading] = useState(false)
  const [webhookInfoJson, setWebhookInfoJson] = useState("")

  const loadAll = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await loadBaleData()
    setLoading(false)
    if (!res.ok || !res.data) {
      setError(res.message ?? t("pages.balebusiness.خطا_در_بارگذاری_تنظیمات"))
      return
    }
    const d = res.data
    setSettings(d.settings)
    setWebhookUrl(d.webhookUrl)
    setWebhookNote(d.webhookNote)
    setLogs(d.logs)
    setStats(d.stats)
    setBotStats(d.botStats)
    setKpi(d.kpi)
    setCampaigns(d.campaigns)
  }, [t, setError])

  useEffect(() => {
    void loadAll()
  }, [loadAll])

  const handleChange = (key: string, value: string | number) => {
    setSettings((prev) => ({ ...(prev ?? {}), [key]: value }))
  }

  const saveSettings = async () => {
    if (!settings) return
    setSaving(true)
    setError(null)
    setSuccess(null)
    const res = await saveBaleSettings(settings)
    setSaving(false)
    if (res.ok && res.data) {
      setSettings(res.data)
      setSuccess(t("pages.balebusiness.تنظیمات_ذخیره_شد"))
    } else {
      setError(res.message ?? t("pages.balebusiness.ذخیره_ناموفق_بود"))
    }
  }

  const runSetWebhook = async () => {
    setDiagLoading(true)
    setWebhookInfoJson("")
    setError(null)
    const res = await setBaleWebhook()
    setDiagLoading(false)
    if (res.ok) {
      setSuccess(t("pages.balebusiness.وبهوک_ثبت_شد"))
      setWebhookInfoJson(JSON.stringify(res.data, null, 2))
    } else {
      setError(res.message ?? t("pages.accounting.accountingSettings.خطا"))
    }
  }

  const runWebhookInfo = async () => {
    setDiagLoading(true)
    setError(null)
    const res = await getBaleWebhookInfo()
    setDiagLoading(false)
    if (res.ok && res.data) {
      setWebhookInfoJson(JSON.stringify(res.data.webhook_info, null, 2))
    } else {
      setError(res.message ?? t("pages.accounting.accountingSettings.خطا"))
    }
  }

  const runTestLog = async () => {
    setDiagLoading(true)
    const res = await postBaleTestLog()
    setDiagLoading(false)
    if (res.ok) {
      setSuccess(t("pages.balebusiness.لاگ_تستی"))
      void loadAll()
    } else {
      setError(res.message ?? t("pages.accounting.accountingSettings.خطا"))
    }
  }

  if (loading && !settings) {
    return (
      <CrmPageLayout
        title={t("pages.balebusiness.عنوان_صفحه")}
        description={t("pages.balebusiness.توضیح_صفحه")}
        {...layoutProps}
        className="max-w-5xl mx-auto"
      >
        <FormSettingsSkeleton />
      </CrmPageLayout>
    )
  }

  return (
    <CrmPageLayout
      title={t("pages.balebusiness.عنوان_صفحه")}
      description={t("pages.balebusiness.توضیح_صفحه")}
      {...layoutProps}
      className="max-w-5xl mx-auto"
    >
      <Tabs
        value={activeTab}
        onValueChange={(v) => {
          if (v === "settings") setSearchParams({}, { replace: true })
          else setSearchParams({ tab: v }, { replace: true })
        }}
        className="w-full text-start"
        dir={isRtl ? "rtl" : "ltr"}
      >
        <TabsList className="grid w-full grid-cols-3 max-w-lg text-start">
          <TabsTrigger value="settings">{t("pages.balebusiness.تنظیمات")}</TabsTrigger>
          <TabsTrigger value="webhook">{t("pages.balebusiness.وبهوک")}</TabsTrigger>
          <TabsTrigger value="logs">{t("pages.balebusiness.لاگها")}</TabsTrigger>
        </TabsList>

        <TabsContent value="settings" className="space-y-4 mt-4">
          <BaleSettingsTab
            settings={settings}
            saving={saving}
            onChange={handleChange}
            onSave={() => void saveSettings()}
            t={t}
          />
        </TabsContent>

        <TabsContent value="webhook" className="mt-4">
          <BaleWebhookTab
            kpi={kpi}
            botStats={botStats}
            webhookUrl={webhookUrl}
            webhookNote={webhookNote}
            webhookInfoJson={webhookInfoJson}
            stats={stats}
            campaigns={campaigns}
            diagLoading={diagLoading}
            onSetWebhook={() => void runSetWebhook()}
            onWebhookInfo={() => void runWebhookInfo()}
            onTestLog={() => void runTestLog()}
            onCampaignCreated={() => void loadAll()}
            t={t}
          />
        </TabsContent>

        <TabsContent value="logs" className="mt-4">
          <BaleLogsTab
            logs={logs}
            onRefresh={() => void loadAll()}
            formatDateTime={formatDateTime}
            t={t}
          />
        </TabsContent>
      </Tabs>
    </CrmPageLayout>
  )
}
