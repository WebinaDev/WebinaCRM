import { useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Webhook } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import type { BaleBotStats, BaleKpi } from "./types"
import { createAndRunCampaign, runBaleCampaign } from "./api"

const SEGMENTS = [
  { value: "newcomer", labelKey: "pages.balebusiness.segment_newcomer" },
  { value: "hot-leads", labelKey: "pages.balebusiness.segment_hot_leads" },
  { value: "past-buyers", labelKey: "pages.balebusiness.segment_past_buyers" },
  { value: "inactive-30d", labelKey: "pages.balebusiness.segment_inactive_30d" },
] as const

type Props = {
  kpi: BaleKpi | null
  botStats: BaleBotStats | null
  webhookUrl: string
  webhookNote: string
  webhookInfoJson: string
  stats: { support_opened: number; support_item_clicked: number } | null
  campaigns: Array<Record<string, unknown>>
  diagLoading: boolean
  onSetWebhook: () => void
  onWebhookInfo: () => void
  onTestLog: () => void
  onCampaignCreated: () => void
  t: (key: string, opts?: Record<string, unknown>) => string
}

export function BaleWebhookTab({
  kpi,
  botStats,
  webhookUrl,
  webhookNote,
  webhookInfoJson,
  stats,
  campaigns,
  diagLoading,
  onSetWebhook,
  onWebhookInfo,
  onTestLog,
  onCampaignCreated,
  t,
}: Props) {
  const { isRtl } = useLocale()
  const [campaignName, setCampaignName] = useState("")
  const [campaignSegment, setCampaignSegment] = useState("newcomer")
  const [campaignVariant, setCampaignVariant] = useState("A")
  const [campaignMessage, setCampaignMessage] = useState("")

  const handleCreateCampaign = async () => {
    if (!campaignName.trim() || !campaignMessage.trim()) return
    const createRes = await createAndRunCampaign({
      name: campaignName,
      segment_key: campaignSegment,
      variant: campaignVariant,
      message_template: campaignMessage,
    })
    if (createRes.ok && createRes.data?.id) {
      await runBaleCampaign(createRes.data.id)
      onCampaignCreated()
    }
  }

  return (
    <div className="space-y-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
      {kpi ? (
        <Card className="text-start">
          <CardHeader className="text-start">
            <CardTitle>{t("pages.balebusiness.KPI_تبدیل_و_نگهداشت")}</CardTitle>
          </CardHeader>
          <CardContent className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm text-start">
            <div>{t("pages.balebusiness.kpi_start_lead", { value: kpi.start_to_lead_rate })}</div>
            <div>{t("pages.balebusiness.kpi_lead_customer", { value: kpi.lead_to_customer_rate })}</div>
            <div>{t("pages.balebusiness.kpi_first_response", { value: kpi.first_response_minutes })}</div>
            <div>{t("pages.balebusiness.kpi_retention", { value: kpi.retention_rate })}</div>
            <div>{t("pages.balebusiness.kpi_campaign_revenue", { value: kpi.campaign_revenue_impact })}</div>
            <div>{t("pages.balebusiness.kpi_dropoff", { value: JSON.stringify(kpi.funnel_dropoff) })}</div>
          </CardContent>
        </Card>
      ) : null}

      {botStats ? (
        <Card>
          <CardHeader>
            <CardTitle>{t("pages.balebusiness.آمار_کامل_ربات")}</CardTitle>
          </CardHeader>
          <CardContent className="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 text-sm">
            <div>{t("pages.balebusiness.stats_started_users", { value: botStats.started_users })}</div>
            <div>{t("pages.balebusiness.stats_total_users", { value: botStats.total_users })}</div>
            <div>{t("pages.balebusiness.stats_total_businesses", { value: botStats.total_businesses })}</div>
            <div>{t("pages.balebusiness.stats_total_events", { value: botStats.total_events })}</div>
            <div>{t("pages.balebusiness.stats_total_logs", { value: botStats.total_logs })}</div>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Webhook className="h-5 w-5" />
            {t("pages.balebusiness.آدرس_وبهوک")}
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {webhookNote ? <p className="text-sm text-muted-foreground">{webhookNote}</p> : null}
          <code className="block p-3 rounded-md bg-muted text-sm break-all">{webhookUrl}</code>
          <div className="flex flex-wrap gap-2">
            <Button type="button" disabled={diagLoading} onClick={onSetWebhook}>
              {t("pages.balebusiness.setWebhook")}
            </Button>
            <Button type="button" variant="secondary" disabled={diagLoading} onClick={onWebhookInfo}>
              {t("pages.balebusiness.webhookInfo")}
            </Button>
            <Button type="button" variant="outline" disabled={diagLoading} onClick={onTestLog}>
              {t("pages.balebusiness.testLog")}
            </Button>
          </div>
          {stats ? (
            <div className="text-sm space-y-1 pt-2 border-t">
              <p>{t("pages.balebusiness.stats_support_opened", { value: stats.support_opened })}</p>
              <p>{t("pages.balebusiness.stats_support_clicked", { value: stats.support_item_clicked })}</p>
            </div>
          ) : null}
          {webhookInfoJson ? (
            <pre className="text-xs p-3 rounded-md bg-muted overflow-auto max-h-64">{webhookInfoJson}</pre>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t("pages.balebusiness.کمپینهای_سگمنتAB")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="grid md:grid-cols-2 gap-3">
            <Input
              value={campaignName}
              onChange={(e) => setCampaignName(e.target.value)}
              placeholder={t("pages.balebusiness.نام_کمپین")}
            />
            <Select value={campaignSegment} onValueChange={setCampaignSegment}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {SEGMENTS.map((s) => (
                  <SelectItem key={s.value} value={s.value}>
                    {t(s.labelKey)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="grid md:grid-cols-2 gap-3">
            <Select value={campaignVariant} onValueChange={setCampaignVariant}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="A">{t("pages.balebusiness.variant_a")}</SelectItem>
                <SelectItem value="B">{t("pages.balebusiness.variant_b")}</SelectItem>
              </SelectContent>
            </Select>
            <Input
              value={campaignMessage}
              onChange={(e) => setCampaignMessage(e.target.value)}
              placeholder={t("pages.balebusiness.متن_کمپین")}
            />
          </div>
          <Button type="button" onClick={() => void handleCreateCampaign()}>
            {t("pages.balebusiness.ایجاد_و_اجرای_کمپین")}
          </Button>
          <div className="text-xs bg-muted rounded-md p-3 max-h-56 overflow-auto">
            <pre>{JSON.stringify(campaigns, null, 2)}</pre>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
