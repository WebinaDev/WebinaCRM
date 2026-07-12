import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil, Trash2, Zap } from 'lucide-react'
import { useMemo, useState } from 'react'
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { apiFetch } from '@/lib/api'
import {
  buildCategoryTree,
  parentSelectOptions,
  slugifyFromName,
  type Category,
} from '@/lib/categoryTree'
import { formatNumber } from '@/lib/formatNumber'
import { cn } from '@/lib/utils'

export type MediaTermKind = 'folder' | 'category'

export type MediaTerm = {
  id: number
  name: string
  slug: string
  parent: number
  description: string
  count: number
}

type TermsPayload = {
  folders: MediaTerm[]
  categories: MediaTerm[]
  tags: MediaTerm[]
}

type TermFormState = {
  name: string
  slug: string
  parent: number
  description: string
}

type MediaTermManagerProps = {
  kind: MediaTermKind
}

function emptyForm(): TermFormState {
  return { name: '', slug: '', parent: 0, description: '' }
}

function formFromTerm(term: MediaTerm): TermFormState {
  return {
    name: term.name,
    slug: term.slug,
    parent: term.parent || 0,
    description: term.description ?? '',
  }
}

function toCategory(term: MediaTerm): Category {
  return { ...term, url: '' }
}

function TermFormFields({
  form,
  onChange,
  items,
  excludeId,
  slugAuto,
  onSlugAutoChange,
  idPrefix,
  hierarchical,
}: {
  form: TermFormState
  onChange: (next: TermFormState) => void
  items: MediaTerm[]
  excludeId?: number
  slugAuto: boolean
  onSlugAutoChange: (auto: boolean) => void
  idPrefix: string
  hierarchical: boolean
}) {
  const { t } = useTranslation()
  const categories = useMemo(() => items.map(toCategory), [items])
  const parentOptions = useMemo(
    () => parentSelectOptions(categories, excludeId),
    [categories, excludeId],
  )

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <Label htmlFor={`${idPrefix}-name`}>{t('media.fieldName')}</Label>
        <Input
          id={`${idPrefix}-name`}
          value={form.name}
          onChange={(e) => onChange({ ...form, name: e.target.value })}
          onBlur={() => {
            if (slugAuto && form.name.trim()) {
              onChange({ ...form, slug: slugifyFromName(form.name) })
            }
          }}
        />
        <p className="text-xs text-muted-foreground">{t('media.fieldNameHint')}</p>
      </div>
      <div className="space-y-2">
        <Label htmlFor={`${idPrefix}-slug`}>{t('media.fieldSlug')}</Label>
        <Input
          id={`${idPrefix}-slug`}
          value={form.slug}
          onChange={(e) => {
            onSlugAutoChange(false)
            onChange({ ...form, slug: e.target.value })
          }}
        />
        <p className="text-xs text-muted-foreground">{t('media.fieldSlugHint')}</p>
      </div>
      {hierarchical ? (
        <div className="space-y-2">
          <Label htmlFor={`${idPrefix}-parent`}>{t('media.fieldParent')}</Label>
          <Select
            value={String(form.parent)}
            onValueChange={(v) => onChange({ ...form, parent: parseInt(v, 10) || 0 })}
          >
            <SelectTrigger id={`${idPrefix}-parent`} className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="0">{t('media.fieldParentNone')}</SelectItem>
              {parentOptions.map(({ node, depth }) => (
                <SelectItem key={node.id} value={String(node.id)}>
                  {`${'— '.repeat(depth)}${node.name}`}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">{t('media.fieldParentHint')}</p>
        </div>
      ) : null}
      <div className="space-y-2">
        <Label htmlFor={`${idPrefix}-description`}>{t('media.fieldDescription')}</Label>
        <Textarea
          id={`${idPrefix}-description`}
          value={form.description}
          onChange={(e) => onChange({ ...form, description: e.target.value })}
          rows={3}
        />
        <p className="text-xs text-muted-foreground">{t('media.fieldDescriptionHint')}</p>
      </div>
    </div>
  )
}

function TermList({
  items,
  tree,
  onQuickEdit,
  onEdit,
  onDelete,
  quickEditId,
  quickForm,
  onQuickFormChange,
  onQuickSave,
  onQuickCancel,
  isLoading,
  isQuickSaving,
}: {
  items: MediaTerm[]
  tree: ReturnType<typeof buildCategoryTree>
  onQuickEdit: (term: MediaTerm) => void
  onEdit: (term: MediaTerm) => void
  onDelete: (term: MediaTerm) => void
  quickEditId: number | null
  quickForm: TermFormState
  onQuickFormChange: (next: TermFormState) => void
  onQuickSave: () => void
  onQuickCancel: () => void
  isLoading: boolean
  isQuickSaving: boolean
}) {
  const { t, i18n } = useTranslation()

  if (isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </div>
    )
  }

  if (tree.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('media.empty')}</p>
  }

  return (
    <ul className="divide-y divide-border rounded-md border border-border">
      {tree.map(({ node, depth }) => {
        const term = items.find((x) => x.id === node.id)!
        const isQuick = quickEditId === term.id
        return (
          <li key={term.id} className="px-3 py-2">
            {isQuick ? (
              <div className="space-y-2 py-1">
                <Input
                  value={quickForm.name}
                  onChange={(e) => onQuickFormChange({ ...quickForm, name: e.target.value })}
                />
                <Input
                  value={quickForm.slug}
                  onChange={(e) => onQuickFormChange({ ...quickForm, slug: e.target.value })}
                />
                <div className="flex gap-2">
                  <Button type="button" size="sm" disabled={isQuickSaving} onClick={onQuickSave}>
                    {t('media.quickEditSave')}
                  </Button>
                  <Button type="button" size="sm" variant="outline" onClick={onQuickCancel}>
                    {t('media.quickEditCancel')}
                  </Button>
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-2">
                <span className="min-w-0 flex-1 truncate text-sm" style={{ paddingInlineStart: depth * 12 }}>
                  {term.name}
                </span>
                <span className="text-xs text-muted-foreground">
                  {formatNumber(term.count, i18n.language)}
                </span>
                <Button type="button" size="icon" variant="ghost" className="size-8" onClick={() => onQuickEdit(term)}>
                  <Zap className="size-4" />
                  <span className="sr-only">{t('media.actionQuickEdit')}</span>
                </Button>
                <Button type="button" size="icon" variant="ghost" className="size-8" onClick={() => onEdit(term)}>
                  <Pencil className="size-4" />
                  <span className="sr-only">{t('media.actionEdit')}</span>
                </Button>
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className={cn('size-8 text-destructive hover:text-destructive')}
                  onClick={() => onDelete(term)}
                >
                  <Trash2 className="size-4" />
                  <span className="sr-only">{t('media.actionDelete')}</span>
                </Button>
              </div>
            )}
          </li>
        )
      })}
    </ul>
  )
}

export function MediaTermManager({ kind }: MediaTermManagerProps) {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const hierarchical = true

  const [addForm, setAddForm] = useState(emptyForm)
  const [addSlugAuto, setAddSlugAuto] = useState(true)
  const [editTerm, setEditTerm] = useState<MediaTerm | null>(null)
  const [editForm, setEditForm] = useState(emptyForm)
  const [editSlugAuto, setEditSlugAuto] = useState(false)
  const [quickEditId, setQuickEditId] = useState<number | null>(null)
  const [quickForm, setQuickForm] = useState(emptyForm)
  const [deleteTarget, setDeleteTarget] = useState<MediaTerm | null>(null)

  const termsQ = useQuery({
    queryKey: ['media-terms'],
    queryFn: () => apiFetch<TermsPayload>('content/media/terms'),
  })
  useQueryErrorToast(termsQ)

  const items = useMemo(() => {
    const data = termsQ.data
    if (!data) return []
    return kind === 'folder' ? data.folders : data.categories
  }, [termsQ.data, kind])

  const tree = useMemo(() => buildCategoryTree(items.map(toCategory)), [items])

  function invalidate() {
    void qc.invalidateQueries({ queryKey: ['media-terms'] })
    void qc.invalidateQueries({ queryKey: ['media'] })
  }

  const create = useMutation({
    mutationFn: () =>
      apiFetch<MediaTerm>('content/media/terms', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          kind,
          name: addForm.name.trim(),
          slug: addForm.slug.trim() || slugifyFromName(addForm.name),
          parent: addForm.parent,
          description: addForm.description,
        }),
      }),
    onSuccess: () => {
      setAddForm(emptyForm())
      setAddSlugAuto(true)
      invalidate()
      toast.success(t('common.saved'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const patch = useMutation({
    mutationFn: (payload: { id: number; body: TermFormState }) =>
      apiFetch<MediaTerm>(`content/media/terms/${payload.id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          kind,
          name: payload.body.name.trim(),
          slug: payload.body.slug.trim() || slugifyFromName(payload.body.name),
          parent: payload.body.parent,
          description: payload.body.description,
        }),
      }),
    onSuccess: () => {
      invalidate()
      toast.success(t('common.saved'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const del = useMutation({
    mutationFn: (id: number) =>
      apiFetch(`content/media/terms/${id}?kind=${kind}`, { method: 'DELETE' }),
    onSuccess: () => {
      invalidate()
      setDeleteTarget(null)
      toast.success(t('common.deleted'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  async function saveEdit() {
    if (!editTerm) return
    await patch.mutateAsync({ id: editTerm.id, body: editForm })
    setEditTerm(null)
  }

  async function saveQuickEdit() {
    if (quickEditId == null) return
    await patch.mutateAsync({ id: quickEditId, body: quickForm })
    setQuickEditId(null)
  }

  const addLabel = kind === 'folder' ? t('media.newFolder') : t('media.newCategory')
  const listTitle = kind === 'folder' ? t('media.foldersTitle') : t('media.categoriesTitle')

  return (
    <>
      <div className="grid gap-6 lg:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]">
        <Card className="gap-4 py-4 shadow-sm lg:sticky lg:top-4 lg:self-start">
          <CardHeader className="px-4 pb-0">
            <CardTitle className="text-base">{t('media.addSectionTitle')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 px-4">
            <TermFormFields
              form={addForm}
              onChange={setAddForm}
              items={items}
              slugAuto={addSlugAuto}
              onSlugAutoChange={setAddSlugAuto}
              idPrefix={`add-${kind}`}
              hierarchical={hierarchical}
            />
            <Button type="button" disabled={!addForm.name.trim() || create.isPending} onClick={() => void create.mutateAsync()}>
              {addLabel}
            </Button>
          </CardContent>
        </Card>

        <Card className="gap-4 py-4 shadow-sm">
          <CardHeader className="px-4 pb-0">
            <CardTitle className="text-base">{listTitle}</CardTitle>
          </CardHeader>
          <CardContent className="px-4">
            <TermList
              items={items}
              tree={tree}
              onQuickEdit={(term) => {
                setQuickEditId(term.id)
                setQuickForm(formFromTerm(term))
              }}
              onEdit={(term) => {
                setEditTerm(term)
                setEditForm(formFromTerm(term))
                setEditSlugAuto(false)
              }}
              onDelete={setDeleteTarget}
              quickEditId={quickEditId}
              quickForm={quickForm}
              onQuickFormChange={setQuickForm}
              onQuickSave={() => void saveQuickEdit()}
              onQuickCancel={() => setQuickEditId(null)}
              isLoading={termsQ.isLoading}
              isQuickSaving={patch.isPending}
            />
          </CardContent>
        </Card>
      </div>

      <Dialog open={editTerm != null} onOpenChange={(open) => !open && setEditTerm(null)}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('media.actionEdit')}</DialogTitle>
          </DialogHeader>
          {editTerm ? (
            <TermFormFields
              form={editForm}
              onChange={setEditForm}
              items={items}
              excludeId={editTerm.id}
              slugAuto={editSlugAuto}
              onSlugAutoChange={setEditSlugAuto}
              idPrefix={`edit-${kind}`}
              hierarchical={hierarchical}
            />
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setEditTerm(null)}>
              {t('media.quickEditCancel')}
            </Button>
            <Button type="button" disabled={patch.isPending} onClick={() => void saveEdit()}>
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteTarget != null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('media.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('media.deleteConfirmBody', { name: deleteTarget?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('media.quickEditCancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={() => deleteTarget && void del.mutateAsync(deleteTarget.id)}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
