import { getAjaxMessage } from "./client"
import { modirpayamakProxy } from "./modirpayamak"

export type EdgeMeta = {
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
  status?: boolean
  message?: string
}

export type EdgeRow = Record<string, unknown>

export type EdgeListResult = {
  items: EdgeRow[]
  meta: EdgeMeta
  raw: unknown
}

export type EdgeItemResult = {
  item: EdgeRow | null
  meta: EdgeMeta
  raw: unknown
}

const LIST_KEYS = [
  "entries",
  "items",
  "data",
  "list",
  "patterns",
  "phonebooks",
  "numbers",
  "users",
  "tickets",
  "drafts",
  "messages",
  "records",
  "rows",
] as const

export function edgeField(row: EdgeRow | null | undefined, ...keys: string[]): string {
  if (!row) return "—"
  for (const key of keys) {
    const v = row[key]
    if (v != null && v !== "") return String(v)
  }
  return "—"
}

export function edgeNumber(row: EdgeRow | null | undefined, ...keys: string[]): number {
  const s = edgeField(row, ...keys)
  if (s === "—") return 0
  const n = Number(s)
  return Number.isFinite(n) ? n : 0
}

export function unwrapEdgeList(payload: unknown): EdgeListResult {
  if (payload == null) return { items: [], meta: {}, raw: payload }

  const root = payload as Record<string, unknown>
  const meta = (root.meta && typeof root.meta === "object" ? root.meta : {}) as EdgeMeta
  let data: unknown = root.data ?? root

  if (Array.isArray(data)) {
    return { items: data as EdgeRow[], meta, raw: payload }
  }

  if (data && typeof data === "object") {
    const obj = data as Record<string, unknown>
    for (const key of LIST_KEYS) {
      if (Array.isArray(obj[key])) {
        return {
          items: obj[key] as EdgeRow[],
          meta: (obj.meta && typeof obj.meta === "object" ? obj.meta : meta) as EdgeMeta,
          raw: payload,
        }
      }
    }
    if (Array.isArray(obj.data)) {
      return { items: obj.data as EdgeRow[], meta, raw: payload }
    }
  }

  return { items: [], meta, raw: payload }
}

export function unwrapEdgeItem(payload: unknown): EdgeItemResult {
  if (payload == null) return { item: null, meta: {}, raw: payload }
  const root = payload as Record<string, unknown>
  const meta = (root.meta && typeof root.meta === "object" ? root.meta : {}) as EdgeMeta
  const data = root.data ?? root
  if (data && typeof data === "object" && !Array.isArray(data)) {
    return { item: data as EdgeRow, meta, raw: payload }
  }
  return { item: null, meta, raw: payload }
}

async function edgeRequest(
  method: string,
  path: string,
  body?: Record<string, unknown>,
  query?: Record<string, unknown>,
) {
  const res = await modirpayamakProxy(method, path, body, query)
  if (!res.success) {
    return {
      ok: false as const,
      message: getAjaxMessage(res) ?? "Request failed",
      data: null,
      meta: {} as EdgeMeta,
    }
  }
  const envelope = (res.data ?? {}) as Record<string, unknown>
  const inner = envelope.data ?? envelope
  const meta = (envelope.meta && typeof envelope.meta === "object" ? envelope.meta : {}) as EdgeMeta
  const ok = envelope.ok !== false
  return {
    ok,
    message: typeof envelope.message === "string" ? envelope.message : "",
    data: inner,
    meta,
  }
}

export async function edgeListPatterns(page = 1, perPage = 20) {
  const res = await edgeRequest("GET", "api/patterns", undefined, { page, per_page: perPage })
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  const list = unwrapEdgeList(res.data)
  return { ...list, meta: { ...list.meta, ...res.meta }, error: null }
}

export async function edgeGetPattern(code: string) {
  const res = await edgeRequest("GET", `api/patterns/${encodeURIComponent(code)}`)
  if (!res.ok) return { ...unwrapEdgeItem(null), error: res.message }
  return { ...unwrapEdgeItem(res.data), error: null }
}

export async function edgeSavePattern(code: string | null, body: Record<string, unknown>) {
  if (code) {
    return edgeRequest("PUT", `api/patterns/${encodeURIComponent(code)}`, body)
  }
  return edgeRequest("POST", "api/patterns", body)
}

export async function edgeDeletePattern(code: string) {
  return edgeRequest("DELETE", `api/patterns/${encodeURIComponent(code)}`)
}

export async function edgeListPhonebooks(query?: Record<string, unknown>) {
  const res = await edgeRequest("GET", "api/phonebooks", undefined, query)
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeSavePhonebook(id: number | null, body: Record<string, unknown>) {
  if (id) return edgeRequest("PUT", `api/phonebooks/${id}`, body)
  return edgeRequest("POST", "api/phonebooks", body)
}

export async function edgeDeletePhonebook(id: number) {
  return edgeRequest("DELETE", `api/phonebooks/${id}`)
}

export async function edgeListPhonebookContacts(phonebookId: number, query?: Record<string, unknown>) {
  const res = await edgeRequest("GET", `api/phonebooks/${phonebookId}/numbers`, undefined, query)
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeAddPhonebookContact(phonebookId: number, body: Record<string, unknown>) {
  return edgeRequest("POST", `api/phonebooks/${phonebookId}/numbers`, body)
}

export async function edgeListNumbers(query?: Record<string, unknown>) {
  const res = await edgeRequest("GET", "api/numbers", undefined, query)
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeListUsers(query?: Record<string, unknown>) {
  const res = await edgeRequest("GET", "api/user", undefined, query)
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeGetUser(userId: string) {
  const res = await edgeRequest("GET", `api/user/${encodeURIComponent(userId)}`)
  if (!res.ok) return { ...unwrapEdgeItem(null), error: res.message }
  return { ...unwrapEdgeItem(res.data), error: null }
}

export async function edgeCreateUser(body: Record<string, unknown>) {
  return edgeRequest("POST", "api/user/create", body)
}

export async function edgeUpdateUser(userId: string, body: Record<string, unknown>) {
  return edgeRequest("PUT", `api/user/${encodeURIComponent(userId)}`, body)
}

export async function edgeListTickets(query?: Record<string, unknown>) {
  const res = await edgeRequest("GET", "api/tickets", undefined, query)
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeGetTicket(id: number) {
  const res = await edgeRequest("GET", `api/tickets/${id}`)
  if (!res.ok) return { ...unwrapEdgeItem(null), error: res.message }
  return { ...unwrapEdgeItem(res.data), error: null }
}

export async function edgeCreateTicket(body: Record<string, unknown>) {
  return edgeRequest("POST", "api/tickets", body)
}

export async function edgeReplyTicket(id: number, body: Record<string, unknown>) {
  return edgeRequest("POST", `api/tickets/${id}/reply`, body)
}

export async function edgeListDrafts(query?: Record<string, unknown>) {
  const res = await edgeRequest("GET", "api/drafts", undefined, query)
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeCreateDraft(body: Record<string, unknown>) {
  return edgeRequest("POST", "api/drafts", body)
}

export async function edgeDeleteDraft(id: number) {
  return edgeRequest("DELETE", `api/drafts/${id}`)
}

export async function edgeReportOutbox(page = 1, limit = 20, filters: Record<string, unknown> = {}) {
  const res = await edgeRequest("POST", "api/report/new_list", { page, limit, filters })
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeReportInbox(page = 1, limit = 20, filters: Record<string, unknown> = {}) {
  const res = await edgeRequest("POST", "api/report/inbox", { page, limit, filters })
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message }
  return { ...unwrapEdgeList(res.data), error: null }
}

export async function edgeReportOutboxDetail(id: string) {
  const res = await edgeRequest("GET", `api/report/outbox/${encodeURIComponent(id)}`)
  if (!res.ok) return { ...unwrapEdgeItem(null), error: res.message }
  return { ...unwrapEdgeItem(res.data), error: null }
}

export async function edgeMyCredit() {
  return edgeRequest("GET", "api/payment/credit/mine")
}
