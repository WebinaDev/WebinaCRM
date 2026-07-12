import { crmGet } from "./client"

export interface LogEntry {
  id: number
  user_id: number
  user_name: string
  action: string
  object_type: string
  object_id: number
  details: string
  ip_address: string
  created_at: string
  title?: string
  content?: string
  author?: string
  date?: string
}

export interface GetLogsResponse {
  logs: LogEntry[]
  total: number
  total_pages: number
}

export interface SystemLogEntry {
  id: number
  level: string
  severity?: string
  message: string
  context: string
  log_type?: string
  created_at: string
}

export interface GetSystemLogsResponse {
  logs: SystemLogEntry[]
  total: number
  total_pages: number
}

export async function getLogs(params?: {
  log_tab?: string
  s?: string
  user_filter?: number
  action_filter?: string
  paged?: number
}) {
  return crmGet<GetLogsResponse>("logs", {
    log_tab: params?.log_tab,
    s: params?.s,
    user_filter: params?.user_filter,
    action_filter: params?.action_filter,
    paged: params?.paged ?? 1,
  })
}

export async function getSystemLogs(params?: { s?: string; level?: string; paged?: number }) {
  return crmGet<GetSystemLogsResponse>("logs/system", {
    s: params?.s,
    level: params?.level,
    paged: params?.paged ?? 1,
  })
}
