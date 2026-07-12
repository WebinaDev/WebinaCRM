import { crmGet, crmPost, crmPostForm } from "./client"

export interface Invoice {
  id: number
  invoice_number: string
  customer_id: number
  customer_name: string
  project_id: number
  project_title: string
  final_total: number
  issue_date: string
  date_display: string
}

export interface InvoiceItem {
  title: string
  desc: string
  price: number
  discount: number
}

export interface InvoiceDetail {
  id: number
  customer_id: number
  project_id: number
  invoice_number: string
  issue_date: string
  issue_date_jalali: string
  items: InvoiceItem[]
  payment_method: string
  notes: string
}

export interface GetInvoicesResponse {
  invoices: Invoice[]
  customers: { id: number; display_name: string }[]
  total_pages: number
  current_page: number
}

export interface GetInvoiceResponse {
  invoice: InvoiceDetail
  customers: { id: number; display_name: string }[]
  projects: { id: number; title: string }[]
}

export async function getInvoices(paged = 1) {
  return crmGet<GetInvoicesResponse>("invoices", { paged })
}

export async function getInvoice(invoiceId: number) {
  return crmGet<GetInvoiceResponse>(`invoices/${invoiceId}`, {
    invoice_id: String(invoiceId),
  })
}

export async function getProjectsForCustomer(customerId: number) {
  return crmGet<{ id: number; title: string }[]>(`customers/${customerId}/projects`, {
    customer_id: String(customerId),
  })
}

export async function manageInvoice(data: {
  invoice_id?: number
  customer_id: number
  project_id: number
  issue_date: string
  item_title: string[]
  item_desc: string[]
  item_price: string[]
  item_discount: string[]
  payment_method: string
  notes: string
}) {
  const form = new FormData()
  form.set("invoice_id", String(data.invoice_id ?? 0))
  form.set("customer_id", String(data.customer_id))
  form.set("project_id", String(data.project_id))
  form.set("issue_date", data.issue_date)
  form.set("payment_method", data.payment_method)
  form.set("notes", data.notes)
  data.item_title.forEach((v, i) => {
    form.append("item_title[]", v)
    form.append("item_desc[]", data.item_desc[i] ?? "")
    form.append("item_price[]", data.item_price[i] ?? "0")
    form.append("item_discount[]", data.item_discount[i] ?? "0")
  })
  return crmPostForm<{ message?: string; reload?: boolean }>("invoices", form)
}

export async function downloadInvoicePdf(invoiceId: number) {
  return crmPost<{ pdf_url?: string; filename?: string; message?: string }>(
    `invoices/${invoiceId}/pdf`,
    { invoice_id: invoiceId },
  )
}

export async function sendInvoiceEmail(invoiceId: number, email?: string) {
  return crmPost<{ message?: string }>(`invoices/${invoiceId}/email`, {
    invoice_id: invoiceId,
    email: email ?? "",
  })
}
