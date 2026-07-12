import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { useLocale } from "@/hooks/use-locale"
import { Loader2, Plus, Trash2 } from "lucide-react"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"

type Category = { id: number; name: string; parent_id: number; sort_order: number }

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  loadCategories: () => Promise<{ success: boolean; data?: { items: Category[] } }>
  saveCategory: (p: { id?: number; name: string; parent_id?: number }) => Promise<{ success: boolean }>
  deleteCategory: (id: number) => Promise<{ success: boolean }>
}

export function CategoryManagerSheet({
  open,
  onOpenChange,
  title,
  loadCategories,
  saveCategory,
  deleteCategory,
}: Props) {
  const { t, isRtl } = useLocale()
  const [items, setItems] = useState<Category[]>([])
  const [loading, setLoading] = useState(false)
  const [name, setName] = useState("")
  const [saving, setSaving] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    const res = await loadCategories()
    if (res.success && res.data?.items) setItems(res.data.items)
    setLoading(false)
  }, [loadCategories])

  useEffect(() => {
    if (open) void load()
  }, [open, load])

  const handleAdd = async () => {
    if (!name.trim()) return
    setSaving(true)
    const res = await saveCategory({ name: name.trim() })
    setSaving(false)
    if (res.success) {
      setName("")
      void load()
    }
  }

  return (
    <>
      <Sheet open={open} onOpenChange={onOpenChange}>
        <SheetContent side={isRtl ? "left" : "right"} className="w-full sm:max-w-md">
          <SheetHeader>
            <SheetTitle>{title}</SheetTitle>
          </SheetHeader>
          <div className="mt-6 space-y-4">
            <div className="flex gap-2">
              <div className="flex-1 space-y-1">
                <Label>{t("common.name")}</Label>
                <Input value={name} onChange={(e) => setName(e.target.value)} />
              </div>
              <Button className="mt-6" onClick={() => void handleAdd()} disabled={saving || !name.trim()}>
                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
              </Button>
            </div>
            {loading ? (
              <p className="text-sm text-muted-foreground">{t("common.loading")}</p>
            ) : (
              <ul className="space-y-2">
                {items.map((c) => (
                  <li key={c.id} className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                    <span>{c.name}</span>
                    <Button variant="ghost" size="icon" onClick={() => setDeleteId(c.id)}>
                      <Trash2 className="h-4 w-4 text-destructive" />
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </SheetContent>
      </Sheet>
      <PmConfirmDialog
        open={deleteId !== null}
        onOpenChange={() => setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.accounting.persons.آیا_از_حذف_این_شخص_اطمینان_دارید؟")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await deleteCategory(deleteId)
          if (res.success) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </>
  )
}
