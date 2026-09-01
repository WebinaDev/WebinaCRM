import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { toastApiError } from '@/lib/apiError'
import { apiFetch } from '@/lib/api'
import { buildCategoryTree, type Category } from '@/lib/categoryTree'
import { AiContentLayout } from '../components/AiContentLayout'
import {
  type CatAttrLabel,
  confirmAttrTemplate,
  deleteAttrTemplate,
  fetchAttrDraft,
  fetchAttrTemplates,
  saveAttrMapping,
  suggestAttrTemplate,
} from '../lib/ai-content-api'

type CatRow = { id: number; name: string; slug?: string; parent?: number }

type DraftAttr = { label: string; slug: string; options: string[] }

export default function AiAttributesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [activeCatId, setActiveCatId] = useState(0)
  const [query, setQuery] = useState('')
  const [draftEdits, setDraftEdits] = useState<DraftAttr[] | null>(null)
  const [picked, setPicked] = useState<number[]>([])
  const seededFor = useRef(0)

  const catsQ = useQuery({
    queryKey: ['product-categories', 'ai-attr'],
    queryFn: () => apiFetch<{ items: CatRow[] }>('shop/product-categories?sort=name_asc&per_page=500'),
  })
  useQueryErrorToast(catsQ)

  const listQ = useQuery({
    queryKey: ['ai-content', 'attr-templates'],
    queryFn: fetchAttrTemplates,
  })
  useQueryErrorToast(listQ)

  const draftQ = useQuery({
    queryKey: ['ai-content', 'attr-draft', activeCatId],
    queryFn: () => fetchAttrDraft(activeCatId),
    enabled: activeCatId > 0,
  })
  useQueryErrorToast(draftQ)

  const cats = catsQ.data?.items ?? []
  const treeRows = useMemo(() => {
    const asCategories: Category[] = cats.map((i) => ({
      id: i.id,
      name: i.name,
      slug: i.slug ?? '',
      parent: i.parent ?? 0,
      description: '',
      count: 0,
      url: '',
    }))
    const tree = buildCategoryTree(asCategories)
    const q = query.trim().toLowerCase()
    if (!q) return tree
    const matchIds = new Set(cats.filter((i) => i.name.toLowerCase().includes(q)).map((i) => i.id))
    const byId = new Map(asCategories.map((c) => [c.id, c]))
    const keep = new Set<number>()
    for (const id of matchIds) {
      let cur: number | undefined = id
      while (cur && cur > 0) {
        keep.add(cur)
        cur = byId.get(cur)?.parent
      }
    }
    return tree.filter(({ node }) => keep.has(node.id))
  }, [cats, query])

  const savedByCat = useMemo(() => {
    const map = new Map<number, string[]>()
    for (const row of listQ.data?.items ?? []) {
      map.set(row.product_cat_id, (row.labels ?? []).map((l) => l.label))
    }
    return map
  }, [listQ.data])

  const catAttrs = useMemo(() => {
    const byId = new Map<number, CatAttrLabel>()
    const saved = (listQ.data?.items ?? []).find((r) => r.product_cat_id === activeCatId)
    for (const row of saved?.labels ?? []) {
      const id = row.attribute_id ?? 0
      if (id > 0) byId.set(id, row)
    }
    for (const row of draftQ.data?.discovered ?? []) {
      const id = row.attribute_id ?? 0
      if (id > 0 && !byId.has(id)) byId.set(id, row)
    }
    for (const row of draftQ.data?.template?.labels ?? []) {
      const id = row.attribute_id ?? 0
      if (id > 0 && !byId.has(id)) byId.set(id, row)
    }
    return [...byId.values()]
  }, [activeCatId, listQ.data, draftQ.data])

  useEffect(() => {
    if (!activeCatId || seededFor.current === activeCatId) return
    const tplIds = draftQ.data?.template?.attribute_ids ?? []
    if (tplIds.length) {
      setPicked(tplIds)
      seededFor.current = activeCatId
      return
    }
    const saved = (listQ.data?.items ?? []).find((r) => r.product_cat_id === activeCatId)
    if (saved?.attribute_ids?.length) {
      setPicked(saved.attribute_ids)
      seededFor.current = activeCatId
      return
    }
    if (draftQ.isFetching) return
    const discovered = draftQ.data?.discovered ?? []
    setPicked(discovered.map((d) => d.attribute_id ?? 0).filter((id) => id > 0))
    seededFor.current = activeCatId
  }, [activeCatId, draftQ.data, draftQ.isFetching, listQ.data])

  const suggest = useMutation({
    mutationFn: () => suggestAttrTemplate(activeCatId),
    onSuccess: () => {
      toast.success(t('aiContent.jobQueued'))
      setTimeout(() => void draftQ.refetch(), 3000)
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const saveMap = useMutation({
    mutationFn: () => saveAttrMapping(activeCatId, picked),
    onSuccess: () => {
      toast.success(t('common.saved'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const serverDraft = draftQ.data?.draft?.attributes ?? []
  const draftAttrs = draftEdits ?? serverDraft

  const confirm = useMutation({
    mutationFn: () => confirmAttrTemplate(activeCatId, { attributes: draftAttrs }),
    onSuccess: () => {
      toast.success(t('common.saved'))
      setDraftEdits(null)
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const remove = useMutation({
    mutationFn: (id: number) => deleteAttrTemplate(id),
    onSuccess: () => {
      toast.success(t('common.deleted'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const toggleAttr = (id: number, on: boolean) => {
    setPicked((base) => (on ? (base.includes(id) ? base : [...base, id]) : base.filter((x) => x !== id)))
  }

  const selectCat = (id: number) => {
    setActiveCatId(id)
    seededFor.current = 0
    setDraftEdits(null)
    const row = (listQ.data?.items ?? []).find((r) => r.product_cat_id === id)
    setPicked(row?.attribute_ids ?? [])
  }

  return (
    <AiContentLayout>
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{t('aiContent.attributesTitle')}</h1>
        <p className="text-muted-foreground mt-1 text-sm">{t('aiContent.attrPageLead')}</p>
      </div>

      <div className="grid gap-4 lg:grid-cols-[minmax(16rem,22rem)_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>{t('aiContent.attrPickCategory')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={t('products.editor.taxonomySearch')}
              className="h-8 text-sm"
            />
            <div className="max-h-[28rem] overflow-y-auto rounded-md border p-1.5 text-sm">
              {catsQ.isLoading ? (
                <p className="text-muted-foreground px-1 py-2 text-xs">{t('common.loading')}</p>
              ) : treeRows.length === 0 ? (
                <p className="text-muted-foreground px-1 py-2 text-xs">{t('products.noCategories')}</p>
              ) : (
                treeRows.map(({ node, depth }) => {
                  const mapped = savedByCat.has(node.id)
                  const active = activeCatId === node.id
                  return (
                    <button
                      key={node.id}
                      type="button"
                      className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start ${
                        active ? 'bg-muted font-medium' : 'hover:bg-muted/60'
                      }`}
                      style={{ paddingInlineStart: `${0.5 + depth * 0.875}rem` }}
                      onClick={() => selectCat(node.id)}
                    >
                      <span className="min-w-0 flex-1 truncate">{node.name}</span>
                      {mapped ? (
                        <span className="text-muted-foreground shrink-0 text-[10px]">{t('aiContent.attrMapped')}</span>
                      ) : null}
                    </button>
                  )
                })
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>
              {activeCatId
                ? t('aiContent.attrForCategory', {
                    name: cats.find((c) => c.id === activeCatId)?.name ?? `#${activeCatId}`,
                  })
                : t('aiContent.attrSelectCategoryFirst')}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!activeCatId ? (
              <p className="text-muted-foreground text-sm">{t('aiContent.attrSelectCategoryFirst')}</p>
            ) : (
              <>
                <div className="max-h-72 space-y-1 overflow-y-auto rounded-md border p-2">
                  {catAttrs.map((attr) => {
                    const id = attr.attribute_id ?? 0
                    const opts = attr.options ?? []
                    return (
                      <label key={id} className="flex cursor-pointer items-start gap-2 py-1 text-sm">
                        <Checkbox
                          className="mt-0.5"
                          checked={picked.includes(id)}
                          onCheckedChange={(v) => toggleAttr(id, v === true)}
                        />
                        <span className="min-w-0 flex-1">
                          <span className="block truncate">{attr.label}</span>
                          {opts.length ? (
                            <span className="text-muted-foreground block text-xs">{opts.join(' · ')}</span>
                          ) : null}
                        </span>
                      </label>
                    )
                  })}
                  {!catAttrs.length ? (
                    <p className="text-muted-foreground text-xs">{t('aiContent.noCategoryAttributes')}</p>
                  ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" disabled={saveMap.isPending} onClick={() => void saveMap.mutateAsync()}>
                    {t('aiContent.saveMapping')}
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={suggest.isPending}
                    onClick={() => void suggest.mutateAsync()}
                  >
                    {t('aiContent.suggest')}
                  </Button>
                  <Button size="sm" variant="ghost" onClick={() => void draftQ.refetch()}>
                    {t('common.refresh')}
                  </Button>
                </div>
                <div className="space-y-2">
                  <div className="text-sm font-medium">{t('aiContent.attrSuggestTitle')}</div>
                  {draftAttrs.map((a, i) => (
                    <div key={`${a.label}-${i}`} className="space-y-1 rounded-md border p-2 text-sm">
                      <div className="flex items-center justify-between gap-2">
                        <Input
                          className="h-8"
                          value={a.label}
                          onChange={(e) => {
                            const next = draftAttrs.map((row, idx) =>
                              idx === i ? { ...row, label: e.target.value } : row,
                            )
                            setDraftEdits(next)
                          }}
                        />
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => setDraftEdits(draftAttrs.filter((_, idx) => idx !== i))}
                        >
                          {t('common.delete')}
                        </Button>
                      </div>
                      <Input
                        className="h-8 text-xs"
                        value={(a.options ?? []).join(', ')}
                        onChange={(e) => {
                          const options = e.target.value
                            .split(',')
                            .map((s) => s.trim())
                            .filter(Boolean)
                          const next = draftAttrs.map((row, idx) => (idx === i ? { ...row, options } : row))
                          setDraftEdits(next)
                        }}
                      />
                    </div>
                  ))}
                  {!draftAttrs.length ? (
                    <p className="text-muted-foreground text-sm">{t('aiContent.noAttrDraft')}</p>
                  ) : (
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={confirm.isPending}
                      onClick={() => void confirm.mutateAsync()}
                    >
                      {t('aiContent.confirmTemplate')}
                    </Button>
                  )}
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.attrTemplates')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {(listQ.data?.items ?? []).map((row) => (
            <div
              key={row.id}
              className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0"
            >
              <button type="button" className="text-start" onClick={() => selectCat(row.product_cat_id)}>
                <div className="font-medium">{row.category_name || `#${row.product_cat_id}`}</div>
                <div className="text-muted-foreground">
                  {(row.labels ?? []).map((l) => l.label).join(' · ') || row.attribute_ids.join(', ')}
                </div>
              </button>
              <Button size="sm" variant="destructive" onClick={() => void remove.mutateAsync(row.product_cat_id)}>
                {t('common.delete')}
              </Button>
            </div>
          ))}
          {!listQ.data?.items?.length ? (
            <p className="text-muted-foreground text-sm">{t('aiContent.noTemplates')}</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
    </AiContentLayout>
  )
}
