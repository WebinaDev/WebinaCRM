import { useState } from "react"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { createTaskExtended, quickAddTask } from "@/api/tasks"
import { useLocale } from "@/hooks/use-locale"
import { Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  projects: { id: number; title: string }[]
  staff: { id: number; display_name: string }[]
  statuses: { slug: string; name: string }[]
  priorities: { slug: string; name: string }[]
  onCreated: () => void
}

export function TaskCreateDialog({
  open,
  onOpenChange,
  projects,
  staff,
  statuses,
  priorities,
  onCreated,
}: Props) {
  const { t, isRtl } = useLocale()
  const [mode, setMode] = useState<"quick" | "extended">("quick")
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [quick, setQuick] = useState({
    title: "",
    project_id: 0,
    assigned_to: 0,
    status_slug: "",
  })
  const [extended, setExtended] = useState({
    title: "",
    content: "",
    project_id: 0,
    assigned_to: 0,
    due_date: "",
    priority: "",
    task_labels: "",
  })

  const reset = () => {
    setQuick({ title: "", project_id: 0, assigned_to: 0, status_slug: "" })
    setExtended({
      title: "",
      content: "",
      project_id: 0,
      assigned_to: 0,
      due_date: "",
      priority: "",
      task_labels: "",
    })
    setError(null)
    setMode("quick")
  }

  const handleSubmit = async () => {
    setSubmitting(true)
    setError(null)
    try {
      if (mode === "quick") {
        if (!quick.title.trim() || !quick.project_id || !quick.assigned_to || !quick.status_slug) {
          setError(t("pages.tasks.عنوان،_پروژه،_مسئول_و_وضعیت_الزامی_هستند"))
          return
        }
        const res = await quickAddTask({
          title: quick.title.trim(),
          project_id: quick.project_id,
          assigned_to: quick.assigned_to,
          status_slug: quick.status_slug,
        })
        if (!res.success) {
          setError(res.message ?? t("pages.tasks.خطا_در_ایجاد_وظیفه"))
          return
        }
      } else {
        if (!extended.title.trim() || !extended.project_id || !extended.assigned_to) {
          setError(t("pages.tasks.عنوان،_پروژه،_مسئول_و_وضعیت_الزامی_هستند"))
          return
        }
        const res = await createTaskExtended({
          title: extended.title.trim(),
          content: extended.content,
          project_id: String(extended.project_id),
          assigned_to: String(extended.assigned_to),
          due_date: extended.due_date,
          task_labels: extended.task_labels,
          story_points: extended.priority,
        })
        if (!res.success) {
          setError(res.message ?? t("pages.tasks.خطا_در_ایجاد_وظیفه"))
          return
        }
      }
      onCreated()
      onOpenChange(false)
      reset()
    } catch {
      setError(t("pages.tasks.خطا_در_ایجاد_وظیفه"))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(o) => {
        if (!o) reset()
        onOpenChange(o)
      }}
    >
      <DialogContent className="sm:max-w-lg" dir={isRtl ? "rtl" : "ltr"}>
        <DialogHeader>
          <DialogTitle>{t("pages.tasks.وظیفه_جدید")}</DialogTitle>
        </DialogHeader>
        <Tabs value={mode} onValueChange={(v) => setMode(v as "quick" | "extended")}>
          <TabsList className="w-full">
            <TabsTrigger value="quick" className="flex-1">{t("pages.tasks.create_quick")}</TabsTrigger>
            <TabsTrigger value="extended" className="flex-1">{t("pages.tasks.create_extended")}</TabsTrigger>
          </TabsList>
          <TabsContent value="quick" className="space-y-4 pt-4">
            <div className="space-y-2">
              <Label>{t("pages.tasks.عنوان")}</Label>
              <Input value={quick.title} onChange={(e) => setQuick((f) => ({ ...f, title: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.invoices.پروژه")}</Label>
              <Select value={quick.project_id ? String(quick.project_id) : ""} onValueChange={(v) => setQuick((f) => ({ ...f, project_id: parseInt(v, 10) || 0 }))}>
                <SelectTrigger><SelectValue placeholder={t("pages.tasks.انتخاب_پروژه")} /></SelectTrigger>
                <SelectContent>
                  {projects.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.title}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.tasks.مسئول_19")}</Label>
              <Select value={quick.assigned_to ? String(quick.assigned_to) : ""} onValueChange={(v) => setQuick((f) => ({ ...f, assigned_to: parseInt(v, 10) || 0 }))}>
                <SelectTrigger><SelectValue placeholder={t("pages.tasks.انتخاب_مسئول")} /></SelectTrigger>
                <SelectContent>
                  {staff.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.display_name}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.projects.وضعیت")}</Label>
              <Select value={quick.status_slug} onValueChange={(v) => setQuick((f) => ({ ...f, status_slug: v }))}>
                <SelectTrigger><SelectValue placeholder={t("pages.projects.انتخاب_وضعیت")} /></SelectTrigger>
                <SelectContent>
                  {statuses.map((s) => <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
          </TabsContent>
          <TabsContent value="extended" className="space-y-4 pt-4">
            <div className="space-y-2">
              <Label>{t("pages.tasks.عنوان")}</Label>
              <Input value={extended.title} onChange={(e) => setExtended((f) => ({ ...f, title: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("common.description")}</Label>
              <textarea className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" value={extended.content} onChange={(e) => setExtended((f) => ({ ...f, content: e.target.value }))} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{t("pages.invoices.پروژه")}</Label>
                <Select value={extended.project_id ? String(extended.project_id) : ""} onValueChange={(v) => setExtended((f) => ({ ...f, project_id: parseInt(v, 10) || 0 }))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {projects.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.title}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.tasks.مسئول_19")}</Label>
                <Select value={extended.assigned_to ? String(extended.assigned_to) : ""} onValueChange={(v) => setExtended((f) => ({ ...f, assigned_to: parseInt(v, 10) || 0 }))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {staff.map((s) => <SelectItem key={s.id} value={String(s.id)}>{s.display_name}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.tasks.detail.deadline")}</Label>
                <DatePicker value={extended.due_date} onChange={(v) => setExtended((f) => ({ ...f, due_date: v }))} />
              </div>
              {priorities.length > 0 && (
                <div className="space-y-2">
                  <Label>{t("pages.tasks.اولویت")}</Label>
                  <Select value={extended.priority} onValueChange={(v) => setExtended((f) => ({ ...f, priority: v }))}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {priorities.map((p) => <SelectItem key={p.slug} value={p.slug}>{p.name}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
              )}
            </div>
            <div className="space-y-2">
              <Label>{t("pages.tasks.برچسب")}</Label>
              <Input value={extended.task_labels} onChange={(e) => setExtended((f) => ({ ...f, task_labels: e.target.value }))} dir="ltr" placeholder={t("pages.projects.برچسبها")} />
            </div>
          </TabsContent>
        </Tabs>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>{t("common.cancel")}</Button>
          <Button onClick={handleSubmit} disabled={submitting}>
            {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
            {t("common.create")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
