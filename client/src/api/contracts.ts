import { crmDelete, crmGet, crmPost, crmPostForm } from "./client"

export interface Contract {
  id: number
  contract_number: string
  title: string
  customer_id: number
  customer_name: string
  customer_email: string
  total_amount: number
  paid_amount: number
  pending_amount: number
  installment_count: number
  paid_count: number
  pending_count: number
  status_class: string
  status_text: string
  start_date: string
  start_date_jalali: string
  payment_percentage: number
  is_cancelled: boolean
  delete_nonce: string
}

export interface ContractDetail {
  id: number
  title: string
  customer_id: number
  contract_number: string
  start_date: string
  start_date_jalali: string
  total_amount: string
  total_installments: number
  duration: string
  subscription_model: string
  installments: { amount: string; due_date: string; due_date_gregorian: string; status: string }[]
  is_cancelled: boolean
  delete_nonce: string
}

export interface GetContractsResponse {
  contracts: Contract[]
  customers: { id: number; display_name: string }[]
  total_pages: number
  current_page: number
}

export interface GetContractResponse {
  contract: ContractDetail
  related_projects: { id: number; title: string }[]
  customers: { id: number; display_name: string }[]
  durations: { value: string; label: string }[]
  models: { value: string; label: string }[]
}

export async function getContracts(params?: {
  s?: string
  customer_filter?: number
  payment_status?: string
  paged?: number
}) {
  return crmGet<GetContractsResponse>("contracts", {
    s: params?.s,
    customer_filter: params?.customer_filter,
    payment_status: params?.payment_status,
    paged: params?.paged ?? 1,
  })
}

export async function getContract(contractId: number) {
  return crmGet<GetContractResponse>(`contracts/${contractId}`)
}

export interface ProjectTemplate {
  id: number
  title: string
}

export async function getProjectTemplates() {
  return crmGet<{ templates: ProjectTemplate[] }>("project-templates")
}

export interface WcProduct {
  id: number
  name: string
  type: string
  price: string
}

export async function getContractProducts() {
  return crmGet<{ products: WcProduct[]; wc_active: boolean }>("services/products")
}

export async function getProjectAssignees(search?: string) {
  return crmGet<{ users: { id: number; display_name: string }[] }>("projects/meta/assignees", {
    search: search ?? "",
  })
}

export async function getProductProjectsPreview(productId: number) {
  return crmGet<{ titles: string[] }>("products/projects-preview", {
    product_id: productId,
  })
}

export async function manageContract(data: {
  contract_id?: number
  customer_id: number
  _project_start_date: string
  contract_title?: string
  total_amount: string
  total_installments: number
  _project_contract_duration: string
  _project_subscription_model: string
  payment_amount: string[]
  payment_due_date: string[]
  payment_status: string[]
  project_template_id?: number
  product_id?: number
  project_assignments?: number[]
  lead_id?: number
}) {
  const form = new FormData()
  form.set("contract_id", String(data.contract_id ?? 0))
  if (data.lead_id != null && data.lead_id > 0) {
    form.set("lead_id", String(data.lead_id))
  }
  form.set("customer_id", String(data.customer_id))
  form.set("_project_start_date", data._project_start_date)
  form.set("contract_title", data.contract_title ?? "")
  form.set("total_amount", data.total_amount)
  form.set("total_installments", String(data.total_installments))
  form.set("_project_contract_duration", data._project_contract_duration)
  form.set("_project_subscription_model", data._project_subscription_model)
  data.payment_amount.forEach((v, i) => {
    form.append("payment_amount[]", v)
    form.append("payment_due_date[]", data.payment_due_date[i] ?? "")
    form.append("payment_status[]", data.payment_status[i] ?? "pending")
  })
  if (data.project_template_id != null && data.project_template_id > 0) {
    form.set("project_template_id", String(data.project_template_id))
  }
  if (data.product_id != null && data.product_id > 0) {
    form.set("product_id", String(data.product_id))
    if (data.project_assignments?.length) {
      data.project_assignments.forEach((uid) => form.append("project_assignments[]", String(uid)))
    }
  }
  return crmPostForm<{ message?: string; reload?: boolean; contract_id?: number }>(data.contract_id ? `contracts/${data.contract_id}` : "contracts", form, undefined, data.contract_id ? "PATCH" : "POST")
}

export async function deleteContract(contractId: number, nonce: string) {
  return crmDelete<{ message?: string }>(`contracts/${contractId}`, {
    contract_id: String(contractId),
    nonce,
  })
}

export async function cancelContract(contractId: number, reason?: string) {
  return crmPost<{ message?: string }>(`contracts/${contractId}/cancel`, {
    contract_id: String(contractId),
    reason: reason ?? "دلیلی ذکر نشده است.",
  })
}

export async function addProjectToContract(contractId: number, projectTitle: string) {
  return crmPost<{ message?: string; project_id?: number }>(`contracts/${contractId}/projects`, {
    contract_id: String(contractId),
    project_title: projectTitle,
  })
}
