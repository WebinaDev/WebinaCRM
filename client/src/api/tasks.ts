import { crmDelete, crmGet, crmPatch, crmPost, crmPostForm } from "./client"

export interface TaskItem {
  id: number
  title: string
  content: string
  status_slug: string
  status_name: string
  priority_slug: string
  priority_name: string
  labels: { slug: string; name: string }[]
  due_date: string
  project_id: number
  project_title: string
  assigned_to: number
  assigned_name: string
  post_date: string
}

export interface TaskStatus {
  id: number
  slug: string
  name: string
  count?: number
}

export interface GetTasksResponse {
  tasks: TaskItem[]
  statuses: TaskStatus[]
  priorities: { id: number; slug: string; name: string }[]
  labels: { id: number; slug: string; name: string }[]
  projects: { id: number; title: string }[]
  staff: { id: number; display_name: string }[]
  can_delete_tasks?: boolean
  total_pages: number
  total: number
  stats: {
    total_tasks: number
    active_tasks: number
    completed_tasks: number
    total_projects: number
  }
}

export interface TaskChecklistItem {
  id: string
  text: string
  checked: boolean
}

export interface TaskCommentItem {
  id: number
  author: string
  content: string
  date: string
  avatar_url: string
  time_ago: string
}

export interface TaskLinkItem {
  type: string
  type_label: string
  task_id: number
  title: string
}

export interface TaskAttachmentItem {
  id: number
  url: string
  name: string
  type: string
  size: string
}

export interface TaskTimeLogItem {
  user_name: string
  hours: number
  description: string
  date: string
}

export interface TaskDetail {
  id: number
  title: string
  content: string
  status_slug: string
  status_name: string
  priority_slug: string
  priority_name: string
  project_id: number
  project_title: string
  assigned_to: number
  assigned_name: string
  due_date: string
  statuses: TaskStatus[]
  checklist: TaskChecklistItem[]
  comments: TaskCommentItem[]
  links: TaskLinkItem[]
  attachments: TaskAttachmentItem[]
  activity: { user_name: string; text: string; time: string }[]
  time_logs: TaskTimeLogItem[]
  total_hours: number
}

export async function getTask(taskId: number) {
  return crmGet<{ task: TaskDetail }>(`tasks/${taskId}`)
}

export async function uploadTaskAttachment(taskId: number, file: File) {
  const form = new FormData()
  form.append("file", file)
  form.append("task_id", String(taskId))
  return crmPostForm<{ message?: string; attachment?: TaskAttachmentItem }>(
    `tasks/${taskId}/attachments`,
    form,
    { task_id: taskId },
  )
}

export async function deleteTaskAttachment(taskId: number, attachmentId: number) {
  return crmDelete<{ message?: string }>(
    `tasks/${taskId}/attachments/${attachmentId}`,
    { task_id: taskId, attachment_id: attachmentId },
  )
}

export async function getTasks(params?: {
  s?: string
  project_filter?: number
  staff_filter?: number
  priority_filter?: string
  label_filter?: string
  status?: string
  paged?: number
  per_page?: number
}) {
  return crmGet<GetTasksResponse>("tasks", {
    s: params?.s,
    project_filter: params?.project_filter,
    staff_filter: params?.staff_filter,
    priority_filter: params?.priority_filter,
    label_filter: params?.label_filter,
    status: params?.status,
    paged: params?.paged ?? 1,
    per_page: params?.per_page ?? 50,
  })
}

export async function updateTaskStatus(taskId: number, statusSlug: string) {
  return crmPatch<{ message?: string }>(`tasks/${taskId}/status`, {
    task_id: String(taskId),
    status: statusSlug,
  })
}

export async function deleteTask(taskId: number) {
  return crmDelete<{ message?: string }>(`tasks/${taskId}`, {
    task_id: String(taskId),
  })
}

export async function addTask(data: {
  title: string
  content?: string
  project_id: number
  assigned_to: number
  due_date?: string
  status_slug?: string
}) {
  const body: Record<string, string> = {
    title: data.title,
    content: data.content ?? "",
    project_id: String(data.project_id),
    assigned_to: String(data.assigned_to),
  }
  if (data.due_date) body.due_date = data.due_date
  if (data.status_slug) body.status_slug = data.status_slug
  return crmPost<{ message?: string; reload?: boolean }>("tasks", body)
}

export async function quickAddTask(data: {
  title: string
  project_id: number
  assigned_to: number
  status_slug: string
}) {
  return crmPost<{ message?: string; task_html?: string }>("tasks", {
    title: data.title,
    project_id: String(data.project_id),
    assigned_to: String(data.assigned_to),
    status_slug: data.status_slug,
  })
}

export interface TaskCalendarEvent {
  id: number
  title: string
  start: string
  color: string
  allDay: boolean
}

export interface TaskGanttItem {
  id: number
  text: string
  start_date: string
  duration: number
  progress: number
  open: boolean
}

export async function getTasksCalendar(params?: { start?: string; end?: string }) {
  return crmGet<{ events: TaskCalendarEvent[] }>("tasks/calendar", {
    start: params?.start ?? "",
    end: params?.end ?? "",
  })
}

export async function getTasksGantt() {
  return crmGet<{ tasks: TaskGanttItem[] }>("tasks/gantt")
}

export async function getTasksViews() {
  return crmGet<{
    calendar_events: TaskCalendarEvent[]
    gantt_tasks: { data: TaskGanttItem[]; links: unknown[] }
  }>("tasks/views")
}

export async function createTaskExtended(body: Record<string, string>) {
  return crmPost<{ message?: string; reload?: boolean }>("tasks/extended", body)
}

export async function saveTaskContent(taskId: number, content: string) {
  return crmPatch<{ message?: string }>(`tasks/${taskId}/content`, {
    task_id: String(taskId),
    content,
  })
}

export async function addTaskComment(taskId: number, comment: string) {
  return crmPost<{ message?: string }>(`tasks/${taskId}/comments`, {
    task_id: String(taskId),
    comment,
  })
}

export async function manageTaskChecklist(
  taskId: number,
  data: { sub_action: string; item_id?: string; text?: string }
) {
  return crmPost<{ checklist?: unknown[]; message?: string }>(
    `tasks/${taskId}/checklist`,
    {
      task_id: String(taskId),
      ...data,
    }
  )
}

export async function logTaskTime(
  taskId: number,
  hours: number,
  description?: string
) {
  return crmPost<{ message?: string; new_log?: unknown; total_hours?: number }>(
    `tasks/${taskId}/time-log`,
    {
      task_id: String(taskId),
      hours: String(hours),
      description: description ?? "",
    }
  )
}

export async function quickEditTask(
  taskId: number,
  data: Record<string, string>
) {
  return crmPatch<{ message?: string }>(`tasks/${taskId}/quick-edit`, {
    task_id: String(taskId),
    ...data,
  })
}

export async function updateTaskAssignee(taskId: number, assigneeId: number) {
  return crmPatch<{ message?: string }>(`tasks/${taskId}/assignee`, {
    task_id: String(taskId),
    assignee_id: String(assigneeId),
  })
}

export async function searchTasksForLinking(search: string, currentTaskId: number) {
  return crmGet<{ id: number; text: string }[]>("tasks/search", {
    search,
    current_task_id: String(currentTaskId),
  })
}

export async function addTaskLink(
  fromTaskId: number,
  toTaskId: number,
  linkType: string
) {
  return crmPost<{ message?: string }>("tasks/links", {
    from_task_id: String(fromTaskId),
    to_task_id: String(toTaskId),
    link_type: linkType,
  })
}

export async function removeTaskLink(fromTaskId: number, toTaskId: number) {
  return crmDelete<{ message?: string }>("tasks/links", {
    from_task_id: String(fromTaskId),
    to_task_id: String(toTaskId),
  })
}

export async function bulkEditTasks(data: Record<string, string>) {
  return crmPost<{ message?: string }>("tasks/bulk", data)
}

export async function saveTaskAsTemplate(taskId: number, templateName: string) {
  return crmPost<{ message?: string }>("tasks/templates", {
    task_id: String(taskId),
    template_name: templateName,
  })
}
