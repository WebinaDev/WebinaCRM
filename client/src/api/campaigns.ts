import { crmDelete, crmGet, crmPost } from "./client"

export interface Campaign {
  id: number
  title: string
  description: string
  channel: string
  channel_label: string
  status: string
  status_label: string
  budget: number
  start_date: string
  end_date: string
  start_date_display: string
  end_date_display: string
  lead_count: number
  created: string
}

export interface GetCampaignsResponse {
  campaigns: Campaign[]
  channels: { slug: string; name: string }[]
  statuses: { slug: string; name: string }[]
  total_pages: number
  total: number
}

export async function getCampaigns(params?: {
  search?: string
  status_filter?: string
  channel_filter?: string
  paged?: number
}) {
  return crmGet<GetCampaignsResponse>("campaigns", {
    search: params?.search,
    status_filter: params?.status_filter,
    channel_filter: params?.channel_filter,
    paged: params?.paged ?? 1,
  })
}

export async function manageCampaign(data: {
  campaign_id?: number
  title: string
  description?: string
  channel: string
  status: string
  budget?: number
  start_date?: string
  end_date?: string
}) {
  return crmPost<{ message?: string; campaign?: Campaign }>("campaigns", {
    campaign_id: data.campaign_id ?? 0,
    title: data.title,
    description: data.description ?? "",
    channel: data.channel,
    status: data.status,
    budget: data.budget ?? 0,
    start_date: data.start_date ?? "",
    end_date: data.end_date ?? "",
  })
}

export async function deleteCampaign(campaignId: number, unlinkLeads = false) {
  return crmDelete<{ message?: string; lead_count?: number }>(`campaigns/${campaignId}`, {
    campaign_id: campaignId,
    unlink_leads: unlinkLeads ? "1" : "0",
  })
}
