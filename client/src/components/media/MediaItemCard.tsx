import { useMutation } from '@tanstack/react-query'
import { FolderOpen, Pencil, Tag, Tags, Trash2 } from 'lucide-react'
import { useMemo, useState, type KeyboardEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { apiFetch } from '@/lib/api'
import { buildCategoryTree, type Category } from '@/lib/categoryTree'
import type { MediaTerm } from '@/components/media/MediaTermManager'

export type MediaItem = {
  id: number
  title: string
  slug: string
  url: string | null
  mime: string
  caption: string
  description: string
  alt_text: string
  categories: MediaTerm[]
  folders: MediaTerm[]
  tags: MediaTerm[]
}

type MediaItemCardProps = {
  item: MediaItem
  folders: MediaTerm[]
  categories: MediaTerm[]
  tags: MediaTerm[]
  onUpdated: () => void
  onDeleted: () => void
}

function toCategory(term: MediaTerm): Category {
  return { ...term, url: '' }
}

export function MediaItemCard({
  item,
  folders,
  categories,
  tags: allTags,
  onUpdated,
  onDeleted,
}: MediaItemCardProps) {
  const { t } = useTranslation()
  const [folderOpen, setFolderOpen] = useState(false)
  const [categoryOpen, setCategoryOpen] = useState(false)
  const [tagOpen, setTagOpen] = useState(false)
  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const [folderId, setFolderId] = useState<number | null>(item.folders[0]?.id ?? null)
  const [categoryIds, setCategoryIds] = useState<number[]>(item.categories.map((c) => c.id))
  const [tagIds, setTagIds] = useState<number[]>(item.tags.map((tg) => tg.id))
  const [tagInput, setTagInput] = useState('')

  const [editForm, setEditForm] = useState({
    title: item.title,
    slug: item.slug,
    caption: item.caption,
    description: item.description,
    alt_text: item.alt_text,
  })

  const folderTree = useMemo(() => buildCategoryTree(folders.map(toCategory)), [folders])
  const categoryTree = useMemo(() => buildCategoryTree(categories.map(toCategory)), [categories])

  const patch = useMutation({
    mutationFn: (body: Record<string, unknown>) =>
      apiFetch<MediaItem>(`content/media/${item.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      }),
    onSuccess: () => {
      onUpdated()
      toast.success(t('common.saved'))
      setFolderOpen(false)
      setCategoryOpen(false)
      setTagOpen(false)
      setEditOpen(false)
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const remove = useMutation({
    mutationFn: () => apiFetch(`content/media/${item.id}`, { method: 'DELETE' }),
    onSuccess: () => {
      onDeleted()
      toast.success(t('common.deleted'))
      setDeleteOpen(false)
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const createTag = useMutation({
    mutationFn: (name: string) =>
      apiFetch<MediaTerm>('content/media/terms', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ kind: 'tag', name }),
      }),
    onError: (e: Error) => toastApiError(t, e),
  })

  function toggleCategory(id: number) {
    setCategoryIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
  }

  function removeTagId(id: number) {
    setTagIds((prev) => prev.filter((x) => x !== id))
  }

  const tagSuggestions = useMemo(() => {
    const q = tagInput.trim().toLowerCase()
    if (!q) return []
    return allTags
      .filter((tg) => tg.name.toLowerCase().includes(q) && !tagIds.includes(tg.id))
      .slice(0, 6)
  }, [allTags, tagIds, tagInput])

  async function addTagByName(raw: string) {
    const name = raw.trim().replace(/^,+|,+$/g, '')
    if (!name) return
    const existing = allTags.find((tg) => tg.name.toLowerCase() === name.toLowerCase())
    if (existing) {
      if (!tagIds.includes(existing.id)) setTagIds((prev) => [...prev, existing.id])
      setTagInput('')
      return
    }
    const created = await createTag.mutateAsync(name)
    setTagIds((prev) => [...prev, created.id])
    setTagInput('')
    onUpdated()
  }

  function onTagKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault()
      void addTagByName(tagInput)
    }
    if (e.key === 'Backspace' && !tagInput && tagIds.length > 0) {
      setTagIds((prev) => prev.slice(0, -1))
    }
  }

  const selectedTags = tagIds
    .map((id) => allTags.find((tg) => tg.id === id) ?? item.tags.find((tg) => tg.id === id))
    .filter(Boolean) as MediaTerm[]

  return (
    <>
      <Card className="overflow-hidden p-0 shadow-sm">
        {item.url && item.mime.startsWith('image/') ? (
          <img src={item.url} alt={item.alt_text || item.title} className="aspect-video w-full object-cover" />
        ) : (
          <div className="flex aspect-video items-center justify-center text-xs text-muted-foreground">{item.mime}</div>
        )}
        <div className="space-y-2 p-3">
          <p className="truncate text-sm font-medium">{item.title || `#${item.id}`}</p>
          <div className="flex flex-wrap gap-1">
            <Button type="button" size="icon" variant="outline" className="size-8" onClick={() => setFolderOpen(true)}>
              <FolderOpen className="size-4" />
              <span className="sr-only">{t('media.actionFolder')}</span>
            </Button>
            <Button type="button" size="icon" variant="outline" className="size-8" onClick={() => setCategoryOpen(true)}>
              <Tags className="size-4" />
              <span className="sr-only">{t('media.actionCategory')}</span>
            </Button>
            <Button type="button" size="icon" variant="outline" className="size-8" onClick={() => setTagOpen(true)}>
              <Tag className="size-4" />
              <span className="sr-only">{t('media.actionTag')}</span>
            </Button>
            <Button
              type="button"
              size="icon"
              variant="outline"
              className="size-8"
              onClick={() => {
                setEditForm({
                  title: item.title,
                  slug: item.slug,
                  caption: item.caption,
                  description: item.description,
                  alt_text: item.alt_text,
                })
                setEditOpen(true)
              }}
            >
              <Pencil className="size-4" />
              <span className="sr-only">{t('media.actionEdit')}</span>
            </Button>
            <Button type="button" size="icon" variant="outline" className="size-8 text-destructive" onClick={() => setDeleteOpen(true)}>
              <Trash2 className="size-4" />
              <span className="sr-only">{t('common.delete')}</span>
            </Button>
          </div>
        </div>
      </Card>

      <Dialog open={folderOpen} onOpenChange={setFolderOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('media.assignFolder')}</DialogTitle>
          </DialogHeader>
          <Select
            value={folderId == null ? '_none' : String(folderId)}
            onValueChange={(v) => setFolderId(v === '_none' ? null : parseInt(v, 10))}
          >
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="_none">{t('media.noFolder')}</SelectItem>
              {folderTree.map(({ node, depth }) => (
                <SelectItem key={node.id} value={String(node.id)}>
                  {`${'— '.repeat(depth)}${node.name}`}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setFolderOpen(false)}>
              {t('media.quickEditCancel')}
            </Button>
            <Button
              type="button"
              disabled={patch.isPending}
              onClick={() => void patch.mutateAsync({ folder_id: folderId })}
            >
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={categoryOpen} onOpenChange={setCategoryOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('media.assignCategory')}</DialogTitle>
          </DialogHeader>
          <div className="max-h-64 space-y-2 overflow-y-auto">
            {categoryTree.map(({ node, depth }) => (
              <Label key={node.id} className="flex cursor-pointer items-center gap-2 text-sm font-normal">
                <Checkbox checked={categoryIds.includes(node.id)} onCheckedChange={() => toggleCategory(node.id)} />
                <span style={{ paddingInlineStart: depth * 12 }}>{node.name}</span>
              </Label>
            ))}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCategoryOpen(false)}>
              {t('media.quickEditCancel')}
            </Button>
            <Button
              type="button"
              disabled={patch.isPending}
              onClick={() => void patch.mutateAsync({ category_ids: categoryIds })}
            >
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={tagOpen} onOpenChange={setTagOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('media.assignTags')}</DialogTitle>
          </DialogHeader>
          <div className="rounded-md border border-border p-2">
            <div className="flex flex-wrap gap-1.5">
              {selectedTags.map((tg) => (
                <span key={tg.id} className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                  {tg.name}
                  <button type="button" className="rounded-sm opacity-70 hover:opacity-100" onClick={() => removeTagId(tg.id)}>
                    ×
                  </button>
                </span>
              ))}
              <Input
                value={tagInput}
                onChange={(e) => setTagInput(e.target.value)}
                onKeyDown={onTagKeyDown}
                placeholder={t('media.tagsPlaceholder')}
                className="h-7 min-w-[8rem] flex-1 border-0 bg-transparent px-1 shadow-none focus-visible:ring-0"
              />
            </div>
            {tagSuggestions.length > 0 ? (
              <div className="mt-2 flex flex-wrap gap-1">
                {tagSuggestions.map((tg) => (
                  <Button key={tg.id} type="button" size="sm" variant="ghost" className="h-7 px-2 text-xs" onClick={() => void addTagByName(tg.name)}>
                    {tg.name}
                  </Button>
                ))}
              </div>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setTagOpen(false)}>
              {t('media.quickEditCancel')}
            </Button>
            <Button type="button" disabled={patch.isPending} onClick={() => void patch.mutateAsync({ tag_ids: tagIds })}>
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('media.editTitle')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor={`edit-title-${item.id}`}>{t('media.fieldName')}</Label>
              <Input
                id={`edit-title-${item.id}`}
                value={editForm.title}
                onChange={(e) => setEditForm((f) => ({ ...f, title: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor={`edit-slug-${item.id}`}>{t('media.fieldSlug')}</Label>
              <Input
                id={`edit-slug-${item.id}`}
                value={editForm.slug}
                onChange={(e) => setEditForm((f) => ({ ...f, slug: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor={`edit-caption-${item.id}`}>{t('media.fieldCaption')}</Label>
              <Input
                id={`edit-caption-${item.id}`}
                value={editForm.caption}
                onChange={(e) => setEditForm((f) => ({ ...f, caption: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor={`edit-desc-${item.id}`}>{t('media.fieldDescription')}</Label>
              <Textarea
                id={`edit-desc-${item.id}`}
                value={editForm.description}
                onChange={(e) => setEditForm((f) => ({ ...f, description: e.target.value }))}
                rows={3}
              />
            </div>
            {item.mime.startsWith('image/') ? (
              <div className="space-y-2">
                <Label htmlFor={`edit-alt-${item.id}`}>{t('media.fieldAlt')}</Label>
                <Input
                  id={`edit-alt-${item.id}`}
                  value={editForm.alt_text}
                  onChange={(e) => setEditForm((f) => ({ ...f, alt_text: e.target.value }))}
                />
              </div>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
              {t('media.quickEditCancel')}
            </Button>
            <Button type="button" disabled={patch.isPending} onClick={() => void patch.mutateAsync(editForm)}>
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('media.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('media.deleteConfirmBody', { name: item.title || `#${item.id}` })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('media.quickEditCancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={() => void remove.mutateAsync()}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
