import { crmDelete, crmGet, crmPostForm } from "./client"

export interface Project {
  id: number
  title: string
  contract_id: number
  customer_name: string
  status_name: string
  status_id: number
  logo_url?: string
}

export interface ProjectTaskItem {
  id: number
  title: string
  status_slug: string
  status_name: string
}

export interface ProjectDetail {
  id: number
  title: string
  content: string
  contract_id: number
  project_manager: number
  project_status: number
  status_name?: string
  start_date: string
  end_date: string
  priority: string
  delete_nonce?: string
  customer_id?: number
  customer_name?: string
  manager_name?: string
  assigned_members?: { id: number; display_name: string }[]
  project_tags?: string[]
  logo_url?: string
  tasks?: ProjectTaskItem[]
  total_tasks?: number
  completed_tasks?: number
  completion_percentage?: number
}

export interface GetProjectsResponse {
  projects: Project[]
  contracts: { id: number; title: string; customer_name: string }[]
  statuses: { id: number; name: string }[]
  managers: { id: number; display_name: string }[]
  total_pages: number
  current_page: number
}

export interface GetProjectResponse {
  project: ProjectDetail
  managers: { id: number; display_name: string }[]
  contracts: { id: number; title: string; customer_name: string }[]
  statuses: { id: number; name: string }[]
}

export async function getProjects(params?: {
  s?: string
  contract_id?: number
  status_filter?: number
  paged?: number
}) {
  return crmGet<GetProjectsResponse>("projects", {
    s: params?.s,
    contract_id: params?.contract_id,
    status_filter: params?.status_filter,
    paged: params?.paged ?? 1,
  })
}

export async function getProject(projectId: number) {
  return crmGet<GetProjectResponse>(`projects/${projectId}`)
}

export async function manageProject(data: {
  project_id?: number
  project_title: string
  contract_id: number
  project_content?: string
  project_status: number
  project_manager?: number
  project_start_date?: string
  project_end_date?: string
  project_priority?: string
  assigned_team_members?: number[]
  project_tags?: string
  project_logo?: File | null
}) {
  const form = new FormData()
  form.set("project_id", String(data.project_id ?? 0))
  form.set("project_title", data.project_title)
  form.set("contract_id", String(data.contract_id))
  form.set("project_content", data.project_content ?? "")
  form.set("project_status", String(data.project_status))
  form.set("project_manager", String(data.project_manager ?? 0))
  form.set("project_start_date", data.project_start_date ?? "")
  form.set("project_end_date", data.project_end_date ?? "")
  form.set("project_priority", data.project_priority ?? "medium")
  if (data.project_tags !== undefined) {
    form.set("project_tags", data.project_tags)
  }
  if (data.assigned_team_members) {
    data.assigned_team_members.forEach((id) => {
      form.append("assigned_team_members[]", String(id))
    })
  }
  if (data.project_logo) {
    form.append("project_logo", data.project_logo)
  }
  const path = data.project_id ? `projects/${data.project_id}` : "projects"
  return crmPostForm<{ message?: string; reload?: boolean }>(
    path,
    form,
    undefined,
    data.project_id ? "PATCH" : "POST",
  )
}

export async function deleteProject(projectId: number, nonce: string) {
  return crmDelete<{ message?: string }>(`projects/${projectId}`, {
    project_id: String(projectId),
    nonce,
  })
}
