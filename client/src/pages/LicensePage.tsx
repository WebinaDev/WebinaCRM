import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, KeyRound, LayoutDashboard, RefreshCw, ShieldCheck, Stethoscope } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { licenseCrmUnreachableMessage, licenseStatusMessage, toastApiError } from '@/lib/apiError'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { useBootstrapQuery } from '@/hooks/useBootstrapQuery'
import { translateLicenseStatus } from '@/lib/enumLabels'
import { apiFetch } from '@/lib/api'
import { applyDashboardDocumentSeo } from '@/lib/dashboard-seo'
import { formatDisplayDate } from '@/lib/date'

type LicenseRemoteResponse = {
  active: boolean
  status: string
  message?: string
  expiry?: string | null
  demo?: boolean
  warning?: string
  error_code?: string
  transport_raw?: string
}

type LicenseSnap = {
  active?: boolean
  demo?: boolean
  status?: string
  message?: string
  expiry?: string | null
  domain?: string
}

type DiagnosticProbe = {
  name: string
  url: string
  method: string
  latency_ms: number
  ok: boolean
  http_code: number
  error: string
  body_snippet: string
  fastpath_detected: boolean
}

type DiagnosticsResponse = {
  site_domain: string
  crm_base: string
  same_server_detected?: boolean
  local_bypass_base?: string | null
  crm_host?: string
  wp_remote_transport: string
  probes: DiagnosticProbe[]
}

function badgeForLicense(
  t: (k: string) => string,
  lic: LicenseSnap,
): { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' } {
  if (lic.active || lic.demo) {
    return { label: lic.demo ? t('license.badgeDemo') : t('license.badgeActive'), variant: 'default' }
  }
  const st = (lic.status || '').toLowerCase()
  if (st === 'error') {
    return { label: t('license.badgeError'), variant: 'destructive' }
  }
  return { label: t('license.badgePending'), variant: 'secondary' }
}

export default function LicensePage() {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()
  const bq = useBootstrapQuery()
  const canManage = Boolean(bq.data?.capabilities?.includes('manage_options'))
  const [diagReport, setDiagReport] = useState<DiagnosticsResponse | null>(null)

  const lic = useMemo(
    (): LicenseSnap | undefined => bq.data?.license ?? window.webinoDashboard.license,
    [bq.data?.license],
  )

  const badge = lic ? badgeForLicense(t, lic) : null

  useEffect(() => {
    const site = window.webinoDashboard.siteName?.trim() || t('app.title')
    const icon = window.webinoDashboard.siteIconUrl?.trim()
    applyDashboardDocumentSeo({
      title: `${t('license.title')} — ${site}`,
      description: t('license.description'),
      canonicalUrl: `${window.location.origin}${window.location.pathname}${window.location.search}`,
      ogImageUrl: icon || undefined,
      structuredSite: { name: site, url: window.webinoDashboard.homeUrl || `${window.location.origin}/` },
    })
  }, [t])

  const check = useMutation({
    mutationFn: () =>
      apiFetch<LicenseRemoteResponse>('license/remote-check', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
      }),
    onSuccess: (r) => {
      void qc.invalidateQueries({ queryKey: ['bootstrap'] })
      if (r.warning === 'crm_unreachable' && (r.active || r.demo)) {
        toast.message(licenseCrmUnreachableMessage(t))
        return
      }
      if (r.active || r.demo) {
        toast.success(t('license.checkOk'))
        return
      }
      const desc = licenseStatusMessage(t, r.message, r.error_code)
      if (!r.active && r.error_code === 'timeout') {
        toast.message(t('license.unreachableHint'), { description: desc })
        return
      }
      toast.message(t('license.statusTitle'), { description: desc })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const activate = useMutation({
    mutationFn: () =>
      apiFetch<LicenseRemoteResponse>('license/remote-activate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
      }),
    onSuccess: (r) => {
      void qc.invalidateQueries({ queryKey: ['bootstrap'] })
      if (r.active || r.demo) {
        toast.success(t('license.activated'))
        window.location.assign(`${window.webinoDashboard.baseUrl.replace(/\/$/, '')}/`)
        return
      }
      if (r.warning === 'crm_unreachable' && (r.active || r.demo)) {
        toast.message(licenseCrmUnreachableMessage(t))
        return
      }
      toast.message(t('license.statusTitle'), {
        description: licenseStatusMessage(t, r.message, r.error_code),
      })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const diagnostics = useMutation({
    mutationFn: () =>
      apiFetch<DiagnosticsResponse>('license/diagnostics', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
      }),
    onSuccess: (r) => setDiagReport(r),
    onError: (e: Error) => toastApiError(t, e),
  })

  const domain = lic?.domain ?? window.webinoDashboard.license?.domain ?? ''
  const baseDash = window.webinoDashboard.baseUrl.replace(/\/$/, '')
  const actionsBusy = check.isPending || activate.isPending || diagnostics.isPending

  if (bq.isError) {
    return (
      <div className="bg-muted flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
        <Card className="border-destructive/40 w-full max-w-lg shadow-md">
          <CardHeader>
            <CardTitle className="text-lg">{t('license.bootstrapErrorTitle')}</CardTitle>
            <CardDescription>{t('license.bootstrapErrorBody')}</CardDescription>
          </CardHeader>
          <CardFooter className="flex flex-wrap gap-2">
            <Button type="button" variant="secondary" onClick={() => void bq.refetch()}>
              <RefreshCw className="size-4 shrink-0" aria-hidden />
              {t('license.retry')}
            </Button>
            <Button type="button" variant="outline" asChild>
              <a href={baseDash}>{t('license.goDashboard')}</a>
            </Button>
          </CardFooter>
        </Card>
      </div>
    )
  }

  return (
    <div className="from-background via-muted/40 to-muted relative flex min-h-svh flex-col items-center justify-center bg-gradient-to-b p-6 md:p-10">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary/8 via-transparent to-transparent" aria-hidden />

      <div className="relative w-full max-w-xl space-y-6">
        <header className="text-center md:text-start">
          <h1 className="text-foreground text-2xl font-semibold tracking-tight md:text-3xl">{t('license.title')}</h1>
          <p className="text-muted-foreground mt-2 max-w-prose text-pretty text-sm leading-relaxed md:text-base">
            {t('license.heroSubtitle')}
          </p>
        </header>

        <Card className="overflow-hidden border shadow-lg">
          <div className="from-primary/[0.07] relative border-b bg-gradient-to-br to-transparent px-6 pb-5 pt-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
              <div className="bg-primary/12 text-primary ring-primary/15 flex size-14 shrink-0 items-center justify-center rounded-2xl ring-1">
                <ShieldCheck className="size-7" aria-hidden />
              </div>
              <div className="min-w-0 flex-1 space-y-3">
                <div>
                  <p className="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                    {t('license.cardEyebrow')}
                  </p>
                  <CardTitle className="mt-1 text-xl sm:text-2xl">{t('license.cardTitle')}</CardTitle>
                  <CardDescription className="text-pretty mt-2 text-base leading-relaxed">
                    {t('license.description')}
                  </CardDescription>
                </div>
                {bq.isLoading ? (
                  <div className="flex flex-wrap items-center gap-2 pt-1">
                    <Skeleton className="h-6 w-28 rounded-full" />
                    <Skeleton className="h-5 w-40" />
                  </div>
                ) : lic ? (
                  <div className="flex flex-wrap items-center gap-2 pt-1">
                    <span className="text-muted-foreground text-sm font-medium">{t('license.statusHeading')}</span>
                    {badge ? (
                      <Badge variant={badge.variant} className="font-medium">
                        {badge.label}
                      </Badge>
                    ) : null}
                    {lic.status && !lic.active && !lic.demo ? (
                      <code className="text-muted-foreground bg-background/80 rounded-md border px-2 py-0.5 text-xs">
                        {translateLicenseStatus(t, lic.status)}
                      </code>
                    ) : null}
                  </div>
                ) : null}
              </div>
            </div>
          </div>

          <CardContent className="space-y-5 pt-6">
            {bq.isLoading ? (
              <div className="space-y-3">
                <Skeleton className="h-16 w-full rounded-lg" />
                <Skeleton className="h-12 w-full rounded-lg" />
                <p className="text-muted-foreground text-center text-xs">{t('license.loadingHint')}</p>
              </div>
            ) : (
              <>
                <section aria-labelledby="license-details-heading">
                  <h2 id="license-details-heading" className="sr-only">
                    {t('license.detailsHeading')}
                  </h2>
                  <dl className="grid gap-3 sm:grid-cols-2">
                    <div className="bg-muted/50 rounded-xl border p-4">
                      <dt className="text-muted-foreground text-xs font-medium">{t('license.fieldDomain')}</dt>
                      <dd className="mt-1 font-mono text-sm break-all">{domain || '—'}</dd>
                    </div>
                    <div className="bg-muted/50 rounded-xl border p-4">
                      <dt className="text-muted-foreground text-xs font-medium">{t('license.fieldExpiry')}</dt>
                      <dd className="mt-1 text-sm">
                        {lic?.expiry ? formatDisplayDate(lic.expiry, i18n.language) : '—'}
                      </dd>
                    </div>
                  </dl>
                </section>

                {lic?.message ? (
                  <div
                    role="status"
                    className="border-border bg-card text-card-foreground rounded-xl border p-4 text-sm leading-relaxed shadow-sm"
                  >
                    <p className="text-muted-foreground text-xs font-medium">{t('license.serverMessage')}</p>
                    <p className="mt-2 break-words font-mono text-xs sm:text-sm">{lic.message}</p>
                  </div>
                ) : null}

                <Separator />

                <section className="bg-muted/30 rounded-xl border p-4" aria-labelledby="license-help-heading">
                  <h3 id="license-help-heading" className="text-sm font-semibold">
                    {t('license.helpTitle')}
                  </h3>
                  <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{t('license.helpBody')}</p>
                </section>
              </>
            )}
          </CardContent>

          <CardFooter className="bg-muted/35 flex flex-col gap-4 border-t px-6 py-6">
            <div className="flex w-full flex-col gap-2 sm:flex-row">
              <Button
                type="button"
                variant="secondary"
                disabled={actionsBusy || bq.isLoading}
                onClick={() => void check.mutateAsync()}
                className="gap-2 sm:flex-1"
              >
                <RefreshCw className={`size-4 shrink-0 ${check.isPending ? 'animate-spin' : ''}`} aria-hidden />
                {t('license.check')}
              </Button>
              {bq.isSuccess && canManage ? (
                <Button
                  type="button"
                  disabled={actionsBusy || bq.isLoading}
                  onClick={() => void activate.mutateAsync()}
                  className="gap-2 sm:flex-1"
                >
                  <KeyRound className="size-4 shrink-0" aria-hidden />
                  {t('license.activate')}
                </Button>
              ) : null}
            </div>

            {bq.isSuccess && canManage ? (
              <Button
                type="button"
                variant="outline"
                disabled={actionsBusy || bq.isLoading}
                onClick={() => void diagnostics.mutateAsync()}
                className="gap-2 w-full"
              >
                <Stethoscope className={`size-4 shrink-0 ${diagnostics.isPending ? 'animate-pulse' : ''}`} aria-hidden />
                {t('license.diagnostics.button')}
              </Button>
            ) : null}

            <div className="flex w-full flex-col gap-2 sm:flex-row">
              <Button variant="outline" className="gap-2 sm:flex-1" asChild>
                <Link to="/">
                  <LayoutDashboard className="size-4 shrink-0" aria-hidden />
                  {t('license.goDashboard')}
                  <ArrowRight className="ms-auto size-4 shrink-0 opacity-70 rtl:rotate-180" aria-hidden />
                </Link>
              </Button>
            </div>

            {bq.isSuccess && !canManage ? (
              <p className="text-muted-foreground text-center text-sm leading-relaxed sm:text-start">{t('license.adminOnly')}</p>
            ) : (
              <p className="text-muted-foreground text-center text-sm leading-relaxed sm:text-start">{t('license.footerHint')}</p>
            )}
          </CardFooter>
        </Card>

        {diagReport ? (
          <Card className="border shadow-md">
            <CardHeader>
              <CardTitle className="text-lg">{t('license.diagnostics.title')}</CardTitle>
              <CardDescription className="font-mono text-xs break-all">
                {diagReport.site_domain} → {diagReport.crm_base} ({t('license.diagnostics.transport')}:{' '}
                {diagReport.wp_remote_transport})
                {diagReport.same_server_detected && diagReport.local_bypass_base ? (
                  <span className="mt-1 block text-foreground">
                    {t('license.diagnostics.sameServer')} {diagReport.local_bypass_base} (Host:{' '}
                    {diagReport.crm_host ?? 'webina.dev'})
                  </span>
                ) : null}
              </CardDescription>
            </CardHeader>
            <CardContent className="select-text space-y-4 text-xs font-mono">
              {diagReport.probes.map((p) => (
                <div key={p.name} className="bg-muted/40 rounded-lg border p-3 space-y-1">
                  <p className="font-semibold text-sm">
                    {t('license.diagnostics.probe')}: {p.name} ({p.method}) — {p.latency_ms}ms — HTTP {p.http_code}
                  </p>
                  <p className="break-all text-muted-foreground">{p.url}</p>
                  {p.name === 'license_check' ? (
                    <p>
                      {t('license.diagnostics.fastpath')}:{' '}
                      {p.fastpath_detected
                        ? t('license.diagnostics.fastpathYes')
                        : t('license.diagnostics.fastpathNo')}
                    </p>
                  ) : null}
                  {p.error ? (
                    <p className="text-destructive">
                      {t('license.diagnostics.error')}: {p.error}
                    </p>
                  ) : null}
                  {p.body_snippet ? (
                    <pre className="bg-background mt-1 max-h-32 overflow-auto whitespace-pre-wrap rounded border p-2 text-[11px]">
                      {p.body_snippet}
                    </pre>
                  ) : null}
                </div>
              ))}
            </CardContent>
          </Card>
        ) : null}
      </div>
    </div>
  )
}
