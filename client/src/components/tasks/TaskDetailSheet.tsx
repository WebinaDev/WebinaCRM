import { DetailSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useRef, useState } from "react"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
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
import { Separator } from "@/components/ui/separator"
import { Checkbox } from "@/components/ui/checkbox"
import { Paperclip, Trash2, X } from "lucide-react"
import {
  getTask,
  updateTaskStatus,
  addTaskComment,
  manageTaskChecklist,
  removeTaskLink,
  addTaskLink,
  searchTasksForLinking,
  uploadTaskAttachment,
  deleteTaskAttachment,
  logTaskTime,
  quickEditTask,
  saveTaskAsTemplate,
  type TaskDetail,
} from "@/api/tasks"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"

interface TaskDetailSheetProps {
  taskId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onUpdated?: () => void
  onOpenLinkedTask?: (taskId: number) => void
}

export function TaskDetailSheet({
  taskId,
  open,
  onOpenChange,
  onUpdated,
  onOpenLinkedTask,
}: TaskDetailSheetProps) {
  const { t, isRtl, formatDisplayDate } = useLocale()
  const [task, setTask] = useState<TaskDetail | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [newChecklist, setNewChecklist] = useState("")
  const [newComment, setNewComment] = useState("")
  const [hours, setHours] = useState("")
  const [timeDesc, setTimeDesc] = useState("")
  const [linkSearch, setLinkSearch] = useState("")
  const [linkResults, setLinkResults] = useState<{ id: number; text: string }[]>([])
  const [linkType, setLinkType] = useState("relates_to")
  const [dueDateEdit, setDueDateEdit] = useState("")
  const [templateName, setTemplateName] = useState("")
  const fileRef = useRef<HTMLInputElement>(null)

  const load = useCallback(async () => {
    if (!taskId) return
    setLoading(true)
    setError(null)
    try {
      const res = await getTask(taskId)
      if (res.success && res.data?.task) {
        const loaded = res.data.task
        setTask(loaded)
        setDueDateEdit(loaded.due_date ? loaded.due_date.slice(0, 10) : "")
      } else {
        setError(res.message ?? t("pages.tasks.خطا_در_بارگذاری_وظایف"))
      }
    } catch {
      setError(t("pages.tasks.خطا_در_بارگذاری_وظایف"))
    } finally {
      setLoading(false)
    }
  }, [taskId, t])

  useEffect(() => {
    if (open && taskId) {
      load()
    } else if (!open) {
      setTask(null)
      setError(null)
    }
  }, [open, taskId, load])

  const refresh = useCallback(async () => {
    await load()
    onUpdated?.()
  }, [load, onUpdated])

  const handleDueDateSave = async (value: string) => {
    if (!taskId) return
    setBusy(true)
    const res = await quickEditTask(taskId, { due_date: value })
    setBusy(false)
    if (res.success) await refresh()
    else setError(res.message ?? t("pages.tasks.خطا_در_بهروزرسانی"))
  }

  const handleSaveTemplate = async () => {
    if (!taskId || !templateName.trim()) return
    setBusy(true)
    const res = await saveTaskAsTemplate(taskId, templateName.trim())
    setBusy(false)
    if (res.success) {
      setTemplateName("")
      await refresh()
    } else {
      setError(res.message ?? t("pages.tasks.خطا_در_بهروزرسانی"))
    }
  }

  const handleStatusChange = async (slug: string) => {
    if (!taskId) return
    setBusy(true)
    const res = await updateTaskStatus(taskId, slug)
    setBusy(false)
    if (res.success) await refresh()
    else setError(res.message ?? t("pages.tasks.خطا_در_بهروزرسانی"))
  }

  const handleChecklistToggle = async (itemId: string) => {
    if (!taskId) return
    setBusy(true)
    await manageTaskChecklist(taskId, { sub_action: "toggle", item_id: itemId })
    setBusy(false)
    await refresh()
  }

  const handleChecklistDelete = async (itemId: string) => {
    if (!taskId) return
    setBusy(true)
    await manageTaskChecklist(taskId, { sub_action: "delete", item_id: itemId })
    setBusy(false)
    await refresh()
  }

  const handleChecklistAdd = async () => {
    if (!taskId || !newChecklist.trim()) return
    setBusy(true)
    await manageTaskChecklist(taskId, { sub_action: "add", text: newChecklist.trim() })
    setNewChecklist("")
    setBusy(false)
    await refresh()
  }

  const handleCommentAdd = async () => {
    if (!taskId || !newComment.trim()) return
    setBusy(true)
    await addTaskComment(taskId, newComment.trim())
    setNewComment("")
    setBusy(false)
    await refresh()
  }

  const handleUpload = async (file: File) => {
    if (!taskId) return
    setBusy(true)
    const res = await uploadTaskAttachment(taskId, file)
    setBusy(false)
    if (!res.success) setError(res.message ?? t("common.errors.saveFailed"))
    else await refresh()
  }

  const handleDeleteAttachment = async (attachmentId: number) => {
    if (!taskId) return
    setBusy(true)
    await deleteTaskAttachment(taskId, attachmentId)
    setBusy(false)
    await refresh()
  }

  const handleLogTime = async () => {
    if (!taskId) return
    const h = parseFloat(hours)
    if (!h || h <= 0) return
    setBusy(true)
    await logTaskTime(taskId, h, timeDesc)
    setHours("")
    setTimeDesc("")
    setBusy(false)
    await refresh()
  }

  const handleRemoveLink = async (toId: number) => {
    if (!taskId) return
    setBusy(true)
    await removeTaskLink(taskId, toId)
    setBusy(false)
    await refresh()
  }

  const searchLinks = async (q: string) => {
    setLinkSearch(q)
    if (!taskId || q.length < 2) {
      setLinkResults([])
      return
    }
    const res = await searchTasksForLinking(q, taskId)
    if (res.success && Array.isArray(res.data)) {
      setLinkResults(res.data)
    }
  }

  const handleAddLink = async (toId: number) => {
    if (!taskId) return
    setBusy(true)
    await addTaskLink(taskId, toId, linkType)
    setLinkSearch("")
    setLinkResults([])
    setBusy(false)
    await refresh()
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side={isRtl ? "left" : "right"}
        className="w-full sm:max-w-lg overflow-y-auto"
        dir={isRtl ? "rtl" : "ltr"}
      >
        <SheetHeader>
          <SheetTitle className="text-start pe-8">
            {task?.title ?? (loading ? "…" : `#${taskId}`)}
          </SheetTitle>
        </SheetHeader>

        {task && !loading && (
          <div className="flex flex-wrap gap-2 items-end">
            <Input
              value={templateName}
              onChange={(e) => setTemplateName(e.target.value)}
              placeholder={t("pages.tasks.template_name")}
              className="flex-1 min-w-[140px]"
            />
            <Button type="button" size="sm" variant="outline" onClick={handleSaveTemplate} disabled={busy || !templateName.trim()}>
              {t("pages.tasks.save_as_template")}
            </Button>
          </div>
        )}

        {loading && <DetailSkeleton compact showPageHeader={false} />}

        {error && (
          <p className="text-sm text-destructive px-1">{error}</p>
        )}

        {task && !loading && (
          <div className="space-y-6 pb-8">
            <div className="grid gap-3 sm:grid-cols-2 text-sm">
              <div>
                <Label>{t("common.status")}</Label>
                <Select value={task.status_slug} onValueChange={handleStatusChange} disabled={busy}>
                  <SelectTrigger className="mt-1">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {task.statuses.map((s) => (
                      <SelectItem key={s.slug} value={s.slug}>{s.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>{t("pages.tasks.اولویت")}</Label>
                <p className="mt-1 text-muted-foreground">{task.priority_name || t("common.emptyValue")}</p>
              </div>
              <div>
                <Label>{t("pages.invoices.پروژه")}</Label>
                <p className="mt-1 text-muted-foreground">{task.project_title || t("common.emptyValue")}</p>
              </div>
              <div>
                <Label>{t("pages.tasks.کارمند")}</Label>
                <p className="mt-1 text-muted-foreground">{task.assigned_name || t("common.emptyValue")}</p>
              </div>
              <div>
                <Label>{t("pages.tasks.detail.deadline")}</Label>
                <div className="mt-1 flex flex-wrap gap-2 items-center">
                  <DatePicker
                    value={dueDateEdit}
                    onChange={(v) => {
                      setDueDateEdit(v)
                      void handleDueDateSave(v)
                    }}
                  />
                  {task.due_date && !dueDateEdit && (
                    <span className="text-sm text-muted-foreground">
                      {formatDisplayDate(task.due_date.slice(0, 10))}
                    </span>
                  )}
                </div>
              </div>
            </div>

            {task.content && (
              <div>
                <Label>{t("pages.tasks.detail.description")}</Label>
                <div
                  className="mt-1 rounded-md border bg-muted/30 p-3 text-sm prose prose-sm max-w-none dark:prose-invert"
                  dangerouslySetInnerHTML={{ __html: task.content }}
                />
              </div>
            )}

            <Separator />

            <div>
              <Label className="mb-2 block">{t("pages.tasks.detail.checklist")}</Label>
              <ul className="space-y-2">
                {task.checklist.map((item) => (
                  <li key={item.id} className="flex items-center gap-2">
                    <Checkbox
                      checked={item.checked}
                      onCheckedChange={() => handleChecklistToggle(item.id)}
                      disabled={busy}
                    />
                    <span className={cn("flex-1 text-sm", item.checked && "line-through text-muted-foreground")}>
                      {item.text}
                    </span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => handleChecklistDelete(item.id)}
                      disabled={busy}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
              </ul>
              <div className="flex gap-2 mt-2">
                <Input
                  value={newChecklist}
                  onChange={(e) => setNewChecklist(e.target.value)}
                  placeholder={t("pages.tasks.detail.newChecklistItem")}
                  onKeyDown={(e) => e.key === "Enter" && handleChecklistAdd()}
                />
                <Button type="button" size="sm" onClick={handleChecklistAdd} disabled={busy}>
                  {t("common.add")}
                </Button>
              </div>
            </div>

            <Separator />

            <div>
              <Label className="mb-2 block">{t("pages.tasks.detail.attachments")}</Label>
              <ul className="space-y-2 mb-2">
                {task.attachments.map((att) => (
                  <li key={att.id} className="flex items-center justify-between gap-2 text-sm">
                    <a href={att.url} target="_blank" rel="noopener noreferrer" className="text-primary truncate">
                      {att.name}
                    </a>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="shrink-0"
                      onClick={() => handleDeleteAttachment(att.id)}
                      disabled={busy}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
              </ul>
              <input
                ref={fileRef}
                type="file"
                className="hidden"
                onChange={(e) => {
                  const f = e.target.files?.[0]
                  if (f) handleUpload(f)
                  e.target.value = ""
                }}
              />
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="gap-2"
                onClick={() => fileRef.current?.click()}
                disabled={busy}
              >
                <Paperclip className="h-4 w-4" />
                {t("pages.tasks.detail.uploadFile")}
              </Button>
            </div>

            {task.links.length > 0 && (
              <>
                <Separator />
                <div>
                  <Label className="mb-2 block">{t("pages.tasks.detail.relatedLinks")}</Label>
                  <ul className="space-y-2">
                    {task.links.map((link) => (
                      <li key={`${link.type}-${link.task_id}`} className="flex items-center justify-between text-sm">
                        <button
                          type="button"
                          className="text-primary text-start hover:underline"
                          onClick={() => onOpenLinkedTask?.(link.task_id)}
                        >
                          {link.type_label} — #{link.task_id} {link.title}
                        </button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => handleRemoveLink(link.task_id)}
                          disabled={busy}
                        >
                          {t("common.delete")}
                        </Button>
                      </li>
                    ))}
                  </ul>
                </div>
              </>
            )}

            <div>
              <Label className="mb-2 block">{t("pages.tasks.detail.addLink")}</Label>
              <Select value={linkType} onValueChange={setLinkType}>
                <SelectTrigger className="mb-2">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="relates_to">{t("pages.tasks.detail.linkRelatesTo")}</SelectItem>
                  <SelectItem value="blocks">{t("pages.tasks.detail.linkBlocks")}</SelectItem>
                  <SelectItem value="is_blocked_by">{t("pages.tasks.detail.linkBlockedBy")}</SelectItem>
                </SelectContent>
              </Select>
              <Input
                value={linkSearch}
                onChange={(e) => searchLinks(e.target.value)}
                placeholder={t("pages.tasks.detail.searchTask")}
              />
              {linkResults.length > 0 && (
                <ul className="mt-1 border rounded-md divide-y max-h-32 overflow-auto">
                  {linkResults.map((r) => (
                    <li key={r.id}>
                      <button
                        type="button"
                        className="w-full px-3 py-2 text-sm text-start hover:bg-muted"
                        onClick={() => handleAddLink(r.id)}
                      >
                        {r.text}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <Separator />

            <div>
              <Label className="mb-2 block">{t("pages.tasks.detail.comments")}</Label>
              <ul className="space-y-3 mb-3">
                {task.comments.map((c) => (
                  <li key={c.id} className="flex gap-2 text-sm">
                    {c.avatar_url && (
                      <img src={c.avatar_url} alt="" className="h-8 w-8 rounded-full shrink-0" />
                    )}
                    <div>
                      <p className="font-medium">{c.author}</p>
                      <p className="text-muted-foreground whitespace-pre-wrap">{c.content}</p>
                      <span className="text-xs text-muted-foreground">{c.time_ago}</span>
                    </div>
                  </li>
                ))}
              </ul>
              <div className="flex gap-2">
                <Input
                  value={newComment}
                  onChange={(e) => setNewComment(e.target.value)}
                  placeholder={t("pages.tasks.detail.newComment")}
                  onKeyDown={(e) => e.key === "Enter" && handleCommentAdd()}
                />
                <Button type="button" size="sm" onClick={handleCommentAdd} disabled={busy}>
                  {t("common.save")}
                </Button>
              </div>
            </div>

            <Separator />

            <div>
              <Label className="mb-2 block">
                {t("pages.tasks.detail.logTime", { total: task.total_hours.toFixed(2) })}
              </Label>
              {task.time_logs.length > 0 && (
                <ul className="text-xs text-muted-foreground space-y-1 mb-2">
                  {task.time_logs.slice(0, 5).map((log, i) => (
                    <li key={i}>
                      {log.user_name}: {log.hours}h — {log.description || log.date}
                    </li>
                  ))}
                </ul>
              )}
              <div className="flex flex-wrap gap-2">
                <Input
                  type="number"
                  step="0.25"
                  min="0"
                  className="w-24"
                  value={hours}
                  onChange={(e) => setHours(e.target.value)}
                  placeholder={t("pages.tasks.detail.hoursPlaceholder")}
                />
                <Input
                  className="flex-1 min-w-[120px]"
                  value={timeDesc}
                  onChange={(e) => setTimeDesc(e.target.value)}
                  placeholder={t("pages.tasks.detail.timeDescription")}
                />
                <Button type="button" size="sm" onClick={handleLogTime} disabled={busy}>
                  {t("common.add")}
                </Button>
              </div>
            </div>

            {task.activity.length > 0 && (
              <>
                <Separator />
                <div>
                  <Label className="mb-2 block">{t("pages.tasks.detail.activity")}</Label>
                  <ul className="text-xs text-muted-foreground space-y-2">
                    {task.activity.map((a, i) => (
                      <li key={i}>
                        <strong>{a.user_name}</strong>: {a.text}
                        <span className="block">{a.time}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </>
            )}
          </div>
        )}
      </SheetContent>
    </Sheet>
  )
}
