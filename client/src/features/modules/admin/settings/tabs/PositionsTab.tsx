import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { managePosition, deletePosition, type PositionItem } from "@/api/settings"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"

type Props = {
  items: PositionItem[]
  onError: (s: string | null) => void
  onMessage: (s: string | null) => void
  onReload: () => void
  isRtl: boolean
}

export function PositionsTab({ items, onError, onMessage, onReload, isRtl }: Props) {
  const { t } = useLocale()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<PositionItem | null>(null)
  const [title, setTitle] = useState("")
  const [submitting, setSubmitting] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const openNew = () => {
    setEditing(null)
    setTitle("")
    setOpen(true)
  }
  const openEdit = (item: PositionItem) => {
    setEditing(item)
    setTitle(item.title)
    setOpen(true)
  }
  const handleSubmit = async () => {
    if (!title.trim()) {
      onError(t("pages.settings.ui.validation_title_required"))
      return
    }
    setSubmitting(true)
    onError(null)
    try {
      const res = await managePosition({
        position_id: editing?.id,
        position_title: title.trim(),
        position_permissions: editing?.permissions ?? [],
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
      const res = await deletePosition(deleteId)
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
            <CardTitle>{t("pages.settings.جایگاههای_شغلی")}</CardTitle>
            <CardDescription>{t("pages.settings.سمتها_و_نقشهای_کاربری")}</CardDescription>
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
                {t("pages.settings.جایگاهی_تعریف_نشده_است")}
              </li>
            )}
            {items.map((item) => (
              <li
                key={item.id}
                className="flex items-center justify-between rounded-lg border p-3"
              >
                <p className="font-medium">{item.title}</p>
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
              {editing
                ? t("pages.settings.ui.edit_position")
                : t("pages.settings.ui.new_position")}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>{t("common.title")}</Label>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} />
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
        description={t("pages.settings.confirm.deletePosition")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={submitting}
        isRtl={isRtl}
      />
    </>
  )
}
