import { crmDelete, crmGet, crmPost, crmPostForm } from "./client"

export interface CustomerUser {
  id: number
  display_name: string
  first_name: string
  last_name: string
  email: string
  phone: string
  role_slug: string
  role_name: string
  department_name?: string
  job_title_name?: string
  department_id?: number
  job_title_id?: number
  registered: string
  registered_jalali: string
  avatar_url: string
  delete_nonce: string
  can_delete: boolean
}

export interface GetUsersResponse {
  users: CustomerUser[]
  stats: { total: number; customers: number; staff: number }
  roles: { slug: string; name: string }[]
  departments?: { id: number; name: string }[]
  job_titles?: { id: number; name: string; parent: number }[]
  total?: number
  total_pages?: number
  current_page?: number
  per_page?: number
}

export interface Customer360Data {
  customer: {
    id: number
    display_name: string
    email: string
    phone: string
    registered: string
    avatar_url: string
  }
  stats: {
    projects_count: number
    contracts_count: number
    tickets_count: number
    total_revenue: number
  }
  projects: { id: number; title: string; date: string }[]
  contracts: { id: number; title: string; date: string; amount: number }[]
  tickets: { id: number; title: string; date: string }[]
}

export async function getUsers(params?: {
  search?: string
  role?: string
  paged?: number
  per_page?: number
}) {
  return crmGet<GetUsersResponse>("customers", {
    search: params?.search,
    role: params?.role,
    paged: params?.paged,
    per_page: params?.per_page,
  })
}

export async function getCustomer360(customerId: number) {
  return crmGet<Customer360Data>(`customers/${customerId}/360`)
}

export async function exportCustomers() {
  return crmGet<{ file_url: string; filename: string; message?: string }>("customers/export")
}

export type ImportCsvResult = {
  message: string
  imported: number
  errors?: string[]
}

export async function importCustomers(file: File) {
  const formData = new FormData()
  formData.append("import_file", file)
  return crmPostForm<ImportCsvResult>("customers/import", formData)
}

export async function manageUser(data: {
  user_id?: number
  first_name: string
  last_name: string
  email: string
  webino_mobile_phone?: string
  role: string
  password?: string
  department?: number
  job_title?: number
}) {
  const body: Record<string, string | number> = {
    user_id: data.user_id ?? 0,
    first_name: data.first_name,
    last_name: data.last_name,
    email: data.email,
    webino_mobile_phone: data.webino_mobile_phone ?? "",
    role: data.role,
    password: data.password ?? "",
  }
  if (data.department !== undefined) body.department = data.department
  if (data.job_title !== undefined) body.job_title = data.job_title
  return crmPost<{ message?: string; reload?: boolean }>("customers", body)
}

export async function manageOrgPosition(data: {
  term_id?: number
  name: string
  parent_id?: number
}) {
  return crmPost<{ message?: string; term?: { id: number; name: string; parent: number } }>(
    "customers/org-positions",
    {
      term_id: data.term_id ?? 0,
      name: data.name,
      parent_id: data.parent_id ?? 0,
    },
  )
}

export async function deleteOrgPosition(termId: number) {
  return crmDelete<{ message?: string }>(`customers/org-positions/${termId}`, {
    term_id: termId,
  })
}

export async function deleteUser(userId: number, nonce: string) {
  return crmDelete<{ message?: string }>(`customers/${userId}`, {
    user_id: String(userId),
    nonce,
  })
}

export async function sendCustomSms(userId: number, message: string) {
  return crmPost<{ message?: string }>("customers/sms", {
    user_id: String(userId),
    message,
  })
}

export async function sendBaleMessage(userId: number, message: string) {
  return crmPost<{ message?: string }>("customers/bale", {
    user_id: String(userId),
    message,
  })
}

export async function sendBaleBulkMessage(data: {
  message: string
  mode: "all" | "filtered"
  roles?: string[]
}) {
  const rolesCsv = (data.roles ?? []).join(",")
  return crmPost<{ message?: string; total?: number; sent?: number; failed?: number }>(
    "customers/bale/bulk",
    {
      message: data.message,
      mode: data.mode,
      roles: rolesCsv,
    },
  )
}
