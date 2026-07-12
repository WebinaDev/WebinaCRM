import { crmDelete, crmGet, crmPatch, crmPost, crmPostForm } from "./client"

export interface FormSubmissionField {
  label: string
  value: string
}

export interface Lead {
  id: number
  first_name: string
  last_name: string
  mobile: string
  email?: string
  business_name?: string
  gender?: string
  status_name: string
  status_slug: string
  date: string
  assigned_to?: number
  assigned_to_name?: string
  last_assignment_note?: string
  lead_source_slug?: string
  lead_source_name?: string
  campaign_id?: number
  notes?: string
  form_submission_data?: FormSubmissionField[]
}

export interface GetLeadsResponse {
  leads: Lead[]
  total: number
  pages: number
  statuses: { slug: string; name: string }[]
  lead_sources?: { slug: string; name: string }[]
  campaigns?: { id: number; title: string }[]
}

export async function getLeads(params: {
  paged?: number
  per_page?: number
  s?: string
  status_filter?: string
}) {
  const res = await crmGet<GetLeadsResponse>("leads", params)
  return res
}

export async function addLead(data: {
  first_name: string
  last_name: string
  mobile: string
  email?: string
  business_name?: string
  gender?: string
  notes?: string
  lead_source?: string
  campaign_id?: number
}) {
  const payload: Record<string, string | number> = {
    first_name: data.first_name,
    last_name: data.last_name,
    mobile: data.mobile,
  }
  if (data.email?.trim()) payload.email = data.email.trim()
  if (data.business_name?.trim()) payload.business_name = data.business_name.trim()
  if (data.gender?.trim()) payload.gender = data.gender.trim()
  if (data.notes?.trim()) payload.notes = data.notes.trim()
  if (data.lead_source?.trim()) payload.lead_source = data.lead_source.trim()
  if (data.campaign_id != null && data.campaign_id > 0) payload.campaign_id = data.campaign_id
  return crmPost("leads", payload)
}

export async function assignLead(leadId: number, assignedTo: number, note?: string) {
  const payload: Record<string, string | number> = {
    lead_id: leadId,
    assigned_to: assignedTo,
  }
  if (note?.trim()) payload.note = note.trim()
  return crmPost(`leads/${leadId}/assign`, payload)
}

export async function getLeadAssignees(search?: string) {
  return crmGet<{ users: { id: number; display_name: string }[] }>("leads/meta/assignees", {
    search: search ?? "",
  })
}

export async function editLead(leadId: number, data: Record<string, string | number | undefined>) {
  const payload: Record<string, string | number> = { lead_id: String(leadId) }
  for (const [k, v] of Object.entries(data)) {
    if (v !== undefined && v !== "") payload[k] = v as string | number
  }
  return crmPatch(`leads/${leadId}`, payload)
}

export async function deleteLead(leadId: number) {
  return crmDelete(`leads/${leadId}`, { lead_id: String(leadId) })
}

export async function changeLeadStatus(leadId: number, newStatus: string) {
  return crmPost(`leads/${leadId}/status`, {
    lead_id: String(leadId),
    new_status: newStatus,
  })
}

export interface LeadForContract {
  lead_id: number
  first_name: string
  last_name: string
  mobile: string
  email: string
  business_name: string
  existing_customer_id: number
}

export async function getLeadForContract(leadId: number) {
  return crmGet<LeadForContract>(`leads/${leadId}/for-contract`, { lead_id: leadId })
}

export async function createCustomerFromLead(leadId: number) {
  return crmPost<{ customer_id: number; message?: string }>(`leads/${leadId}/create-customer`, {
    lead_id: leadId,
  })
}

export async function exportLeads() {
  return crmGet<{ file_url: string; filename: string; message?: string }>("leads/export")
}

export type ImportCsvResult = {
  message: string
  imported: number
  errors?: string[]
}

export async function importLeads(file: File) {
  const formData = new FormData()
  formData.append("import_file", file)
  return crmPostForm<ImportCsvResult>("leads/import", formData)
}
