import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { toastApiError } from '@/lib/apiError'
import { fetchAiJobs, fetchAiOverview, retryAiJob } from '../lib/ai-content-api'
import { AiJobCard } from '../components/AiJobCard'
import { AiToman } from '../components/AiToman'
import { AiContentLayout } from '../components/AiContentLayout'

const STAT_LINKS: Record<string, string> = {
  pending: '/ai-content/jobs?status=pending',
  failed: '/ai-content/jobs?status=failed',
  done: '/ai-content/jobs?status=done',
  calendar: '/ai-content/calendar',
}

export default function AiOverviewPage() {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()

  const overviewQ = useQuery({
    queryKey: ['ai-content', 'overview'],
    queryFn: fetchAiOverview,
    refetchInterval: (q) => ((q.state.data?.jobs_pending ?? 0) > 0 ? 3000 : false),
  })
  useQueryErrorToast(overviewQ)

  const jobsQ = useQuery({
    queryKey: ['ai-content', 'jobs', 'recent'],
    queryFn: () => fetchAiJobs({ limit: 8 }),
    refetchInterval: (q) =>
      (q.state.data?.items ?? []).some((j) => j.status === 'pending' || j.status === 'running')
        ? 3000
        : false,
  })
  useQueryErrorToast(jobsQ)

  const retry = useMutation({
    mutationFn: (id: number) => retryAiJob(id),
    onSuccess: () => {
      toast.success(t('aiContent.jobRetried'))
      void qc.invalidateQueries({ queryKey: ['ai-content'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const o = overviewQ.data

  return (
    <AiContentLayout>
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        <Button asChild variant="outline" size="sm">
          <Link to="/ai-content/jobs">{t('aiContent.navJobs')}</Link>
        </Button>
        <Button asChild variant="outline" size="sm">
          <Link to="/ai-content/calendar">{t('aiContent.navCalendar')}</Link>
        </Button>
        <Button asChild variant="outline" size="sm">
          <Link to="/ai-content/products">{t('aiContent.navProducts')}</Link>
        </Button>
        <Button asChild variant="outline" size="sm">
          <Link to="/ai-content/taxonomies">{t('aiContent.navTaxonomies')}</Link>
        </Button>
        <Button asChild variant="outline" size="sm">
          <Link to="/ai-content/attributes">{t('aiContent.navAttributes')}</Link>
        </Button>
        <Button asChild variant="outline" size="sm">
          <Link to="/ai-content/settings">{t('aiContent.navSettings')}</Link>
        </Button>
      </div>

      {overviewQ.isPending ? (
        <Skeleton className="h-32 w-full rounded-xl" />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          {[
            ['pending', o?.jobs_pending ?? 0],
            ['failed', o?.jobs_failed ?? 0],
            ['done', o?.jobs_done ?? 0],
            ['calendar', o?.calendar_upcoming ?? 0],
          ].map(([key, val]) => (
            <Link key={String(key)} to={STAT_LINKS[String(key)] ?? '/ai-content/jobs'} className="block">
              <Card className="h-full transition-colors hover:bg-muted/40">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    {t(`aiContent.stat.${key}`)}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-semibold">{val}</div>
                </CardContent>
              </Card>
            </Link>
          ))}
          <Link to="/ai-content/jobs" className="block">
            <Card className="h-full transition-colors hover:bg-muted/40">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                  {t('aiContent.stat.spend')}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-semibold">
                  <AiToman amount={o?.jobs_cost_toman ?? 0} locale={i18n.language} />
                </div>
              </CardContent>
            </Card>
          </Link>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.incompleteTitle')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <p className="text-sm text-muted-foreground">
            {t('aiContent.incompleteCount', { count: o?.incomplete_products ?? 0 })}
          </p>
          {(o?.sample_incomplete ?? []).map((row) => (
            <div key={row.id} className="flex items-center justify-between gap-2 text-sm">
              <Link className="underline-offset-2 hover:underline" to="#">
                {row.name}
              </Link>
              <span className="text-muted-foreground">{row.missing.join(', ')}</span>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <CardTitle>{t('aiContent.jobsTitle')}</CardTitle>
          <Button asChild variant="ghost" size="sm">
            <Link to="/ai-content/jobs">{t('aiContent.jobsViewAll')}</Link>
          </Button>
        </CardHeader>
        <CardContent className="space-y-2">
          {(jobsQ.data?.items ?? []).map((job) => (
            <AiJobCard
              key={job.id}
              job={job}
              retryPending={retry.isPending}
              onRetry={(id) => void retry.mutateAsync(id)}
            />
          ))}
          {!jobsQ.data?.items?.length ? (
            <p className="text-sm text-muted-foreground">{t('aiContent.noJobs')}</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
    </AiContentLayout>
  )
}
