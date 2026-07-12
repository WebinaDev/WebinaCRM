import { crmDelete, crmGet, crmPatch, crmPost } from "./client"

export interface License {
  id: number
  project_name: string
  domain: string
  remaining_percentage: number
  remaining_days: number | null
  expiry_date: string | null
  logo_url?: string
  card_color: string
  status: "active" | "inactive" | "expired" | "cancelled"
}

export interface GetLicensesResponse {
  licenses: License[]
}

export async function getLicenses() {
  return crmGet<GetLicensesResponse>("licenses")
}

export async function addLicense(data: {
  project_name: string
  domain: string
  start_date?: string
  expiry_date?: string
  logo_url?: string
  status?: string
}) {
  return crmPost<{ message?: string; license?: License }>("licenses", {
    project_name: data.project_name,
    domain: data.domain,
    start_date: data.start_date ?? new Date().toISOString().slice(0, 10),
    expiry_date: data.expiry_date ?? "",
    logo_url: data.logo_url ?? "",
    status: data.status ?? "active",
  })
}

export async function updateLicense(data: {
  id: number
  project_name?: string
  domain?: string
  expiry_date?: string
  start_date?: string
  logo_url?: string
  status?: string
}) {
  return crmPatch<{ message?: string }>(`licenses/${data.id}`, {
    id: String(data.id),
    project_name: data.project_name,
    domain: data.domain,
    expiry_date: data.expiry_date,
    start_date: data.start_date,
    logo_url: data.logo_url,
    status: data.status,
  })
}

export async function renewLicense(id: number, expiry_date: string) {
  return crmPost<{ message?: string }>(`licenses/${id}/renew`, {
    id: String(id),
    expiry_date,
  })
}

export async function cancelLicense(id: number) {
  return crmPost<{ message?: string }>(`licenses/${id}/cancel`, {
    id: String(id),
  })
}

export async function deleteLicense(id: number) {
  return crmDelete<{ message?: string }>(`licenses/${id}`, {
    id: String(id),
  })
}
