import type { ComponentProps } from 'react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { apiFetch } from '@/lib/api'
import { toastApiError } from '@/lib/apiError'

export function LoginForm({
  className,
  ...props
}: ComponentProps<'div'>) {
  const { t } = useTranslation()
  const homeUrl = window.webinoDashboard.homeUrl || '/'
  const siteName = window.webinoDashboard.siteName?.trim() || t('app.title')
  const iconUrl = window.webinoDashboard.siteIconUrl?.trim()

  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(true)
  const [pending, setPending] = useState(false)

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setPending(true)
    try {
      const res = await apiFetch<{ success?: boolean; data?: { redirect_url?: string } }>(
        'auth/login/password',
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            username: identifier.trim(),
            password,
            remember: remember ? 1 : 0,
          }),
        },
      )
      window.location.assign(res.data?.redirect_url || window.webinoDashboard.baseUrl)
    } catch (err) {
      toastApiError(t, err)
    } finally {
      setPending(false)
    }
  }

  return (
    <div className={cn('flex flex-col gap-6', className)} {...props}>
      <Card className="overflow-hidden shadow-sm">
        <CardContent className="grid p-0 md:grid-cols-2">
          <form className="flex flex-col gap-6 p-6 md:p-8" onSubmit={(e) => void onSubmit(e)}>
            <div className="flex flex-col items-center gap-2 text-center">
              <h1 className="text-2xl font-bold">{t('login.title')}</h1>
              <p className="text-balance text-muted-foreground text-sm">{t('login.subtitle')}</p>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="dashboard-login-id">{t('login.identifier')}</Label>
              <Input
                id="dashboard-login-id"
                name="login"
                type="text"
                autoComplete="username"
                value={identifier}
                onChange={(e) => setIdentifier(e.target.value)}
                required
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="dashboard-login-password">{t('login.password')}</Label>
              <Input
                id="dashboard-login-password"
                name="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <div className="flex items-center gap-2">
              <Checkbox
                id="dashboard-login-remember"
                checked={remember}
                onCheckedChange={(v) => setRemember(v === true)}
              />
              <Label htmlFor="dashboard-login-remember" className="cursor-pointer font-normal">
                {t('login.remember')}
              </Label>
            </div>
            <Button type="submit" className="w-full" disabled={pending}>
              {pending ? t('login.pending') : t('login.submit')}
            </Button>
            <Button variant="outline" className="w-full" asChild type="button">
              <a href={homeUrl}>{t('login.backHome')}</a>
            </Button>
          </form>
          <div className="relative hidden flex-col justify-between gap-4 bg-muted p-8 md:flex">
            <div>
              <p className="text-muted-foreground text-sm leading-relaxed">{t('login.heroHint')}</p>
              <p className="mt-4 font-semibold">{siteName}</p>
            </div>
            {iconUrl ? (
              <img src={iconUrl} alt="" className="mx-auto size-24 rounded-lg object-cover shadow-sm" />
            ) : (
              <div className="mx-auto flex size-24 items-center justify-center rounded-lg bg-background/80 text-2xl font-semibold shadow-sm">
                {siteName.slice(0, 1).toUpperCase()}
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
