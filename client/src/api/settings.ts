/**
 * Settings API for dashboard (get/save by tab)
 */

import { crmDelete, crmGet, crmPost, crmPostForm, type AjaxResponse } from "./client"

export type SettingsTab =
  | "authentication"
  | "visitor_tracking"
  | "style"
  | "payment"
  | "sms"
  | "modirpayamak"
  | "notifications"
  | "workflow"
  | "automations"
  | "positions"
  | "task_categories"
  | "canned_responses"
  | "forms"
  | "leads"
  | "crm_core_update"

export interface AuthSettings {
  login_phone_pattern: string
  login_phone_length: number
  login_sms_template: string
  otp_expiry_minutes: number
  otp_max_attempts: number
  otp_length: number
  enable_password_login: boolean
  enable_sms_login: boolean
  login_redirect_url: string
  logout_redirect_url: string
  force_logout_inactive: boolean
  inactive_timeout_minutes: number
}

export interface StyleSettings {
  primary_color: string
  font_family: string
  font_size: number
}

export interface PaymentSettings {
  zarinpal_merchant: string
  zarinpal_sandbox: boolean
}

export interface SmsSettings {
  sms_service: string
  sms_username: string
  sms_password: string
  sms_sender: string
}

export interface NotificationSettings {
  telegram_bot_token: string
  telegram_chat_id: string
}

export interface CannedResponseItem {
  id: number
  title: string
  content: string
}

export interface PositionItem {
  id: number
  title: string
  permissions: string[]
}

export interface TaskCategoryItem {
  id: number
  name: string
  slug: string
  color: string
}

export async function getSettings(
  tab: SettingsTab
): Promise<AjaxResponse<Record<string, unknown> & { items?: unknown[] }>> {
  return crmGet("settings/crm", { settings_tab: tab })
}

export async function saveSettings(
  tab: string,
  params: Record<string, string | number | boolean | undefined>
): Promise<AjaxResponse<{ message?: string; reload?: boolean }>> {
  return crmPost("settings/crm", { settings_tab: tab, ...params })
}

export async function saveAuthSettings(
  params: Record<string, string | number | boolean | undefined>
): Promise<AjaxResponse<{ message?: string }>> {
  return crmPost("settings/crm/auth", params)
}

export async function manageCannedResponse(params: {
  response_id?: number
  response_title: string
  response_content: string
}): Promise<AjaxResponse<{ message?: string }>> {
  const form: Record<string, string> = {
    response_title: params.response_title,
    response_content: params.response_content,
  }
  if (params.response_id) form.response_id = String(params.response_id)
  return crmPost("settings/canned-responses", form)
}

export async function deleteCannedResponse(responseId: number): Promise<AjaxResponse<{ message?: string }>> {
  return crmDelete(`settings/canned-responses/${responseId}`, { response_id: String(responseId) })
}

export async function managePosition(params: {
  position_id?: number
  position_title: string
  position_permissions?: string[]
}): Promise<AjaxResponse<{ message?: string }>> {
  const formData = new FormData()
  formData.set("position_title", params.position_title)
  if (params.position_id) formData.set("position_id", String(params.position_id))
  ;(params.position_permissions ?? []).forEach((p) => formData.append("position_permissions[]", p))
  return crmPostForm("settings/positions", formData)
}

export async function deletePosition(positionId: number): Promise<AjaxResponse<{ message?: string }>> {
  return crmDelete(`settings/positions/${positionId}`, { position_id: String(positionId) })
}

export async function manageTaskCategory(params: {
  category_id?: number
  category_name: string
  category_color?: string
}): Promise<AjaxResponse<{ message?: string }>> {
  const form: Record<string, string> = {
    category_name: params.category_name,
    category_color: params.category_color ?? "#845adf",
  }
  if (params.category_id) form.category_id = String(params.category_id)
  return crmPost("settings/task-categories", form)
}

export async function deleteTaskCategory(categoryId: number): Promise<AjaxResponse<{ message?: string }>> {
  return crmDelete(`settings/task-categories/${categoryId}`, { category_id: String(categoryId) })
}
