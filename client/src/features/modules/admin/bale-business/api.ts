import { restGetJson, restPostJson } from "@/api/client"
import type { BaleBotStats, BaleKpi, BaleLoadResult, BaleLogRow, BaleSettings } from "./types"

export async function loadBaleData(): Promise<{
  ok: boolean
  data?: BaleLoadResult
  message?: string
}> {
  const [sRes, uRes, lRes, stRes, bsRes, kpiRes, campaignRes] = await Promise.all([
    restGetJson<BaleSettings>("bale/settings"),
    restGetJson<{ url: string; message?: string }>("bale/webhook-url"),
    restGetJson<{ logs: BaleLogRow[] }>("bale/logs?limit=80"),
    restGetJson<{ support_opened: number; support_item_clicked: number }>("bale/diagnostics/stats"),
    restGetJson<BaleBotStats>("bale/stats"),
    restGetJson<BaleKpi>("bale/kpi"),
    restGetJson<{ campaigns: Array<Record<string, unknown>> }>("bale/campaigns"),
  ])

  if (!sRes.ok) {
    return { ok: false, message: sRes.message }
  }

  return {
    ok: true,
    data: {
      settings: sRes.data ?? null,
      webhookUrl: uRes.ok && uRes.data ? (uRes.data.url ?? "") : "",
      webhookNote: uRes.ok && uRes.data ? (uRes.data.message ?? "") : "",
      logs: lRes.ok && lRes.data?.logs ? lRes.data.logs : [],
      stats: stRes.ok && stRes.data ? stRes.data : null,
      botStats: bsRes.ok && bsRes.data ? bsRes.data : null,
      kpi: kpiRes.ok && kpiRes.data ? kpiRes.data : null,
      campaigns: campaignRes.ok && campaignRes.data?.campaigns ? campaignRes.data.campaigns : [],
    },
  }
}

export function saveBaleSettings(settings: BaleSettings) {
  return restPostJson<BaleSettings>("bale/settings", settings)
}

export function setBaleWebhook() {
  return restPostJson<unknown>("bale/set-webhook", {})
}

export function getBaleWebhookInfo() {
  return restPostJson<{ webhook_info: unknown }>("bale/diagnostics/webhook-info", {})
}

export function postBaleTestLog() {
  return restPostJson("bale/diagnostics/test-log", {})
}

export function createAndRunCampaign(payload: {
  name: string
  segment_key: string
  variant: string
  message_template: string
}) {
  return restPostJson<{ id: number }>("bale/campaigns", payload)
}

export function runBaleCampaign(id: number) {
  return restPostJson(`bale/campaigns/${id}/run`, {})
}

export function getBaleUserLogs(chatId: string) {
  return restGetJson<{
    events: Array<Record<string, string>>
    logs: Array<Record<string, string>>
  }>(`bale/user-logs?chat_id=${encodeURIComponent(chatId)}&limit=80`)
}
