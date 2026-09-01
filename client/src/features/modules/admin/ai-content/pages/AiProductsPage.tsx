import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { toastApiError } from '@/lib/apiError'
import { fetchAiCostEstimate, fetchIncompleteProducts, fillProductsBatch, generateAi } from '../lib/ai-content-api'
import { AiToman } from '../components/AiToman'
import { AiContentLayout } from '../components/AiContentLayout'

export default function AiProductsPage() {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()

  const listQ = useQuery({
    queryKey: ['ai-content', 'incomplete'],
    queryFn: () => fetchIncompleteProducts(80),
  })
  useQueryErrorToast(listQ)

  const costQ = useQuery({
    queryKey: ['ai-content', 'cost-estimate'],
    queryFn: () => fetchAiCostEstimate(),
  })
  useQueryErrorToast(costQ)

  const batch = useMutation({
    mutationFn: () => fillProductsBatch(),
    onSuccess: (res) => {
      toast.success(t('aiContent.batchQueued', { count: res.count }))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const one = useMutation({
    mutationFn: (id: number) => generateAi({ type: 'product', id, sync: false }),
    onSuccess: (res) => {
      if (!res?.job_id || res.job_id < 1) {
        toast.error(t('aiContent.jobQueueFailed'))
        return
      }
      toast.success(t('aiContent.jobQueued'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const n = listQ.data?.items?.length ?? 0
  const per = costQ.data?.entities.product?.cost_toman.mid ?? 0

  return (
    <AiContentLayout>
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <Button size="sm" onClick={() => void batch.mutateAsync()} disabled={batch.isPending}>
          {t('aiContent.fillIncomplete')}
        </Button>
        {n > 0 && per > 0 ? (
          <p className="text-muted-foreground text-sm">
            {t('aiContent.costBatchHint', { count: n })}{' '}
            <AiToman amount={per} locale={i18n.language} />
            {' × '}
            {n}
            {' ≈ '}
            <AiToman amount={per * n} locale={i18n.language} />
          </p>
        ) : null}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.incompleteTitle')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {(listQ.data?.items ?? []).map((row) => (
            <div key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
              <div>
                <Link className="font-medium underline-offset-2 hover:underline" to="#">
                  {row.name}
                </Link>
                <div className="text-muted-foreground">{row.missing.join(', ')}</div>
              </div>
              <Button size="sm" variant="outline" disabled={one.isPending} onClick={() => void one.mutateAsync(row.id)}>
                {t('aiContent.generate')}
              </Button>
            </div>
          ))}
          {!listQ.data?.items?.length ? (
            <p className="text-sm text-muted-foreground">{t('aiContent.noIncomplete')}</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
    </AiContentLayout>
  )
}
