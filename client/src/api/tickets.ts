import { crmGet, crmPost } from "./client"

export interface Ticket {
  id: number
  title: string
  author_name: string
  modified: string
  status_slug: string
  status_name: string
  department_name: string
  priority_slug: string
  priority_name: string
}

export interface TicketReply {
  id: number
  author: string
  content: string
  date: string
}

export interface TicketDetail {
  id: number
  title: string
  content: string
  author_id: number
  author_name: string
  date: string
  status_slug: string
  status_name: string
  department_name: string
  department_id: number
  priority_name: string
  priority_id: number
  assigned_to_id: number
  assigned_to_name: string
  is_closed: boolean
  replies: TicketReply[]
}

export interface GetTicketsResponse {
  tickets: Ticket[]
  statuses: { slug: string; name: string }[]
  priorities: { slug: string; name: string; term_id: number }[]
  departments: { id: number; name: string }[]
  total_pages: number
  current_page: number
  is_manager: boolean
  is_team_member: boolean
}

export interface GetTicketResponse {
  ticket: TicketDetail
  departments: { id: number; name: string }[]
  statuses: { slug: string; name: string }[]
  priorities: { term_id: number; slug: string; name: string }[]
  can_manage: boolean
}

export async function getTickets(params?: {
  s?: string
  status_filter?: string
  priority_filter?: string
  department_filter?: number
  paged?: number
}) {
  return crmGet<GetTicketsResponse>("tickets", {
    s: params?.s,
    status_filter: params?.status_filter,
    priority_filter: params?.priority_filter,
    department_filter: params?.department_filter,
    paged: params?.paged ?? 1,
  })
}

export async function getTicket(ticketId: number) {
  return crmGet<GetTicketResponse>(`tickets/${ticketId}`)
}

export async function newTicket(data: {
  ticket_title: string
  ticket_content: string
  department: number
  ticket_priority: number
}) {
  return crmPost<{ message?: string; reload?: boolean }>("tickets", {
    ticket_title: data.ticket_title,
    ticket_content: data.ticket_content,
    department: String(data.department),
    ticket_priority: String(data.ticket_priority),
  })
}

export async function ticketReply(
  ticketId: number,
  comment: string,
  staffFields?: { ticket_status?: string; department?: number; assigned_to?: number; ticket_priority?: number }
) {
  const params: Record<string, string> = {
    ticket_id: String(ticketId),
    comment,
  }
  if (staffFields) {
    if (staffFields.ticket_status) params.ticket_status = staffFields.ticket_status
    if (staffFields.department) params.department = String(staffFields.department)
    if (staffFields.assigned_to) params.assigned_to = String(staffFields.assigned_to)
    if (staffFields.ticket_priority) params.ticket_priority = String(staffFields.ticket_priority)
  }
  return crmPost<{ message?: string }>(`tickets/${ticketId}/replies`, params)
}

export async function convertTicketToTask(ticketId: number) {
  return crmPost<{ message?: string }>(`tickets/${ticketId}/convert-task`, {
    ticket_id: String(ticketId),
  })
}

export async function getCannedResponses() {
  return crmGet<{ responses: { id: number; title: string; content: string }[] }>(
    "tickets/meta/canned-responses",
  )
}
