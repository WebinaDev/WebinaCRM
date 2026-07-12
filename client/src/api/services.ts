import { crmGet, crmPost } from "./client"

export interface Subscription {
  id: number
  customer_id: number
  customer_name: string
  product_id: number
  product_name: string
  status: string
  status_name?: string
  start_date: string
  end_date: string
  total_formatted?: string
  next_payment?: string
  already_converted?: boolean
}

export interface ListSubscriptionsResponse {
  subscriptions: Subscription[]
  wc_subscriptions_active?: boolean
}

export interface WcProduct {
  id: number
  name: string
  type: string
  price: string
  task_template_id?: number | null
  task_template_title?: string
  service_task_type?: string
}
export type Product = WcProduct

export interface ListProductsResponse {
  products: WcProduct[]
  wc_active: boolean
  task_templates?: TaskTemplate[]
}

export interface ConvertResponse {
  message?: string
  contract_id?: number
}

export interface TaskTemplate {
  id: number
  title: string
  is_recurring?: boolean
  recurring_type?: string
}

export interface UpdateProductTaskTemplateResponse {
  message?: string
}

export async function listSubscriptions() {
  return crmGet<ListSubscriptionsResponse>("services/subscriptions")
}

export async function listProducts() {
  return crmGet<ListProductsResponse>("services/products")
}

export async function convertSubscriptionToContract(subscriptionId: number) {
  return crmPost<ConvertResponse>(`services/subscriptions/${subscriptionId}/convert-contract`, {
    subscription_id: String(subscriptionId),
  })
}

export async function updateProductTaskTemplate(params: {
  product_id: number
  task_template_id: number | null
  service_task_type?: string
}) {
  return crmPost<UpdateProductTaskTemplateResponse>(`services/products/${params.product_id}/task-template`, {
    product_id: String(params.product_id),
    task_template_id: params.task_template_id == null ? "" : String(params.task_template_id),
    service_task_type: params.service_task_type ?? "onetime",
  })
}

export async function getTaskTemplates() {
  return crmGet<{ task_templates: TaskTemplate[] }>("services/task-templates")
}
