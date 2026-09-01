import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { toastApiError } from '@/lib/apiError'
import { fetchAiPages, generateAi } from '../lib/ai-content-api'
import { AiContentLayout } from '../components/AiContentLayout'

export default function AiPagesPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [search, setSearch] = useState('')
  const [prompts, setPrompts] = useState<Record<number, string>>({})

  const listQ = useQuery({
    queryKey: ['ai-content', 'pages', search],
    queryFn: () => fetchAiPages(1, search),
  })
  useQueryErrorToast(listQ)

  const one = useMutation({
    mutationFn: (row: { id: number }) =>
      generateAi({
        type: 'page',
        id: row.id,
        page_prompt: prompts[row.id] ?? listQ.data?.items.find((i) => i.id === row.id)?.page_prompt ?? '',
        run_now: false,
      }),
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

  return (
    <AiContentLayout>
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{t('aiContent.pagesTitle')}</h1>
        <p className="text-muted-foreground mt-1 text-sm">{t('aiContent.pagesLead')}</p>
      </div>
      {!listQ.data?.elementor ? (
        <p className="text-destructive text-sm">{t('aiContent.elementorRequired')}</p>
      ) : null}
      <Input
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder={t('aiContent.pagesSearch')}
        className="max-w-sm"
      />
      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.pagesList')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {(listQ.data?.items ?? []).map((row) => (
            <div key={row.id} className="space-y-2 border-b py-3 last:border-0">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <Link className="font-medium underline-offset-2 hover:underline" to={`/pages/${row.id}`}>
                    {row.title || `#${row.id}`}
                  </Link>
                  <div className="text-muted-foreground text-xs">
                    {row.status}
                    {row.has_elementor ? ` · ${t('aiContent.hasElementor')}` : ''}
                  </div>
                </div>
                <div className="flex gap-2">
                  {row.elementor_url ? (
                    <Button size="sm" variant="ghost" asChild>
                      <a href={row.elementor_url} target="_blank" rel="noreferrer">
                        {t('pages.actionElementor')}
                      </a>
                    </Button>
                  ) : null}
                  <Button size="sm" variant="outline" disabled={one.isPending || !listQ.data?.elementor} onClick={() => void one.mutateAsync(row)}>
                    {t('aiContent.generate')}
                  </Button>
                </div>
              </div>
              <Textarea
                rows={2}
                value={prompts[row.id] ?? row.page_prompt}
                onChange={(e) => setPrompts((p) => ({ ...p, [row.id]: e.target.value }))}
                placeholder={t('aiContent.pagePromptPlaceholder')}
              />
            </div>
          ))}
          {!listQ.data?.items?.length ? (
            <p className="text-sm text-muted-foreground">{t('aiContent.noPages')}</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
    </AiContentLayout>
  )
}
