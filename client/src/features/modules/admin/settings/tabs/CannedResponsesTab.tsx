import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  manageCannedResponse,
  deleteCannedResponse,
  type CannedResponseItem,
} from "@/api/settings"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"

type Props = {
  items: CannedResponseItem[]
  onError: (s: string | null) => void
  onMessage: (s: string | null) => void
  onReload: () => void
  isRtl: boolean
}

export function CannedResponsesTab({ items, onError, onMessage, onReload, isRtl }: Props) {
  const { t } = useLocale()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<CannedResponseItem | null>(null)
  const [title, setTitle] = useState("")
  const [content, setContent] = useState("")
  const [submitting, setSubmitting] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const openNew = () => {
    setEditing(null)
    setTitle("")
    setContent("")
    setOpen(true)
  }
  const openEdit = (item: CannedResponseItem) => {
    setEditing(item)
    setTitle(item.title)
    setContent(item.content)
    setOpen(true)
  }
  const handleSubmit = async () => {
    if (!title.trim() || !content.trim()) {
      onError(t("pages.settings.ui.validation_title_content_required"))
      return
    }
    setSubmitting(true)
    onError(null)
    try {
      const res = await manageCannedResponse({
        response_id: editing?.id,
        response_title: title.trim(),
        response_content: content.trim(),
      })
      if (res.success) {
        onMessage(res.message ?? t("pages.settings.ذخیره_شد"))
        setOpen(false)
        onReload()
      } else {
        onError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      onError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }
  const confirmDelete = async () => {
    if (deleteId == null) return
    setSubmitting(true)
    onError(null)
    try {
      const res = await deleteCannedResponse(deleteId)
      if (res.success) {
        onMessage(t("pages.settings.ui.deleted"))
        onReload()
        setDeleteId(null)
      } else {
        onError(res.message ?? t("common.errors.saveFailed"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>{t("pages.settings.پاسخهای_آماده")}</CardTitle>
            <CardDescription>
              {t("pages.settings.مدیریت_پاسخهای_از_پیش_تعریفشده_برای_تیکت")}
            </CardDescription>
          </div>
          <Button type="button" onClick={openNew} className="gap-2">
            <Plus className="h-4 w-4" />
            {t("common.add")}
          </Button>
        </CardHeader>
        <CardContent>
          <ul className="space-y-2">
            {items.length === 0 && (
              <li className="text-muted-foreground text-sm py-4">
                {t("pages.settings.پاسخی_تعریف_نشده_است")}
              </li>
            )}
            {items.map((item) => (
              <li
                key={item.id}
                className="flex items-center justify-between rounded-lg border p-3"
              >
                <div>
                  <p className="font-medium">{item.title}</p>
                  <p className="text-muted-foreground text-sm line-clamp-1">{item.content}</p>
                </div>
                <div className="flex gap-2">
                  <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(item)}>
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setDeleteId(item.id)}
                  >
                    <Trash2 className="h-4 w-4 text-destructive" />
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editing ? t("pages.settings.ui.edit_canned") : t("pages.settings.ui.new_canned")}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>{t("common.title")}</Label>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.settings.محتوا")}</Label>
              <Textarea
                className="min-h-[100px]"
                value={content}
                onChange={(e) => setContent(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button type="button" onClick={() => void handleSubmit()} disabled={submitting}>
              {submitting ? <Loader2 className="h-4 w-4 animate-spin me-2" /> : null}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(o) => !o && setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.settings.confirm.deleteCanned")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={submitting}
        isRtl={isRtl}
      />
    </>
  )
}
