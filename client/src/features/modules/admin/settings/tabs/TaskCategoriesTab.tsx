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
import {
  manageTaskCategory,
  deleteTaskCategory,
  type TaskCategoryItem,
} from "@/api/settings"
import { Loader2, Plus, Pencil, Trash2 } from "lucide-react"
import { useLocale } from "@/hooks/use-locale"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"

type Props = {
  items: TaskCategoryItem[]
  onError: (s: string | null) => void
  onMessage: (s: string | null) => void
  onReload: () => void
  isRtl: boolean
}

export function TaskCategoriesTab({ items, onError, onMessage, onReload, isRtl }: Props) {
  const { t } = useLocale()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<TaskCategoryItem | null>(null)
  const [name, setName] = useState("")
  const [color, setColor] = useState("#845adf")
  const [submitting, setSubmitting] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const openNew = () => {
    setEditing(null)
    setName("")
    setColor("#845adf")
    setOpen(true)
  }
  const openEdit = (item: TaskCategoryItem) => {
    setEditing(item)
    setName(item.name)
    setColor(item.color || "#845adf")
    setOpen(true)
  }
  const handleSubmit = async () => {
    if (!name.trim()) {
      onError(t("pages.settings.ui.validation_category_name_required"))
      return
    }
    setSubmitting(true)
    onError(null)
    try {
      const res = await manageTaskCategory({
        category_id: editing?.id,
        category_name: name.trim(),
        category_color: color,
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
      const res = await deleteTaskCategory(deleteId)
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
            <CardTitle>{t("pages.settings.دستهبندی_وظایف")}</CardTitle>
            <CardDescription>{t("pages.settings.دستهبندی_و_برچسبهای_وظایف")}</CardDescription>
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
                {t("pages.settings.دستهبندی_تعریف_نشده_است")}
              </li>
            )}
            {items.map((item) => (
              <li
                key={item.id}
                className="flex items-center justify-between rounded-lg border p-3"
              >
                <div className="flex items-center gap-2">
                  <span
                    className="h-4 w-4 rounded-full border"
                    style={{ backgroundColor: item.color || "#845adf" }}
                  />
                  <p className="font-medium">{item.name}</p>
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
              {editing
                ? t("pages.settings.ui.edit_category")
                : t("pages.settings.ui.new_category")}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>{t("common.name")}</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.settings.رنگ")}</Label>
              <div className="flex gap-2">
                <input
                  type="color"
                  value={color}
                  onChange={(e) => setColor(e.target.value)}
                  className="h-10 w-14 cursor-pointer rounded border"
                />
                <Input value={color} onChange={(e) => setColor(e.target.value)} />
              </div>
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
        description={t("pages.settings.confirm.deleteCategory")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={submitting}
        isRtl={isRtl}
      />
    </>
  )
}
