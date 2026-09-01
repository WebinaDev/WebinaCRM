import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { toastApiError } from '@/lib/apiError'
import { AiContentLayout } from '../components/AiContentLayout'
import {
  applyAiProposal,
  enqueueAiProposals,
  fetchAiProposals,
  requeueAiProposal,
  skipAiProposal,
} from '../lib/ai-content-api'

export default function AiTitlesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [edits, setEdits] = useState<Record<number, string>>({})

  const listQ = useQuery({
    queryKey: ['ai-content', 'proposals', 'title'],
    queryFn: () => fetchAiProposals('title', 'pending', 500),
    refetchInterval: 4000,
  })
  useQueryErrorToast(listQ)

  useEffect(() => {
    const next: Record<number, string> = {}
    for (const row of listQ.data?.items ?? []) {
      const name = String((row.proposed as { name?: string }).name ?? '')
      next[row.id] = name
    }
    setEdits((prev) => {
      const merged = { ...next }
      for (const id of Object.keys(prev)) {
        const nid = Number(id)
        if (merged[nid] !== undefined && prev[nid] !== undefined && prev[nid] !== next[nid]) {
          merged[nid] = prev[nid]
        }
      }
      return merged
    })
  }, [listQ.data?.items])

  const enqueue = useMutation({
    mutationFn: () => enqueueAiProposals('title'),
    onSuccess: (res) => {
      toast.success(t('aiContent.batchQueued', { count: res.count }))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const apply = useMutation({
    mutationFn: (id: number) => applyAiProposal(id, { name: edits[id] ?? '' }),
    onSuccess: () => {
      toast.success(t('aiContent.proposalApplied'))
      void qc.invalidateQueries({ queryKey: ['ai-content', 'proposals', 'title'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const skip = useMutation({
    mutationFn: (id: number) => skipAiProposal(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['ai-content', 'proposals', 'title'] }),
    onError: (e: Error) => toastApiError(t, e),
  })

  const redo = useMutation({
    mutationFn: (productId: number) => requeueAiProposal('title', productId),
    onSuccess: () => {
      toast.success(t('aiContent.jobQueued'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <AiContentLayout>
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <Button size="sm" onClick={() => void enqueue.mutateAsync()} disabled={enqueue.isPending}>
          {t('aiContent.titleSuggestAll')}
        </Button>
        <p className="text-muted-foreground text-sm">{t('aiContent.titleSuggestAllHint')}</p>
      </div>
      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.titlesPageTitle')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {(listQ.data?.items ?? []).map((row) => {
            const current = String((row.current as { name?: string }).name ?? row.product_name)
            return (
              <div key={row.id} className="grid gap-2 border-b py-3 last:border-0 md:grid-cols-[1fr_1fr_auto]">
                <div>
                  <div className="text-muted-foreground text-xs">{t('aiContent.titleCurrent')}</div>
                  <Link className="text-sm font-medium underline-offset-2 hover:underline" to="#">
                    {current}
                  </Link>
                </div>
                <div>
                  <div className="text-muted-foreground text-xs">{t('aiContent.titleProposed')}</div>
                  <Input
                    value={edits[row.id] ?? ''}
                    onChange={(e) => setEdits((s) => ({ ...s, [row.id]: e.target.value }))}
                  />
                </div>
                <div className="flex flex-wrap items-end gap-2">
                  <Button size="sm" disabled={apply.isPending} onClick={() => void apply.mutateAsync(row.id)}>
                    {t('aiContent.proposalApply')}
                  </Button>
                  <Button size="sm" variant="outline" disabled={skip.isPending} onClick={() => void skip.mutateAsync(row.id)}>
                    {t('aiContent.proposalSkip')}
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    disabled={redo.isPending}
                    onClick={() => void redo.mutateAsync(row.product_id)}
                  >
                    {t('aiContent.titleRedo')}
                  </Button>
                </div>
              </div>
            )
          })}
          {!listQ.data?.items?.length ? (
            <p className="text-sm text-muted-foreground">{t('aiContent.noTitleProposals')}</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
    </AiContentLayout>
  )
}
