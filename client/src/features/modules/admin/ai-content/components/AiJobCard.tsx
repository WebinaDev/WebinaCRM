import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useLocale } from '@/hooks/use-locale'
import { jobPhase, jobPhasePercent } from '@/lib/aiJobProgress'
import type { AiJob } from '../lib/ai-content-api'
import { AiToman } from './AiToman'

export function jobTargetHref(job: AiJob): string | null {
  if (job.target_type === 'calendar') return '/ai-content/calendar'
  if (job.target_id < 1) return null
  if (job.target_type === 'product') return '#'
  if (job.target_type === 'post') return `/magazine/posts/${job.target_id}`
  if (job.target_type === 'product_cat' || job.target_type === 'product_brand') {
    return '/ai-content/taxonomies'
  }
  return null
}

export function isActiveJob(job: AiJob) {
  return job.status === 'pending' || job.status === 'running'
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === 'done') return 'default'
  if (status === 'failed' || status === 'cancelled') return 'destructive'
  if (status === 'running') return 'secondary'
  return 'outline'
}

function humanJobError(raw: string, t: (key: string) => string): { title: string; detail: string } {
  const s = raw.trim()
  if (/cURL error 28|timed out|timeout/i.test(s)) {
    return { title: t('aiContent.jobError.timeout'), detail: s }
  }
  if (/cURL error/i.test(s)) {
    return { title: t('aiContent.jobError.network'), detail: s }
  }
  return { title: s, detail: '' }
}

export function AiJobCard({
  job,
  onRetry,
  onCancel,
  retryPending,
  cancelPending,
}: {
  job: AiJob
  onRetry?: (id: number) => void
  onCancel?: (id: number) => void
  retryPending?: boolean
  cancelPending?: boolean
}) {
  const { t, i18n } = useTranslation()
  const { formatDateTime } = useLocale()
  const href = jobTargetHref(job)
  const title =
    (job.target_title || '').trim() ||
    t('aiContent.jobUntitled')
  const titleNode = href ? (
    <Link className="hover:underline underline-offset-2" to={href}>
      {title}
    </Link>
  ) : (
    <span>{title}</span>
  )
  const err = job.error_message ? humanJobError(job.error_message, t) : null
  const showActions = !!(onRetry || onCancel)

  return (
    <div className="flex flex-wrap items-start justify-between gap-3 border-b py-3 last:border-0">
      <div className="min-w-0 flex-1 space-y-2">
        <div className="text-sm font-semibold leading-snug">{titleNode}</div>
        <div className="flex flex-wrap gap-1.5">
          <Badge variant="outline">{t('aiContent.jobId', { id: job.id })}</Badge>
          <Badge variant="secondary">
            {t(`aiContent.jobType.${job.job_type}`, { defaultValue: job.job_type })}
          </Badge>
          <Badge variant={statusVariant(job.status)}>
            {t(`aiContent.jobStatus.${job.status}`, { defaultValue: job.status })}
          </Badge>
          {job.provider ? <Badge variant="outline">{job.provider}</Badge> : null}
          {job.model ? <Badge variant="outline">{job.model}</Badge> : null}
        </div>
        <div className="flex flex-wrap gap-1.5">
          {job.target_type ? (
            <Badge variant="outline">
              {t(`aiContent.targetType.${job.target_type}`, { defaultValue: job.target_type })}
              {job.target_id > 0 ? ` #${job.target_id}` : ''}
            </Badge>
          ) : null}
          {job.attempts > 0 ? (
            <Badge variant="outline">{t('aiContent.jobsAttempts', { count: job.attempts })}</Badge>
          ) : null}
          {job.tokens_in > 0 ? (
            <Badge variant="outline">{t('aiContent.jobTokensIn', { count: job.tokens_in })}</Badge>
          ) : null}
          {job.tokens_out > 0 ? (
            <Badge variant="outline">{t('aiContent.jobTokensOut', { count: job.tokens_out })}</Badge>
          ) : null}
          {Number(job.cost_toman) > 0 ? (
            <Badge variant="outline" className="gap-1">
              <AiToman amount={Number(job.cost_toman)} locale={i18n.language} />
              {job.cost_estimated ? ` · ${t('aiContent.costApprox')}` : ''}
            </Badge>
          ) : null}
          {job.created_at ? (
            <Badge variant="outline">{formatDateTime(job.created_at)}</Badge>
          ) : null}
        </div>
        {err ? (
          <div className="border-destructive/30 bg-destructive/5 text-destructive space-y-0.5 rounded-md border px-2.5 py-2 text-xs">
            <div className="font-medium">{err.title}</div>
            {err.detail && err.detail !== err.title ? (
              <div className="text-destructive/80 break-words">{err.detail}</div>
            ) : null}
          </div>
        ) : isActiveJob(job) ? (
          <div className="space-y-1">
            <div className="bg-muted h-1.5 overflow-hidden rounded-full">
              <div
                className="bg-primary h-full transition-[width] duration-500"
                style={{ width: `${jobPhasePercent(jobPhase(job))}%` }}
              />
            </div>
            <div className="text-muted-foreground text-xs">
              {t(`aiContent.phase.${jobPhase(job)}`, { defaultValue: jobPhase(job) })}
            </div>
          </div>
        ) : job.result_summary &&
          !['queued', 'provider', 'seo', 'writing', 'done', 'cancelled'].includes(job.result_summary) ? (
          <div className="text-muted-foreground text-xs">{job.result_summary}</div>
        ) : null}
      </div>
      {showActions ? (
        <div className="flex shrink-0 gap-2">
          {isActiveJob(job) && onCancel ? (
            <Button size="sm" variant="outline" disabled={cancelPending} onClick={() => onCancel(job.id)}>
              {t('aiContent.jobsCancel')}
            </Button>
          ) : null}
          {(job.status === 'failed' || job.status === 'cancelled') && onRetry ? (
            <Button size="sm" variant="outline" disabled={retryPending} onClick={() => onRetry(job.id)}>
              {t('aiContent.retry')}
            </Button>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
