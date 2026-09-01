import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { toastApiError } from '@/lib/apiError'
import { AiJobCard, isActiveJob } from '../components/AiJobCard'
import { AiToman } from '../components/AiToman'
import { AiContentLayout } from '../components/AiContentLayout'
import {
  cancelAiJob,
  cancelPendingAiJobs,
  fetchAiJobs,
  fetchAiQueue,
  retryAiJob,
  runDueJobs,
  setAiQueuePaused,
} from '../lib/ai-content-api'

const FILTERS = ['', 'pending', 'running', 'failed', 'cancelled', 'done'] as const

export default function AiJobsPage() {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()
  const [params, setParams] = useSearchParams()
  const status = params.get('status') ?? ''
  const filter = FILTERS.includes(status as (typeof FILTERS)[number]) ? status : ''

  const jobsQ = useQuery({
    queryKey: ['ai-content', 'jobs', filter],
    queryFn: () => fetchAiJobs({ status: filter || undefined, limit: 80 }),
    refetchInterval: (q) => ((q.state.data?.items ?? []).some(isActiveJob) ? 2000 : false),
  })
  useQueryErrorToast(jobsQ)

  const queueQ = useQuery({
    queryKey: ['ai-content', 'queue'],
    queryFn: fetchAiQueue,
  })
  useQueryErrorToast(queueQ)
  const paused = !!queueQ.data?.paused

  const retry = useMutation({
    mutationFn: (id: number) => retryAiJob(id),
    onSuccess: () => {
      toast.success(t('aiContent.jobRetried'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const runDue = useMutation({
    mutationFn: async () => {
      let total = 0
      for (let guard = 0; guard < 200; guard++) {
        const snap = await fetchAiJobs({ limit: 80 })
        const items = snap.items ?? []
        if (items.some((j) => j.status === 'running')) {
          await new Promise((r) => setTimeout(r, 2000))
          continue
        }
        if (!items.some((j) => j.status === 'pending')) {
          break
        }
        const res = await runDueJobs(1)
        if (res.paused) {
          return { processed: [], count: total, paused: true as const }
        }
        if ((res.count || 0) < 1) {
          break
        }
        total += res.count
        await new Promise((r) => setTimeout(r, 1500))
      }
      return { processed: [], count: total }
    },
    onSuccess: (res) => {
      if (res.paused) {
        toast.message(t('aiContent.queuePausedHint'))
        return
      }
      toast.success(t('aiContent.jobsRunDueDone', { count: res.count }))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const pause = useMutation({
    mutationFn: (next: boolean) => setAiQueuePaused(next),
    onSuccess: (res) => {
      qc.setQueryData(['ai-content', 'queue'], { paused: res.paused })
      toast.success(res.paused ? t('aiContent.queuePaused') : t('aiContent.queueResumed'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const cancelOne = useMutation({
    mutationFn: (id: number) => cancelAiJob(id),
    onSuccess: () => {
      toast.success(t('aiContent.jobCancelled'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const cancelPending = useMutation({
    mutationFn: () => cancelPendingAiJobs(),
    onSuccess: (res) => {
      toast.success(t('aiContent.jobsCancelledCount', { count: res.count }))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const setFilter = (next: string) => {
    const nextParams = new URLSearchParams(params)
    if (next) nextParams.set('status', next)
    else nextParams.delete('status')
    setParams(nextParams, { replace: true })
  }

  const hasPending = (jobsQ.data?.items ?? []).some((j) => j.status === 'pending')
  const listItems = jobsQ.data?.items ?? []
  const costSum = listItems.reduce((s, j) => s + (Number(j.cost_toman) || 0), 0)
  const tokenIn = listItems.reduce((s, j) => s + (j.tokens_in || 0), 0)
  const tokenOut = listItems.reduce((s, j) => s + (j.tokens_out || 0), 0)

  return (
    <AiContentLayout>
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        {FILTERS.map((key) => (
          <Button
            key={key || 'all'}
            size="sm"
            variant={filter === key ? 'default' : 'outline'}
            onClick={() => setFilter(key)}
          >
            {t(key ? `aiContent.jobsFilter.${key}` : 'aiContent.jobsFilter.all')}
          </Button>
        ))}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Button
          size="sm"
          variant={paused ? 'default' : 'outline'}
          disabled={pause.isPending}
          onClick={() => void pause.mutateAsync(!paused)}
        >
          {paused ? t('aiContent.queueResume') : t('aiContent.queuePause')}
        </Button>
        <Button
          size="sm"
          variant="outline"
          disabled={cancelPending.isPending || !hasPending}
          onClick={() => void cancelPending.mutateAsync()}
        >
          {t('aiContent.jobsCancelPending')}
        </Button>
        <Button
          size="sm"
          className="ms-auto"
          disabled={runDue.isPending || paused}
          onClick={() => void runDue.mutateAsync()}
        >
          {runDue.isPending ? t('aiContent.jobsRunDueRunning') : t('aiContent.jobsRunDue')}
        </Button>
      </div>
      {paused ? <p className="text-muted-foreground text-sm">{t('aiContent.queuePausedHint')}</p> : null}
      {listItems.length ? (
        <p className="text-muted-foreground text-sm">
          {t('aiContent.jobsCostTotal')} <AiToman amount={costSum} locale={i18n.language} />
          {tokenIn || tokenOut
            ? ` · ${t('aiContent.jobsTokens', { inCount: tokenIn, outCount: tokenOut })}`
            : ''}
        </p>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.jobsPageTitle')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {jobsQ.isPending ? <Skeleton className="h-24 w-full rounded-xl" /> : null}
          {(jobsQ.data?.items ?? []).map((job) => (
            <AiJobCard
              key={job.id}
              job={job}
              retryPending={retry.isPending}
              cancelPending={cancelOne.isPending}
              onRetry={(id) => void retry.mutateAsync(id)}
              onCancel={(id) => void cancelOne.mutateAsync(id)}
            />
          ))}
          {!jobsQ.isPending && !jobsQ.data?.items?.length ? (
            <p className="text-sm text-muted-foreground">{t('aiContent.noJobs')}</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
    </AiContentLayout>
  )
}
