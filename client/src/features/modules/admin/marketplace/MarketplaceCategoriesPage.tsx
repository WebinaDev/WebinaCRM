import { CardListSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  deleteMarketplaceCategory,
  getMarketplaceCategories,
  saveMarketplaceCategory,
  type MarketplaceCategory,
} from "@/api/marketplace"
import { marketplaceError } from "./marketplace-messages"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { FolderTree, Pencil, Plus, Trash2, X } from "lucide-react"

const EMPTY_FORM = { slug: "", name: "", sort: "0", status: "active" }

export function MarketplaceCategoriesPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, setError, setSuccess } = useCrmFeedback()
  const [items, setItems] = useState<MarketplaceCategory[]>([])
  const [loading, setLoading] = useState(true)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(EMPTY_FORM)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getMarketplaceCategories()
    if (res.success && res.data) {
      setItems(res.data.categories ?? [])
    } else {
      setError(marketplaceError(res, t("pages.marketplace.api.categoriesLoadError")))
      setItems([])
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const resetForm = () => {
    setEditingId(null)
    setForm(EMPTY_FORM)
  }

  const startEdit = (item: MarketplaceCategory) => {
    setEditingId(item.id)
    setForm({
      slug: item.slug,
      name: item.name,
      sort: String(item.sort ?? 0),
      status: item.status ?? "active",
    })
  }

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setSuccess(null)
    const name = form.name.trim()
    if (!name) {
      setError(t("pages.marketplace.categoryNameRequired"))
      return
    }
    const res = await saveMarketplaceCategory({
      id: editingId ?? undefined,
      slug: form.slug.trim(),
      name,
      sort: Number(form.sort) || 0,
      status: form.status,
    })
    if (res.success) {
      setSuccess(t("pages.marketplace.saved"))
      resetForm()
      void load()
    } else {
      setError(marketplaceError(res, t("pages.marketplace.api.categorySaveFailed")))
    }
  }

  const confirmDelete = async () => {
    if (deleteId == null) return
    setDeleting(true)
    const res = await deleteMarketplaceCategory(deleteId)
    setDeleting(false)
    if (res.success) {
      setDeleteId(null)
      setSuccess(t("pages.marketplace.api.deleted"))
      if (editingId === deleteId) resetForm()
      void load()
    } else {
      setError(marketplaceError(res, t("pages.marketplace.deleteError")))
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.marketplace.categoriesTitle")}
      description={t("pages.marketplace.categoriesDesc")}
      {...layoutProps}
    >
      <Card>
        <CardContent className="pt-6">
          <form onSubmit={handleSave} className="grid gap-4 md:grid-cols-4">
            <div className="space-y-2">
              <Label>{t("pages.marketplace.name")}</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.slugOptional")}</Label>
              <Input
                value={form.slug}
                readOnly={editingId != null}
                placeholder={t("pages.marketplace.slugAutoHint")}
                onChange={(e) => setForm((p) => ({ ...p, slug: e.target.value }))}
              />
              <p className="text-muted-foreground text-xs">{t("pages.marketplace.slugAutoHint")}</p>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.sort")}</Label>
              <Input
                type="number"
                value={form.sort}
                onChange={(e) => setForm((p) => ({ ...p, sort: e.target.value }))}
              />
            </div>
            <div className="flex items-end gap-2">
              <Button type="submit">
                {editingId != null ? (
                  t("common.save")
                ) : (
                  <>
                    <Plus className="me-2 h-4 w-4" />
                    {t("pages.marketplace.addCategory")}
                  </>
                )}
              </Button>
              {editingId != null ? (
                <Button type="button" variant="outline" onClick={resetForm}>
                  <X className="me-2 h-4 w-4" />
                  {t("common.cancel")}
                </Button>
              ) : null}
            </div>
          </form>
        </CardContent>
      </Card>

      {loading ? (
        <CardListSkeleton rows={6} />
      ) : items.length === 0 ? (
        <PmEmptyState icon={FolderTree} message={t("pages.marketplace.categoriesDesc")} />
      ) : (
        <div className="grid gap-3">
          {items.map((item) => (
            <Card key={item.id}>
              <CardContent className="flex items-center justify-between py-4">
                <div>
                  <div className="font-medium">{item.name}</div>
                  <div className="text-muted-foreground text-sm">
                    {item.slug} · {t("pages.marketplace.sort")}: {item.sort}
                  </div>
                </div>
                <div className="flex gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => startEdit(item)}
                  >
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="text-destructive"
                    onClick={() => setDeleteId(item.id)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.marketplace.confirm.deleteCategory")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  )
}
