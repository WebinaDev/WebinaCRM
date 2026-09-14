import { useCallback, useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'

import { TableListSkeleton } from '@/components/TableListSkeleton'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import {
  type BasalamConnection,
  disconnectBasalamConnection,
  getBasalamOAuthStatus,
  listBasalamConnections,
  saveBasalamOAuthConfig,
} from '@/api/basalam-oauth'
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout'
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback'
import { useLocale } from '@/hooks/use-locale'

const DEFAULT_REDIRECT = 'https://webina.dev/api/basalam/oauth/callback'
const DEFAULT_CLIENT_ID = '2357'
const DEFAULT_SCOPES =
  'vendor.product.write vendor.product.read vendor.parcel.write vendor.parcel.read vendor.profile.read vendor.profile.write customer.profile.read customer.profile.write customer.order.read customer.order.write customer.chat.read customer.chat.write customer.wallet.read customer.wallet.write order-processing customer.identity.read'

function formatTs(value?: string) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleString()
}

export function MarketplaceBasalamSettingsPage() {
  const { t } = useLocale()
  const { layoutProps, setError, setSuccess, applyResponse } = useCrmFeedback()
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [disconnecting, setDisconnecting] = useState<string | null>(null)
  const [configured, setConfigured] = useState(false)
  const [connections, setConnections] = useState<BasalamConnection[]>([])
  const [form, setForm] = useState({
    client_id: DEFAULT_CLIENT_ID,
    client_secret: '',
    redirect_uri: DEFAULT_REDIRECT,
    scopes: DEFAULT_SCOPES,
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const [statusRes, connRes] = await Promise.all([getBasalamOAuthStatus(), listBasalamConnections()])
    if (statusRes.ok && statusRes.data) {
      setConfigured(Boolean(statusRes.data.configured))
      setForm({
        client_id: statusRes.data.client_id || DEFAULT_CLIENT_ID,
        client_secret: '',
        redirect_uri: statusRes.data.redirect_uri || DEFAULT_REDIRECT,
        scopes: statusRes.data.scopes || DEFAULT_SCOPES,
      })
    } else {
      setError(statusRes.message || t('pages.marketplace.basalam.loadError'))
    }
    if (connRes.ok && connRes.data) {
      setConnections(Array.isArray(connRes.data.connections) ? connRes.data.connections : [])
    }
    setLoading(false)
  }, [setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    setSuccess(null)
    const payload: Parameters<typeof saveBasalamOAuthConfig>[0] = {
      client_id: form.client_id.trim() || DEFAULT_CLIENT_ID,
      redirect_uri: form.redirect_uri.trim() || DEFAULT_REDIRECT,
      scopes: form.scopes.trim() || DEFAULT_SCOPES,
    }
    if (form.client_secret.trim() && form.client_secret.trim() !== '***') {
      payload.client_secret = form.client_secret.trim()
    }
    const res = await saveBasalamOAuthConfig(payload)
    setSaving(false)
    if (
      applyResponse(
        { success: res.ok, message: res.message },
        {
          successMessage: t('pages.marketplace.basalam.saved'),
          errorFallback: t('pages.marketplace.basalam.saveError'),
        },
      )
    ) {
      setForm((p) => ({ ...p, client_secret: '' }))
      void load()
    }
  }

  const handleDisconnect = async (siteUrl: string) => {
    if (!siteUrl) return
    setDisconnecting(siteUrl)
    setError(null)
    setSuccess(null)
    const res = await disconnectBasalamConnection(siteUrl)
    setDisconnecting(null)
    if (
      applyResponse(
        { success: res.ok, message: res.message },
        {
          successMessage: t('pages.marketplace.basalam.disconnected'),
          errorFallback: t('pages.marketplace.basalam.disconnectError'),
        },
      )
    ) {
      if (res.data?.connections) {
        setConnections(res.data.connections)
      } else {
        void load()
      }
    }
  }

  const statusLabel = (status?: string) => {
    if (status === 'connected') return t('pages.marketplace.basalam.statusConnected')
    if (status === 'disconnected') return t('pages.marketplace.basalam.statusDisconnected')
    return status || '—'
  }

  return (
    <CrmPageLayout
      title={t('pages.marketplace.basalam.title')}
      description={t('pages.marketplace.basalam.desc')}
      {...layoutProps}
    >
      <div className="grid gap-4">
        <Card>
          <CardHeader>
            <CardTitle>{t('pages.marketplace.basalam.oauthTitle')}</CardTitle>
            <CardDescription>{t('pages.marketplace.basalam.oauthDesc')}</CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <TableListSkeleton rows={6} columns={4} />
            ) : (
              <form onSubmit={handleSave} className="grid max-w-2xl gap-4">
                <p className="text-sm">
                  {configured
                    ? t('pages.marketplace.basalam.configured')
                    : t('pages.marketplace.basalam.notConfigured')}
                </p>
                <div className="space-y-2">
                  <Label>{t('pages.marketplace.basalam.clientId')}</Label>
                  <Input
                    value={form.client_id}
                    onChange={(e) => setForm((p) => ({ ...p, client_id: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('pages.marketplace.basalam.clientSecret')}</Label>
                  <Input
                    type="password"
                    autoComplete="new-password"
                    placeholder={configured ? '***' : ''}
                    value={form.client_secret}
                    onChange={(e) => setForm((p) => ({ ...p, client_secret: e.target.value }))}
                  />
                  <p className="text-muted-foreground text-xs">{t('pages.marketplace.basalam.secretHint')}</p>
                </div>
                <div className="space-y-2">
                  <Label>{t('pages.marketplace.basalam.redirectUri')}</Label>
                  <Input
                    value={form.redirect_uri}
                    onChange={(e) => setForm((p) => ({ ...p, redirect_uri: e.target.value }))}
                  />
                  <p className="text-muted-foreground text-xs">{t('pages.marketplace.basalam.redirectHint')}</p>
                </div>
                <div className="space-y-2">
                  <Label>{t('pages.marketplace.basalam.scopes')}</Label>
                  <Textarea
                    rows={4}
                    className="font-mono text-xs"
                    value={form.scopes}
                    onChange={(e) => setForm((p) => ({ ...p, scopes: e.target.value }))}
                  />
                </div>
                <div>
                  <Button type="submit" disabled={saving}>
                    {saving ? <Loader2 className="me-2 h-4 w-4 animate-spin" /> : null}
                    {t('pages.marketplace.basalam.save')}
                  </Button>
                </div>
              </form>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('pages.marketplace.basalam.connectionsTitle')}</CardTitle>
            <CardDescription>{t('pages.marketplace.basalam.connectionsDesc')}</CardDescription>
          </CardHeader>
          <CardContent>
            {loading ? (
              <TableListSkeleton rows={4} columns={5} />
            ) : connections.length === 0 ? (
              <p className="text-muted-foreground text-sm">{t('pages.marketplace.basalam.connectionsEmpty')}</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('pages.marketplace.basalam.colSite')}</TableHead>
                    <TableHead>{t('pages.marketplace.basalam.colVendor')}</TableHead>
                    <TableHead>{t('pages.marketplace.basalam.colStatus')}</TableHead>
                    <TableHead>{t('pages.marketplace.basalam.colLastSeen')}</TableHead>
                    <TableHead className="w-[1%] whitespace-nowrap">
                      {t('pages.marketplace.basalam.colActions')}
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {connections.map((row) => {
                    const site = row.site_url || ''
                    const busy = disconnecting === site
                    return (
                      <TableRow key={site || String(row.vendor_id)}>
                        <TableCell className="font-medium">{site || '—'}</TableCell>
                        <TableCell>{row.vendor_id ? String(row.vendor_id) : '—'}</TableCell>
                        <TableCell>{statusLabel(row.status)}</TableCell>
                        <TableCell className="text-muted-foreground text-sm">
                          {formatTs(row.last_seen_at)}
                        </TableCell>
                        <TableCell>
                          {row.status === 'connected' ? (
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              disabled={busy || !site}
                              onClick={() => void handleDisconnect(site)}
                            >
                              {busy ? <Loader2 className="me-2 h-3.5 w-3.5 animate-spin" /> : null}
                              {t('pages.marketplace.basalam.disconnect')}
                            </Button>
                          ) : (
                            <span className="text-muted-foreground text-xs">—</span>
                          )}
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </CrmPageLayout>
  )
}
