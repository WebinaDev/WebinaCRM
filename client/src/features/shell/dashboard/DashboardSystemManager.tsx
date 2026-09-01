import { DashboardSkeleton } from "@/components/skeletons"
import { useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Progress } from "@/components/ui/progress"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { crmGet, getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"
import { SimpleBarChart } from "@/features/shared/charts/SimpleBarChart"
import { DashboardStatCard } from "./components/DashboardStatCard"
import { SimpleDoughnut } from "./components/SimpleDoughnut"
import type { DashboardFullData } from "./types"
import {
  FolderOpen,
  CheckCircle,
  ListTodo,
  ArrowLeft,
  ArrowRight,
  Eye,
  Pencil,
  Trash2,
  UserPlus,
  Mail,
} from "lucide-react"

type Props = {
  onError?: (message: string | null) => void
}

function normalizeRevenueData(raw: unknown): { values: number[]; labels: string[] } {
  if (!raw || typeof raw !== "object") {
    return { values: [], labels: [] }
  }
  const obj = raw as { values?: unknown; labels?: unknown }
  const values = Array.isArray(obj.values) ? obj.values.map((v) => Number(v) || 0) : []
  const labels = Array.isArray(obj.labels) ? obj.labels.map((l) => String(l ?? "")) : []
  return { values, labels }
}

export function DashboardSystemManager({ onError }: Props) {
  const { t, isRtl, formatNumber, formatDate } = useLocale()
  const [data, setData] = useState<DashboardFullData | null>(null)
  const [loading, setLoading] = useState(true)
  const BackIcon = isRtl ? ArrowRight : ArrowLeft

  useEffect(() => {
    let cancelled = false
    onError?.(null)
    crmGet<DashboardFullData>("dashboard/full")
      .then((res) => {
        if (cancelled) return
        if (res.success && res.data) {
          setData(res.data)
        } else {
          setData(null)
          onError?.(getAjaxMessage(res) ?? t("pages.dashboard.load_error"))
        }
      })
      .catch(() => {
        if (!cancelled) onError?.(t("pages.dashboard.load_error"))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [onError])

  const s = data?.stats ?? {
    total_projects: 0,
    new_projects_this_month: 0,
    new_projects_growth: 0,
    completed_projects: 0,
    in_progress_projects: 0,
    pending_projects: 0,
    total_tasks: 0,
    completed_tasks: 0,
    customer_count: 0,
    open_tickets: 0,
    income_this_month: 0,
    total_revenue: 0,
    revenue_growth: 0,
    completion_rate: 0,
  }
  const team = data?.team ?? []
  const running = data?.running_projects ?? []
  const daily = data?.daily_tasks ?? []
  const projectsTable = data?.projects_table ?? []
  const revenue = normalizeRevenueData(data?.revenue_data)
  const goals = data?.monthly_goals ?? { new_projects: 0, completed: 0, pending: 0 }

  if (loading) {
    return <DashboardSkeleton compact showPageHeader={false} />
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-4">
          <Card className="overflow-hidden bg-gradient-to-br from-primary to-primary/80 text-primary-foreground">
            <CardContent className="flex flex-row items-center justify-between p-4">
              <div>
                <h4 className="font-medium">{t("pages.dashboard.مدیریت_پروژه")}</h4>
                <p className="text-sm opacity-90">
                  {t("pages.dashboard.پروژهها_را_با_یک_کلیک_مدیریت_کنید")}
                </p>
                <Button asChild size="sm" className="mt-2" variant="secondary">
                  <Link to="/pm/projects">
                    {t("pages.dashboard.همین_الان_مدیریت")}
                    <BackIcon className={cn("h-4 w-4", isRtl ? "me-2" : "ms-2")} />
                  </Link>
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>{t("pages.dashboard.تیم")}</CardTitle>
              <Button asChild variant="outline" size="sm">
                <Link to="/staff">{t("pages.dashboard.مشاهده_همه")}</Link>
              </Button>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("common.name")}</TableHead>
                    <TableHead>{t("pages.dashboard.وظایف")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead>{t("pages.dashboard.پیشرفت")}</TableHead>
                    <TableHead>{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {team.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-muted-foreground">
                        {t("pages.dashboard.موردی_یافت_نشد")}
                      </TableCell>
                    </TableRow>
                  ) : (
                    team.map((m) => (
                      <TableRow key={m.id}>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <Avatar className="h-8 w-8">
                              <AvatarImage src={m.avatar} />
                              <AvatarFallback>{m.name.slice(0, 2)}</AvatarFallback>
                            </Avatar>
                            <div>
                              <div className="font-medium">{m.name}</div>
                              <div className="text-xs text-muted-foreground">{m.role}</div>
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>{formatNumber(m.total_tasks)}</TableCell>
                        <TableCell>
                          <Badge variant={m.is_online ? "default" : "secondary"}>
                            {m.is_online ? t("common.online") : t("common.offline")}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          {formatNumber(m.completed_tasks)} / {formatNumber(m.total_tasks)}
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-1">
                            <Button variant="ghost" size="icon">
                              <UserPlus className="h-4 w-4" />
                            </Button>
                            {m.email ? (
                              <Button variant="ghost" size="icon" asChild>
                                <a href={`mailto:${m.email}`}>
                                  <Mail className="h-4 w-4" />
                                </a>
                              </Button>
                            ) : (
                              <Button variant="ghost" size="icon" disabled>
                                <Mail className="h-4 w-4" />
                              </Button>
                            )}
                            <Button variant="ghost" size="icon" asChild>
                              <Link to={`/staff?user=${m.id}`}>
                                <Eye className="h-4 w-4" />
                              </Link>
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            <DashboardStatCard
              title={t("pages.dashboard.پروژههای_جدید")}
              value={formatNumber(s.new_projects_this_month ?? 0)}
              description={`${(s.new_projects_growth ?? 0) >= 0 ? "+" : ""}${formatNumber(Math.round(s.new_projects_growth ?? 0))}٪`}
              icon={FolderOpen}
            />
            <DashboardStatCard
              title={t("pages.dashboard.تکمیلشده")}
              value={formatNumber(s.completed_projects ?? 0)}
              icon={CheckCircle}
            />
            <DashboardStatCard
              title={t("pages.dashboard.در_حال_انجام")}
              value={formatNumber(s.in_progress_projects ?? 0)}
              icon={FolderOpen}
            />
            <DashboardStatCard
              title={t("pages.contracts.در_انتظار")}
              value={formatNumber(s.pending_projects ?? 0)}
              icon={ListTodo}
            />
          </div>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>{t("pages.dashboard.آمار_پروژه")}</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mb-4 flex items-center justify-between">
              <div>
                <p className="text-sm text-muted-foreground">{t("pages.dashboard.کل_درآمد")}</p>
                <p className="text-xl font-bold">{formatNumber(s.total_revenue ?? 0)}</p>
              </div>
            </div>
            <SimpleBarChart data={revenue.values} labels={revenue.labels} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>{t("pages.dashboard.پروژههای_در_حال_اجرا")}</CardTitle>
            <Button asChild variant="outline" size="sm">
              <Link to="/pm/projects">{t("pages.dashboard.مشاهده_همه")}</Link>
            </Button>
          </CardHeader>
          <CardContent>
            {running.length === 0 ? (
              <p className="py-4 text-center text-sm text-muted-foreground">
                {t("pages.dashboard.پروژه_فعالی_وجود_ندارد")}
              </p>
            ) : (
              <div className="space-y-4">
                {running.map((p) => (
                  <div key={p.id} className="space-y-2">
                    <div className="flex justify-between text-sm">
                      <Link to={`/pm/projects/${p.id}`} className="font-medium hover:underline">
                        {p.title}
                      </Link>
                      <span className="text-muted-foreground">{p.progress}٪</span>
                    </div>
                    <Progress value={p.progress} />
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>{t("pages.dashboard.اهداف_ماهانه")}</CardTitle>
            <Button asChild variant="outline" size="sm">
              <Link to="/reports">{t("pages.dashboard.مشاهده_همه")}</Link>
            </Button>
          </CardHeader>
          <CardContent>
            <SimpleDoughnut
              values={[goals.new_projects, goals.completed, goals.pending]}
              labels={[
                t("pages.dashboard.chart_new"),
                t("pages.dashboard.chart_completed"),
                t("pages.dashboard.chart_pending"),
              ]}
            />
          </CardContent>
        </Card>
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>{t("pages.dashboard.وظایف_روزانه")}</CardTitle>
          </CardHeader>
          <CardContent>
            {daily.length === 0 ? (
              <p className="py-4 text-center text-sm text-muted-foreground">
                {t("pages.dashboard.وظیفهای_برای_امروز_تعیین_نشده")}
              </p>
            ) : (
              <div className="space-y-2">
                {daily.map((task) => (
                  <div
                    key={task.id}
                    className="flex items-center justify-between rounded-lg border p-3"
                  >
                    <div>
                      <p className="font-medium">{task.title}</p>
                      {task.project ? (
                        <Badge variant="outline" className="mt-1">
                          {task.project}
                        </Badge>
                      ) : null}
                    </div>
                    <span className="text-sm text-muted-foreground">
                      {task.due_time || (task.due_date ? formatDate(task.due_date) : "")}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>{t("pages.dashboard.خلاصه_پروژهها")}</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>{t("pages.accounting.accountingReports.عنوان")}</TableHead>
                  <TableHead>{t("pages.dashboard.وظایف")}</TableHead>
                  <TableHead>{t("pages.dashboard.پیشرفت")}</TableHead>
                  <TableHead>{t("common.status")}</TableHead>
                  <TableHead>{t("common.date")}</TableHead>
                  <TableHead>{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {projectsTable.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center text-muted-foreground">
                      {t("pages.dashboard.موردی_یافت_نشد")}
                    </TableCell>
                  </TableRow>
                ) : (
                  projectsTable.map((p, i) => (
                    <TableRow key={p.id}>
                      <TableCell>{formatNumber(i + 1)}</TableCell>
                      <TableCell className="font-medium">{p.title}</TableCell>
                      <TableCell>
                        {formatNumber(p.done_tasks)} / {formatNumber(p.total_tasks)}
                      </TableCell>
                      <TableCell>
                        <Progress value={p.progress} className="w-20" />
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{p.status}</Badge>
                      </TableCell>
                      <TableCell>{p.due_date ? formatDate(p.due_date) : "-"}</TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon" asChild>
                            <Link to={`/projects/${p.id}`}>
                              <Eye className="h-4 w-4" />
                            </Link>
                          </Button>
                          <Button variant="ghost" size="icon">
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="icon">
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>{t("pages.dashboard.خلاصه_وظایف")}</CardTitle>
            <Button asChild variant="outline" size="sm">
              <Link to="/tasks">{t("pages.dashboard.مشاهده_همه")}</Link>
            </Button>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div>
                <p className="text-sm text-muted-foreground">{t("pages.dashboard.نرخ_تکمیل")}</p>
                <p className="text-2xl font-bold">
                  {formatNumber(Math.round(s.completion_rate ?? 0))}٪
                </p>
              </div>
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>{t("pages.dashboard.انجام_شده")}</span>
                  <span>{formatNumber(s.completed_tasks ?? 0)}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span>{t("pages.dashboard.در_حال_انجام")}</span>
                  <span>
                    {formatNumber((s.total_tasks ?? 0) - (s.completed_tasks ?? 0))}
                  </span>
                </div>
                <div className="flex justify-between text-sm">
                  <span>{t("pages.dashboard.معوق")}</span>
                  <span>{formatNumber(s.overdue_tasks ?? 0)}</span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
