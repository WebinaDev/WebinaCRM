import { crmDelete, crmGet, crmPatch, crmPost } from "./client"

export interface AppointmentItem {
  id: number
  title: string
  notes: string
  customer_id: number
  customer_name: string
  datetime: string
  status_slug: string
  status_name: string
}

export interface CalendarEvent {
  id: number
  title: string
  start: string
  backgroundColor?: string
  borderColor?: string
  extendedProps?: { customer?: string; status?: string }
}

export interface GetAppointmentsResponse {
  appointments: AppointmentItem[]
  statuses: { slug: string; name: string }[]
  total: number
  total_pages: number
}

export interface GetAppointmentResponse {
  appointment: {
    id: number
    title: string
    notes: string
    customer_id: number
    date: string
    time: string
    status_slug: string
  }
}

export async function getAppointments(params?: {
  s?: string
  date_from?: string
  date_to?: string
  paged?: number
  per_page?: number
}) {
  return crmGet<GetAppointmentsResponse>("appointments", {
    s: params?.s,
    date_from: params?.date_from,
    date_to: params?.date_to,
    paged: params?.paged ?? 1,
    per_page: params?.per_page ?? 50,
  })
}

export async function getAppointment(appointmentId: number) {
  return crmGet<GetAppointmentResponse>(`appointments/${appointmentId}`, {
    appointment_id: String(appointmentId),
  })
}

export async function getAppointmentsCalendar(start?: string, end?: string) {
  return crmGet<{ events: CalendarEvent[] }>("appointments/calendar", {
    start: start ?? "",
    end: end ?? "",
  })
}

export async function manageAppointment(data: {
  appointment_id?: number
  customer_id: number
  title: string
  date: string
  time: string
  status: string
  notes?: string
}) {
  return crmPost<{ message?: string; reload?: boolean }>("appointments", {
    appointment_id: String(data.appointment_id ?? 0),
    customer_id: String(data.customer_id),
    title: data.title,
    date: data.date,
    time: data.time ?? "10:00",
    status: data.status,
    notes: data.notes ?? "",
  })
}

export async function rescheduleAppointment(appointmentId: number, newDatetime: string) {
  return crmPatch<{ message?: string }>(`appointments/${appointmentId}/datetime`, {
    appointment_id: String(appointmentId),
    new_date: newDatetime,
  })
}

export async function deleteAppointment(appointmentId: number) {
  return crmDelete<{ message?: string }>(`appointments/${appointmentId}`, {
    appointment_id: String(appointmentId),
  })
}

export async function getAppointmentCustomers() {
  return crmGet<{ customers: { id: number; name: string }[] }>("appointments/meta/customers")
}
