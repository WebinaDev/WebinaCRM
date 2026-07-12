/**
 * Warehouse REST API (webinocrm/v1/warehouses/*).
 */

import { crmGet, crmPost, type AjaxResponse } from "./client"

export interface Warehouse {
  id: number
  name: string
  code: string
  description: string
  location: string
  is_default: boolean
  is_active: boolean
  created_at: string
}

export interface WarehouseListResponse {
  items?: Warehouse[]
  total?: number
}

export async function listWarehouses(params: {
  search?: string
  page?: number
  per_page?: number
}): Promise<AjaxResponse<WarehouseListResponse>> {
  const res = await crmGet<Warehouse[] | WarehouseListResponse>("warehouses", params)
  if (!res.success) return { ...res, data: undefined }
  const data = res.data
  if (Array.isArray(data)) {
    return { ...res, data: { items: data, total: res.total ?? data.length } }
  }
  return { ...res, data: data ?? { items: [], total: 0 } }
}

export async function createWarehouse(payload: {
  name: string
  code: string
  description?: string
  location?: string
  is_default?: boolean
  is_active?: boolean
}) {
  return crmPost<{ id?: number }>("warehouses/create", payload as Record<string, string | number | boolean>)
}

export async function updateWarehouse(payload: {
  id: number
  name: string
  code: string
  description?: string
  location?: string
  is_default?: boolean
  is_active?: boolean
}) {
  return crmPost("warehouses/update", payload as Record<string, string | number | boolean>)
}

export async function deleteWarehouse(id: number) {
  return crmPost("warehouses/delete", { id })
}

export async function listWarehouseStock(params: {
  warehouse_id?: number
  product_id?: number
  search?: string
  page?: number
  per_page?: number
}) {
  return crmGet<unknown>("warehouse/stock", params)
}

export async function listWarehouseInbound(params: Record<string, string | number>) {
  return crmGet<unknown>("warehouse/inbound", params)
}

export async function getWarehouseInbound(id: number) {
  return crmGet<unknown>(`warehouse/inbound/${id}`)
}

export async function createWarehouseInbound(payload: Record<string, unknown>) {
  return crmPost("warehouse/inbound/create", payload as Record<string, string | number>)
}

export async function postWarehouseInbound(id: number) {
  return crmPost("warehouse/inbound/post", { id })
}

export async function listWarehouseOutbound(params: Record<string, string | number>) {
  return crmGet<unknown>("warehouse/outbound", params)
}

export async function getWarehouseOutbound(id: number) {
  return crmGet<unknown>(`warehouse/outbound/${id}`)
}

export async function createWarehouseOutbound(payload: Record<string, unknown>) {
  return crmPost("warehouse/outbound/create", payload as Record<string, string | number>)
}

export async function postWarehouseOutbound(id: number) {
  return crmPost("warehouse/outbound/post", { id })
}

export async function listWarehouseAudits(params: Record<string, string | number>) {
  return crmGet<unknown>("warehouse/audit", params)
}

export async function getWarehouseAudit(id: number) {
  return crmGet<unknown>(`warehouse/audit/${id}`)
}

export async function createWarehouseAudit(payload: Record<string, unknown>) {
  return crmPost("warehouse/audit/create", payload as Record<string, string | number>)
}

export async function recordWarehouseAuditLine(payload: Record<string, unknown>) {
  return crmPost("warehouse/audit/record", payload as Record<string, string | number>)
}

export async function completeWarehouseAudit(id: number) {
  return crmPost("warehouse/audit/complete", { id })
}

export async function postWarehouseAudit(id: number) {
  return crmPost("warehouse/audit/post", { id })
}

export async function listProducts(params: { per_page?: number; search?: string }) {
  return crmGet<unknown>("products", params)
}

/** Normalize list payloads ({ items } or bare array) from REST. */
export function unwrapListPayload<T>(data: unknown): T[] {
  if (Array.isArray(data)) return data as T[]
  if (data && typeof data === "object" && "items" in data) {
    const items = (data as WarehouseListResponse).items
    if (Array.isArray(items)) return items as T[]
  }
  return []
}

export async function getProductStock(warehouseId: number, productId: number) {
  return crmGet<unknown>(`warehouse/stock/${warehouseId}/${productId}`)
}
