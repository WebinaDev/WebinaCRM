import { crmDelete, crmGet, crmPost, type AjaxResponse } from "./client"
import { apiFetch } from "@/lib/api"

// —— Staff (wraps customers list for staff role) ——

export interface StaffUser {
  id: number
  display_name: string
  first_name: string
  last_name: string
  email: string
  phone: string
  role_slug: string
  role_name: string
  department_name?: string
  job_title_name?: string
  department_id?: number
  job_title_id?: number
  registered: string
  registered_jalali: string
  avatar_url: string
  delete_nonce: string
  can_delete: boolean
}

export interface GetStaffResponse {
  users: StaffUser[]
  stats: { total: number; customers: number; staff: number }
  roles: { slug: string; name: string }[]
  departments?: { id: number; name: string }[]
  job_titles?: { id: number; name: string; parent: number }[]
  total?: number
  total_pages?: number
  current_page?: number
  per_page?: number
}

export interface ProfileFieldDef {
  value: string
  label: string
  type: string
  options?: Record<string, string>
}

export interface ProfileSection {
  title: string
  fields: Record<string, ProfileFieldDef>
}

export interface StaffProfile {
  user_id: number
  first_name: string
  last_name: string
  email: string
  role_slug: string
  department_id: number
  job_title_id: number
  department_manager_dept_ids: number[]
  avatar_url: string
  sections: Record<string, ProfileSection>
}

export interface OrgPositionsResponse {
  departments: { id: number; name: string }[]
  job_titles: { id: number; name: string; parent: number }[]
}

export function getStaff(params?: {
  search?: string
  paged?: number
  per_page?: number
}) {
  return crmGet<GetStaffResponse>("hrm/staff", {
    role: "staff",
    search: params?.search,
    paged: params?.paged,
    per_page: params?.per_page,
  })
}

export function saveStaff(data: {
  user_id?: number
  first_name: string
  last_name: string
  email: string
  webino_mobile_phone?: string
  role: string
  password?: string
  department?: number
  job_title?: number
}) {
  const body: Record<string, string | number> = {
    user_id: data.user_id ?? 0,
    first_name: data.first_name,
    last_name: data.last_name,
    email: data.email,
    webino_mobile_phone: data.webino_mobile_phone ?? "",
    role: data.role,
    password: data.password ?? "",
  }
  if (data.department !== undefined) body.department = data.department
  if (data.job_title !== undefined) body.job_title = data.job_title
  return crmPost<{ message?: string; reload?: boolean }>("hrm/staff", body)
}

export function deleteStaff(userId: number, nonce: string) {
  return crmDelete<{ message?: string }>(`hrm/staff/${userId}`, {
    user_id: String(userId),
    nonce,
  })
}

export function getStaffProfile(userId: number) {
  return crmGet<StaffProfile>(`hrm/staff/${userId}/profile`)
}

export async function saveStaffProfile(
  userId: number,
  profile: Record<string, unknown>,
) {
  try {
    const raw = await apiFetch<AjaxResponse<{ message?: string; profile?: StaffProfile }>>(
      `hrm/staff/${userId}/profile`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ profile }),
      },
    )
    if (typeof raw === "object" && raw !== null && "success" in raw) {
      return raw
    }
    return { success: true as const, data: raw as { message?: string; profile?: StaffProfile } }
  } catch (e) {
    const message = e instanceof Error ? e.message : "Request failed"
    return { success: false as const, message }
  }
}

export function getOrgPositions() {
  return crmGet<OrgPositionsResponse>("hrm/org-positions")
}

export function saveOrgPosition(data: {
  term_id?: number
  name: string
  parent_id?: number
}) {
  return crmPost<{ message?: string; term?: { id: number; name: string; parent: number } }>(
    "hrm/org-positions",
    {
      term_id: data.term_id ?? 0,
      name: data.name,
      parent_id: data.parent_id ?? 0,
    },
  )
}

export function deleteOrgPosition(termId: number) {
  return crmDelete<{ message?: string }>(`hrm/org-positions/${termId}`, {
    term_id: termId,
  })
}

// —— Attendance ——

export interface AttendanceRecord {
  id: number
  user_id: number
  user_name: string
  work_date: string
  check_in: string
  check_out: string
  source: string
  status: string
  notes: string
  approved_by: number
}

export interface AttendanceListResponse {
  items: AttendanceRecord[]
  total: number
  total_pages: number
  paged: number
}

export function getAttendance(params?: {
  user_id?: number
  date_from?: string
  date_to?: string
  department?: number
  paged?: number
  per_page?: number
}) {
  return crmGet<AttendanceListResponse>("hrm/attendance", params)
}

export function checkIn() {
  return crmPost<{ message?: string; record?: AttendanceRecord }>("hrm/attendance/check-in")
}

export function checkOut() {
  return crmPost<{ message?: string; record?: AttendanceRecord }>("hrm/attendance/check-out")
}

export function saveAttendance(data: Record<string, string | number>) {
  return crmPost<{ message?: string; record?: AttendanceRecord }>("hrm/attendance", data)
}

// —— Leave ——

export interface LeaveType {
  id: number
  name: string
  code: string
  is_paid: number
  max_days_per_year: number
  is_active: number
  sort_order: number
}

export interface LeaveRequest {
  id: number
  user_id: number
  user_name: string
  leave_type_id: number
  date_from: string
  date_to: string
  days: number
  status: string
  reason: string
  approved_by: number
  approved_at: string
  rejection_reason: string
}

export interface LeaveBalance {
  id: number
  user_id: number
  leave_type_id: number
  year: number
  allocated: number
  used: number
  balance: number
}

export function getLeaveTypes() {
  return crmGet<{ types: LeaveType[] }>("hrm/leave/types")
}

export function saveLeaveType(data: Partial<LeaveType> & { name: string }) {
  return crmPost<{ message?: string; type?: LeaveType }>("hrm/leave/types", data as Record<string, string | number>)
}

export function getLeaveRequests(params?: {
  status?: string
  user_id?: number
  paged?: number
  per_page?: number
}) {
  return crmGet<{
    requests: LeaveRequest[]
    total: number
    total_pages: number
    paged: number
  }>("hrm/leave/requests", params)
}

export function saveLeaveRequest(data: {
  id?: number
  user_id?: number
  leave_type_id: number
  date_from: string
  date_to: string
  reason?: string
}) {
  return crmPost<{ message?: string; request?: LeaveRequest }>("hrm/leave/requests", data)
}

export function approveLeave(id: number) {
  return crmPost<{ message?: string; request?: LeaveRequest }>(`hrm/leave/requests/${id}/approve`)
}

export function rejectLeave(id: number, rejection_reason?: string) {
  return crmPost<{ message?: string; request?: LeaveRequest }>(
    `hrm/leave/requests/${id}/reject`,
    { rejection_reason: rejection_reason ?? "" },
  )
}

export function getLeaveBalances(params?: { user_id?: number; year?: number }) {
  return crmGet<{ balances: LeaveBalance[] }>("hrm/leave/balances", params)
}

// —— Payroll ——

export interface PayrollRun {
  id: number
  title: string
  period_year: number
  period_month: number
  period_start: string
  period_end: string
  status: string
  total_gross: number
  total_deductions: number
  total_net: number
  journal_entry_id: number
  calculated_at: string
  approved_by: number
  approved_at: string
}

export interface Payslip {
  id: number
  run_id: number
  user_id: number
  user_name: string
  gross: number
  deductions: number
  net: number
  components: unknown[]
  status: string
}

export interface EmployeeSalaryRow {
  id: number
  user_id: number
  component_id: number
  component_name: string
  component_code: string
  component_type: string
  amount: number
  effective_from: string
  effective_to: string
}

export function getPayrollSettings() {
  return crmGet<{ settings: Record<string, unknown> }>("hrm/payroll/settings")
}

export function savePayrollSettings(data: Record<string, number>) {
  return crmPost<{ message?: string; settings?: Record<string, unknown> }>(
    "hrm/payroll/settings",
    data,
  )
}

export function getPayrollRuns() {
  return crmGet<{ runs: PayrollRun[] }>("hrm/payroll/runs")
}

export function savePayrollRun(data: {
  id?: number
  title?: string
  period_year?: number
  period_month?: number
  period_start?: string
  period_end?: string
}) {
  return crmPost<{ message?: string; run?: PayrollRun }>("hrm/payroll/runs", data)
}

export function getPayrollRun(id: number) {
  return crmGet<{ run: PayrollRun }>(`hrm/payroll/runs/${id}`)
}

export function calculatePayrollRun(id: number) {
  return crmPost<{ message?: string; run?: PayrollRun }>(`hrm/payroll/runs/${id}/calculate`)
}

export function approvePayrollRun(id: number) {
  return crmPost<{ message?: string; run?: PayrollRun }>(`hrm/payroll/runs/${id}/approve`)
}

export function getPayslips(runId: number, userId?: number) {
  return crmGet<{ payslips: Payslip[]; run_id: number }>(
    `hrm/payroll/runs/${runId}/payslips`,
    userId ? { user_id: userId } : undefined,
  )
}

export function getEmployeeSalary(userId: number) {
  return crmGet<{ user_id: number; salaries: EmployeeSalaryRow[] }>(
    "hrm/payroll/employee-salaries",
    { user_id: userId },
  )
}

export function saveEmployeeSalary(data: {
  id?: number
  user_id: number
  component_id: number
  amount: number
  effective_from?: string
  effective_to?: string
}) {
  return crmPost<{ message?: string; id?: number }>("hrm/payroll/employee-salaries", data)
}

// —— Recruitment ——

export interface Applicant {
  id: number
  job_posting_id: number
  first_name: string
  last_name: string
  email: string
  phone: string
  status: string
  resume_url: string
  cover_letter: string
  source: string
  hired_user_id: number
}

export interface JobPosting {
  id: number
  title: string
  department_id: number
  description: string
  requirements: string
  status: string
  posted_at: string
}

export interface Interview {
  id: number
  applicant_id: number
  scheduled_at: string
  interviewer_id: number
  interviewer_name: string
  location: string
  notes: string
  status: string
}

export function getApplicants(params?: { job_posting_id?: number; status?: string }) {
  return crmGet<{ applicants: Applicant[] }>("hrm/recruitment/applicants", params)
}

export function saveApplicant(data: Partial<Applicant> & {
  job_posting_id: number
  last_name: string
  email: string
}) {
  return crmPost<{ message?: string; applicant?: Applicant }>("hrm/recruitment/applicants", data as Record<string, string | number>)
}

export function deleteApplicant(id: number) {
  return crmDelete<{ message?: string }>(`hrm/recruitment/applicants/${id}`)
}

export function hireApplicant(id: number) {
  return crmPost<{ message?: string; user_id?: number }>(`hrm/recruitment/applicants/${id}/hire`)
}

export function getInterviews(params?: { applicant_id?: number }) {
  return crmGet<{ interviews: Interview[] }>("hrm/recruitment/interviews", params)
}

export function saveInterview(data: Partial<Interview> & { applicant_id: number }) {
  return crmPost<{ message?: string; interview?: Interview }>("hrm/recruitment/interviews", data as Record<string, string | number>)
}

export function getJobPostings() {
  return crmGet<{ postings: JobPosting[] }>("hrm/recruitment/postings")
}

export function saveJobPosting(data: Partial<JobPosting> & { title: string }) {
  return crmPost<{ message?: string; posting?: JobPosting }>("hrm/recruitment/postings", data as Record<string, string | number>)
}

// —— Performance ——

export interface KpiTemplate {
  id: number
  name: string
  description: string
  criteria: { key?: string; label?: string; weight?: number; max_score?: number }[]
  is_active: number
}

export interface ReviewCycle {
  id: number
  name: string
  year: number
  start_date: string
  end_date: string
  status: string
  template_id: number
}

export interface PerformanceReview {
  id: number
  cycle_id: number
  user_id: number
  user_name: string
  reviewer_id: number
  reviewer_name: string
  status: string
  overall_score: number
  notes: string
  submitted_at: string
}

export function getKpiTemplates() {
  return crmGet<{ templates: KpiTemplate[] }>("hrm/performance/kpi-templates")
}

export function saveKpiTemplate(data: {
  id?: number
  name: string
  description?: string
  criteria?: unknown[]
  is_active?: number
}) {
  return crmPost<{ message?: string; id?: number }>("hrm/performance/kpi-templates", {
    id: data.id ?? 0,
    name: data.name,
    description: data.description ?? "",
    criteria: JSON.stringify(data.criteria ?? []),
    is_active: data.is_active ?? 1,
  })
}

export function getReviewCycles() {
  return crmGet<{ cycles: ReviewCycle[] }>("hrm/performance/cycles")
}

export function saveReviewCycle(data: Partial<ReviewCycle> & { name: string; start_date: string; end_date: string }) {
  return crmPost<{ message?: string; cycle?: ReviewCycle }>("hrm/performance/cycles", data as Record<string, string | number>)
}

export function getReviews(params?: { cycle_id?: number; user_id?: number }) {
  return crmGet<{ reviews: PerformanceReview[] }>("hrm/performance/reviews", params)
}

export function saveReview(data: {
  id?: number
  cycle_id: number
  user_id: number
  reviewer_id?: number
  status?: string
  notes?: string
}) {
  return crmPost<{ message?: string }>("hrm/performance/reviews", data)
}

// —— Training ——

export interface TrainingCourse {
  id: number
  title: string
  description: string
  duration_hours: number
  category: string
  status: string
}

export interface TrainingSession {
  id: number
  course_id: number
  session_date: string
  start_time: string
  end_time: string
  location: string
  instructor_id: number
  instructor_name: string
  notes: string
}

export interface Enrollment {
  id: number
  course_id: number
  user_id: number
  user_name?: string
  status: string
  enrolled_at: string
  completed_at: string
}

export function getCourses(params?: { status?: string }) {
  return crmGet<{ courses: TrainingCourse[] }>("hrm/training/courses", params)
}

export function saveCourse(data: Partial<TrainingCourse> & { title: string }) {
  return crmPost<{ message?: string; course?: TrainingCourse }>("hrm/training/courses", data as Record<string, string | number>)
}

export function getSessions(params?: { course_id?: number }) {
  return crmGet<{ sessions: TrainingSession[] }>("hrm/training/sessions", params)
}

export function saveSession(data: Partial<TrainingSession> & { course_id: number; session_date: string }) {
  return crmPost<{ message?: string; session?: TrainingSession }>("hrm/training/sessions", data as Record<string, string | number>)
}

export function getEnrollments(params?: { course_id?: number; user_id?: number }) {
  return crmGet<{ enrollments: Enrollment[] }>("hrm/training/enrollments", params)
}

export function saveEnrollment(data: {
  id?: number
  course_id: number
  user_id: number
  status?: string
}) {
  return crmPost<{ message?: string }>("hrm/training/enrollments", data)
}
