export interface DashboardStats {
  total_projects: number
  new_projects_this_month?: number
  new_projects_growth?: number
  completed_projects?: number
  in_progress_projects: number
  pending_projects?: number
  total_tasks: number
  completed_tasks: number
  overdue_tasks?: number
  customer_count: number
  open_tickets: number
  income_this_month: number
  total_revenue: number
  revenue_growth?: number
  completion_rate?: number
}

export interface DashboardFullData {
  stats: DashboardStats
  team: Array<{
    id: number
    name: string
    email?: string
    avatar: string
    role: string
    total_tasks: number
    completed_tasks: number
    progress: number
    is_online: boolean
  }>
  running_projects: Array<{
    id: number
    title: string
    excerpt: string
    progress: number
    done: number
    total: number
    assigned: number[]
    modified: string
  }>
  daily_tasks: Array<{
    id: number
    title: string
    due_date: string
    due_time: string
    project: string
  }>
  projects_table: Array<{
    id: number
    title: string
    done_tasks: number
    total_tasks: number
    progress: number
    status: string
    status_slug: string
    assigned: number[]
    due_date: string
  }>
  revenue_data: { values: number[]; labels: string[] }
  monthly_goals: { new_projects: number; completed: number; pending: number }
}

export interface TeamMemberStats {
  total_tasks: number
  completed_tasks: number
  in_progress_tasks: number
  overdue_tasks: number
  today_tasks: number
  total_projects: number
  completion_rate: number
  total_tickets: number
  open_tickets: number
}

export interface ClientStats {
  total_projects: number
  active_projects: number
  completed_projects: number
  total_tickets: number
  open_tickets: number
  total_contracts: number
  total_value: number
  paid_amount: number
  pending_amount: number
}
