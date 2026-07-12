import { DetailSkeleton } from "@/components/skeletons"
import { useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { getProject, type ProjectDetail } from "@/api/projects"
import { cn } from "@/lib/utils"
import { ArrowRight, Calendar, Pencil, User, Users } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"

type Props = {
  projectId: number
  isRtl: boolean
  onBack: () => void
  onEdit: () => void
}

export function ProjectDetailPanel({ projectId, isRtl, onBack, onEdit }: Props) {
  const { t } = useLocale()
  const [detail, setDetail] = useState<ProjectDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)
    getProject(projectId)
      .then((res) => {
        if (cancelled) return
        if (res.success && res.data?.project) {
          setDetail(res.data.project)
        } else {
          setError(res.message ?? t("pages.projects.پروژه_یافت_نشد"))
        }
      })
      .catch(() => {
        if (!cancelled) setError(t("pages.projects.خطا_در_بارگذاری_پروژه"))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => { cancelled = true }
  }, [projectId, t])

  if (loading) {
    return <DetailSkeleton compact showPageHeader={false} />
  }
  if (error || !detail) {
    return (
      <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
        <Button variant="ghost" onClick={onBack}>{t("pages.projects.بازگشت_به_لیست_پروژهها")}</Button>
        <p className="text-destructive">{error ?? t("pages.projects.پروژه_یافت_نشد")}</p>
      </div>
    )
  }

  const priorityLabel = {
    high: t("pages.projects.اولویت_زیاد"),
    medium: t("pages.projects.اولویت_متوسط"),
    low: t("pages.projects.اولویت_کم"),
  }[detail.priority] ?? detail.priority
  const tasks = detail.tasks ?? []

  return (
    <div className="space-y-6" dir={isRtl ? "rtl" : "ltr"}>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Button variant="ghost" onClick={onBack} className="gap-2">
          <ArrowRight className={cn("h-4 w-4", isRtl && "rotate-180")} />
          {t("pages.projects.بازگشت_به_لیست_پروژهها")}
        </Button>
        <Button variant="outline" size="sm" onClick={onEdit} className="gap-2">
          <Pencil className="h-4 w-4" />
          {t("pages.projects.ویرایش_پروژه")}
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <h1 className="text-2xl font-semibold">{detail.title}</h1>
        <Badge variant="secondary">{detail.status_name ?? ""}</Badge>
        <Badge variant="outline">{priorityLabel}</Badge>
      </div>

      <Card>
        <CardContent className="pt-6">
          <h3 className="text-lg font-medium mb-4">{t("pages.projects.جزئیات_پروژه")}</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            {detail.customer_name && (
              <div className="flex items-center gap-2">
                <User className="h-4 w-4 text-muted-foreground" />
                <span>{t("pages.projects.مشتری")}</span>
                <span className="font-medium">{detail.customer_name}</span>
              </div>
            )}
            {detail.manager_name && (
              <div className="flex items-center gap-2">
                <User className="h-4 w-4 text-muted-foreground" />
                <span>{t("pages.projects.مدیر_پروژه")}</span>
                <span className="font-medium">{detail.manager_name}</span>
              </div>
            )}
            {detail.start_date && (
              <div className="flex items-center gap-2">
                <Calendar className="h-4 w-4 text-muted-foreground" />
                <span>{t("pages.projects.تاریخ_شروع")}</span>
                <span>{detail.start_date}</span>
              </div>
            )}
            {detail.end_date && (
              <div className="flex items-center gap-2">
                <Calendar className="h-4 w-4 text-muted-foreground" />
                <span>{t("pages.projects.تاریخ_پایان")}</span>
                <span>{detail.end_date}</span>
              </div>
            )}
            {detail.assigned_members && detail.assigned_members.length > 0 && (
              <div className="flex items-start gap-2 sm:col-span-2">
                <Users className="h-4 w-4 text-muted-foreground mt-0.5" />
                <div>
                  <span className="block mb-1">{t("pages.projects.اعضای_تیم")}</span>
                  <div className="flex flex-wrap gap-1">
                    {detail.assigned_members.map((m) => (
                      <Badge key={m.id} variant="outline">{m.display_name}</Badge>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>
          {detail.content && (
            <div
              className="mt-4 pt-4 border-t prose prose-sm dark:prose-invert max-w-none text-muted-foreground"
              dangerouslySetInnerHTML={{ __html: detail.content }}
            />
          )}
        </CardContent>
      </Card>

      {(detail.total_tasks !== undefined && detail.total_tasks > 0) && (
        <Card>
          <CardContent className="pt-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-medium">{t("pages.projects.پیشرفت_وظایف")}</h3>
              <span className="text-sm text-muted-foreground">
                {detail.completed_tasks ?? 0} / {detail.total_tasks}
                {detail.completion_percentage != null && ` (${detail.completion_percentage}%)`}
              </span>
            </div>
            {detail.completion_percentage != null && (
              <div className="h-2 w-full rounded-full bg-muted overflow-hidden">
                <div
                  className="h-full bg-primary transition-all"
                  style={{ width: `${Math.min(100, detail.completion_percentage)}%` }}
                />
              </div>
            )}
            {tasks.length > 0 && (
              <ul className="mt-4 space-y-2">
                {tasks.slice(0, 10).map((task) => (
                  <li key={task.id} className="flex items-center justify-between py-2 border-b last:border-0">
                    <span>{task.title}</span>
                    <Badge variant="secondary">{task.status_name}</Badge>
                  </li>
                ))}
                {tasks.length > 10 && (
                  <li className="text-sm text-muted-foreground pt-2">
                    {t("pages.projects.tasks_more", { count: tasks.length - 10 })}
                  </li>
                )}
              </ul>
            )}
            <div className="mt-4">
              <Button variant="outline" size="sm" asChild>
                <Link to={`/tasks?project=${detail.id}`}>{t("pages.projects.لینک_وظایف")}</Link>
              </Button>
            </div>
            {detail.project_tags && detail.project_tags.length > 0 && (
              <div className="mt-4 flex flex-wrap gap-1">
                {detail.project_tags.map((tag) => (
                  <Badge key={tag} variant="outline">{tag}</Badge>
                ))}
              </div>
            )}
            {detail.logo_url && (
              <img src={detail.logo_url} alt="" className="mt-4 h-16 w-16 rounded-lg object-cover" />
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
