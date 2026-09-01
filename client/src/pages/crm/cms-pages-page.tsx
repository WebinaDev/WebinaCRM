import { useEffect, useState } from 'react'
import { Link, useMatch, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { PageShell } from '@/components/PageShell'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Skeleton } from '@/components/ui/skeleton'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { apiFetch } from '@/lib/api'
import { toastApiError } from '@/lib/apiError'

type PageRow = {
  id: number
  title: string
  status: string
  elementor_url?: string
  url?: string
}

type Page = {
  id: number
  title: string
  excerpt: string
  status: string
  ai_page_prompt?: string
  elementor_url?: string
}

function PagesList() {
  const { t } = useTranslation()
  const listQ = useQuery({
    queryKey: ['cms-pages'],
    queryFn: () => apiFetch<{ items: PageRow[] }>('content/pages?per_page=50'),
  })
  useQueryErrorToast(listQ)

  return (
    <PageShell title={t('aiContent.pagesTitle')}>
      <div className="mb-3 flex justify-end">
        <Button asChild size="sm">
          <Link to="/pages/new">{t('pages.newTitle')}</Link>
        </Button>
      </div>
      <Card>
        <CardContent className="space-y-2 pt-4">
          {(listQ.data?.items ?? []).map((row) => (
            <div key={row.id} className="flex items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
              <Link className="font-medium underline-offset-2 hover:underline" to={`/pages/${row.id}`}>
                {row.title || `#${row.id}`}
              </Link>
              <span className="text-muted-foreground text-xs">{row.status}</span>
            </div>
          ))}
          {!listQ.data?.items?.length ? (
            <p className="text-muted-foreground text-sm">{t('aiContent.noPages')}</p>
          ) : null}
        </CardContent>
      </Card>
    </PageShell>
  )
}

function PageEditor() {
  const { t } = useTranslation()
  const nav = useNavigate()
  const qc = useQueryClient()
  const isNew = useMatch('/pages/new')
  const { pageId } = useParams<{ pageId: string }>()
  const id = isNew ? undefined : pageId ? parseInt(pageId, 10) : undefined

  const [title, setTitle] = useState('')
  const [excerpt, setExcerpt] = useState('')
  const [status, setStatus] = useState('draft')
  const [aiPagePrompt, setAiPagePrompt] = useState('')
  const [siteDescription, setSiteDescription] = useState('')

  const pageQ = useQuery({
    queryKey: ['cms-page', id],
    queryFn: () => apiFetch<Page>(`content/pages/${id}`),
    enabled: Boolean(id),
  })
  useQueryErrorToast(pageQ)

  const siteQ = useQuery({
    queryKey: ['ai-content', 'site-profile'],
    queryFn: () => apiFetch<{ site_description?: string; site_topic?: string }>('ai-content/site-profile'),
    retry: false,
  })

  useEffect(() => {
    if (!pageQ.data) return
    setTitle(pageQ.data.title)
    setExcerpt(pageQ.data.excerpt ?? '')
    setStatus(pageQ.data.status)
    setAiPagePrompt(pageQ.data.ai_page_prompt ?? '')
  }, [pageQ.data])

  useEffect(() => {
    if (!siteQ.data) return
    setSiteDescription((siteQ.data.site_description || siteQ.data.site_topic || '').trim())
  }, [siteQ.data])

  const save = useMutation({
    mutationFn: async () => {
      const body = { title, excerpt, status, ai_page_prompt: aiPagePrompt }
      if (id) {
        return apiFetch<Page>(`content/pages/${id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        })
      }
      return apiFetch<Page>('content/pages', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      })
    },
    onSuccess: (p) => {
      void qc.invalidateQueries({ queryKey: ['cms-pages'] })
      toast.success(t('common.saved'))
      if (!id && p.id) nav(`/pages/${p.id}`, { replace: true })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const generateAi = useMutation({
    mutationFn: async () => {
      if (!id) throw new Error('save first')
      return apiFetch<{ ok: boolean; job_id: number }>('ai-content/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'page', id, page_prompt: aiPagePrompt, run_now: false }),
      })
    },
    onSuccess: (res) => {
      if (!res?.job_id) {
        toast.error(t('aiContent.jobQueueFailed'))
        return
      }
      toast.success(t('aiContent.jobQueued'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  if (id && pageQ.isError) {
    return (
      <PageShell title={t('pages.editTitle')}>
        <p className="text-destructive text-sm">{pageQ.error?.message || t('common.error')}</p>
        <Button type="button" size="sm" className="mt-2" onClick={() => void pageQ.refetch()}>
          {t('common.retry')}
        </Button>
      </PageShell>
    )
  }

  const loading = Boolean(id) && pageQ.isLoading

  return (
    <PageShell title={id ? t('pages.editTitle') : t('pages.newTitle')}>
      <div className="mb-3 flex justify-end gap-2">
        <Button type="button" size="sm" variant="outline" disabled={save.isPending} onClick={() => void save.mutateAsync()}>
          {t('common.save')}
        </Button>
      </div>
      <div className="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">{t('pages.fieldTitle')}</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? <Skeleton className="h-10 w-full" /> : (
              <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder={t('pages.titlePlaceholder')} />
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">{t('pages.fieldExcerpt')}</CardTitle>
          </CardHeader>
          <CardContent>
            <Textarea rows={3} value={excerpt} onChange={(e) => setExcerpt(e.target.value)} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">{t('aiContent.pageAiCard')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {siteDescription ? (
              <p className="text-muted-foreground text-xs">
                {t('aiContent.siteDescription')}: {siteDescription}{' '}
                <Link className="underline-offset-2 hover:underline" to="/ai-content/settings">
                  {t('aiContent.editSiteDescription')}
                </Link>
              </p>
            ) : (
              <Link className="text-muted-foreground text-xs underline-offset-2 hover:underline" to="/ai-content/settings">
                {t('aiContent.editSiteDescription')}
              </Link>
            )}
            <Textarea
              rows={4}
              value={aiPagePrompt}
              onChange={(e) => setAiPagePrompt(e.target.value)}
              placeholder={t('aiContent.pagePromptPlaceholder')}
            />
            <div className="flex flex-wrap gap-2">
              <Button type="button" size="sm" disabled={!id || generateAi.isPending} onClick={() => void generateAi.mutateAsync()}>
                {t('aiContent.generatePage')}
              </Button>
              {pageQ.data?.elementor_url ? (
                <Button type="button" size="sm" variant="outline" asChild>
                  <a href={pageQ.data.elementor_url} target="_blank" rel="noreferrer">
                    {t('pages.actionElementor')}
                  </a>
                </Button>
              ) : null}
            </div>
          </CardContent>
        </Card>
      </div>
    </PageShell>
  )
}

export function CmsPagesPage() {
  const isNew = useMatch('/pages/new')
  const { pageId } = useParams<{ pageId: string }>()
  if (isNew || pageId) return <PageEditor />
  return <PagesList />
}
