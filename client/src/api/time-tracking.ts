import { crmDelete, crmGet, crmPost } from "./client"

export interface TimeEntry {
  id: number
  user_id: number
  user_name?: string
  entity_type: string
  entity_id: number
  description?: string
  start_time: string
  end_time?: string
  duration_minutes?: number
  cost?: number
  status: string
  is_billable?: number
  hourly_rate?: number
  is_manual?: number
}

export interface TimeReportRow {
  label?: string
  total_minutes?: number
  total_cost?: number
  entry_count?: number
  [key: string]: unknown
}

export function getActiveTimer() {
  return crmGet<{ timer: TimeEntry | null }>("time-tracking/active")
}

export function startTimer(params?: {
  entity_type?: string
  entity_id?: number
  description?: string
  is_billable?: boolean
  hourly_rate?: number
}) {
  return crmPost<{ timer_id: number }>("time-tracking/start", {
    ...params,
    is_billable: params?.is_billable ? 1 : 0,
  })
}

export function stopTimer(id: number) {
  return crmPost(`time-tracking/${id}/stop`)
}

export function pauseTimer(id: number) {
  return crmPost(`time-tracking/${id}/pause`)
}

export function resumeTimer(id: number) {
  return crmPost(`time-tracking/${id}/resume`)
}

export function getTimeEntries(params?: {
  user_id?: number
  entity_type?: string
  entity_id?: number
  date_from?: string
  date_to?: string
  limit?: number
}) {
  return crmGet<{ entries: TimeEntry[] }>("time-tracking/entries", params)
}

export function addManualTimeEntry(params: {
  entity_type?: string
  entity_id?: number
  description?: string
  date?: string
  duration_minutes: number
  is_billable?: boolean
  hourly_rate?: number
}) {
  return crmPost<{ entry_id: number }>("time-tracking/entries", {
    ...params,
    is_billable: params.is_billable ? 1 : 0,
  })
}

export function deleteTimeEntry(id: number) {
  return crmDelete(`time-tracking/entries/${id}`)
}

export function getTimeReport(params?: {
  user_id?: number
  entity_type?: string
  entity_id?: number
  date_from?: string
  date_to?: string
  group_by?: string
}) {
  return crmGet<{ report: TimeReportRow[] | Record<string, unknown> }>("time-tracking/report", params)
}
