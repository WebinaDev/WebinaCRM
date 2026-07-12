import { ExternalLink, Maximize, Minimize } from 'lucide-react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, Outlet, useLocation } from 'react-router-dom'

import { AccentMenu } from '@/components/AccentMenu'
import { AppSidebar } from '@/components/app-sidebar'
import { LanguageMenu } from '@/components/LanguageMenu'
import { ThemeMenu } from '@/components/ThemeMenu'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { SidebarInset, SidebarProvider, SidebarTrigger, useSidebar } from '@/components/ui/sidebar'
import { useBootstrapQuery } from '@/hooks/useBootstrapQuery'
import { useAuthSession } from '@/hooks/useAuthSession'
import { normalizeCapabilities } from '@/lib/bootstrapQuery'
import { normalizeAccent } from '@/lib/accent'
import { resolveSiteHeaderTitle } from '@/lib/dashboard-header-title'
import { applyDashboardDocumentSeo } from '@/lib/dashboard-seo'
import {
  buildSidebarSections,
  flattenNavLabels,
  moduleNavTitle,
  navSectionsToSidebar08MainItems,
} from '@/lib/nav-modules'
import { moduleIcon } from '@/lib/module-icons'
import type { NavMainSection } from '@/types/nav'
import { useTheme } from '@/theme/ThemeProvider'

function fallbackNavSectionsForRole(role: string | undefined, t: ReturnType<typeof useTranslation>['t']): NavMainSection[] {
  const home: NavMainSection = {
    kind: 'item',
    id: 'home',
    to: '/',
    title: moduleNavTitle(t, 'home', t('nav.overview')),
    icon: moduleIcon('layout-dashboard'),
  }
  if (role === 'client' || role === 'customer') {
    return [
      home,
      { kind: 'item', id: 'projects', to: '/projects', title: t('nav.module.projects'), icon: moduleIcon('folder') },
      { kind: 'item', id: 'contracts', to: '/contracts', title: t('nav.module.contracts'), icon: moduleIcon('file-text') },
      { kind: 'item', id: 'invoices', to: '/invoices', title: t('nav.module.invoices'), icon: moduleIcon('file-stack') },
      { kind: 'item', id: 'tickets', to: '/tickets', title: t('nav.module.tickets'), icon: moduleIcon('headphones') },
    ]
  }
  return [home]
}

function CloseMobileSidebarOnNavigate() {
  const { pathname } = useLocation()
  const { setOpenMobile } = useSidebar()

  useEffect(() => {
    setOpenMobile(false)
  }, [pathname, setOpenMobile])

  return null
}

export function DashboardLayout() {
  const { t, i18n } = useTranslation()
  const loc = useLocation()
  const { setTheme } = useTheme()
  const bq = useBootstrapQuery()
  const authSession = useAuthSession()
  const restUnavailable = authSession.isError || bq.isError
  const applyBootstrapTheme = useCallback(
    (th?: string) => {
      if (th === 'light' || th === 'dark' || th === 'system') {
        setTheme(th)
      }
    },
    [setTheme],
  )

  useEffect(() => {
    if (bq.data?.uiTheme) applyBootstrapTheme(bq.data.uiTheme)
  }, [bq.data?.uiTheme, applyBootstrapTheme])

  useEffect(() => {
    document.documentElement.setAttribute('data-accent', normalizeAccent(bq.data?.uiAccent))
  }, [bq.data?.uiAccent])

  const { pinned, sections: moduleSections } = useMemo(() => {
    if (bq.data?.modules?.length) {
      const built = buildSidebarSections(bq.data.modules, t)
      if (built.pinned.length > 0 || built.sections.length > 0) {
        return built
      }
    }
    const fallback = fallbackNavSectionsForRole(bq.data?.user?.role, t)
    return { pinned: fallback, sections: [] as ReturnType<typeof buildSidebarSections>['sections'] }
  }, [bq.data?.modules, bq.data?.user?.role, t])

  const pinnedItems = useMemo(
    () => navSectionsToSidebar08MainItems(pinned, loc.pathname),
    [pinned, loc.pathname],
  )

  const sidebarModuleSections = useMemo(
    () =>
      moduleSections.map((section) => ({
        id: section.id,
        label: section.label,
        items: navSectionsToSidebar08MainItems(section.items, loc.pathname),
      })),
    [moduleSections, loc.pathname],
  )

  const navLabels = useMemo(() => {
    const allSections: NavMainSection[] = [...pinned, ...moduleSections.flatMap((s) => s.items)]
    return flattenNavLabels(allSections)
  }, [pinned, moduleSections])

  const headerTitle = useMemo(() => {
    return resolveSiteHeaderTitle(loc.pathname, navLabels, t)
  }, [loc.pathname, navLabels, t])

  const siteName = bq.data?.site?.name ?? t('app.title')

  useEffect(() => {
    const description = t('seo.pageDescription', { page: headerTitle, site: siteName })
    const canonicalUrl = `${window.location.origin}${window.location.pathname}${window.location.search}`
    const icon = bq.data?.site?.icon?.trim()
    applyDashboardDocumentSeo({
      title: `${headerTitle} — ${siteName}`,
      description,
      canonicalUrl,
      ogImageUrl: icon || undefined,
      structuredSite: { name: siteName, url: bq.data?.site?.url ?? window.location.origin + '/' },
    })
  }, [headerTitle, siteName, t, loc.pathname, loc.search, bq.data?.site?.icon, bq.data?.site?.url])

  const [fs, setFs] = useState(false)
  useEffect(() => {
    const h = () => setFs(Boolean(document.fullscreenElement))
    document.addEventListener('fullscreenchange', h)
    return () => document.removeEventListener('fullscreenchange', h)
  }, [])

  async function toggleFs() {
    try {
      if (!document.fullscreenElement) {
        await document.documentElement.requestFullscreen()
      } else {
        await document.exitFullscreen()
      }
    } catch {
      /* ignore */
    }
  }

  function logout() {
    const redirect = `${window.webinoDashboard.baseUrl}login`
    const url = `${window.webinoDashboard.restUrl}auth/logout`
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: {
        'X-WP-Nonce': window.webinoDashboard.nonce,
      },
    }).catch(() => {})
    window.location.assign(redirect)
  }

  const siteUrl = bq.data?.site?.url ?? '/'
  const userName = bq.data?.user?.name?.trim() || t('nav.userFallback')
  const userEmail = bq.data?.user?.email?.trim() || ''
  const userAvatar = bq.data?.user?.avatar

  const sidebarSide = useMemo(() => (i18n.dir() === 'rtl' ? 'right' : 'left'), [i18n.language])

  const canAdmin = normalizeCapabilities(bq.data?.capabilities).includes('manage_options')
  const totalNavItems = pinned.length + moduleSections.reduce((n, s) => n + s.items.length, 0)
  const singleNavHint =
    bq.isSuccess && totalNavItems === 1 && pinned.length === 1 && !canAdmin
      ? t('nav.singleNavHint')
      : undefined

  return (
    <SidebarProvider>
      <CloseMobileSidebarOnNavigate />
      <AppSidebar
        side={sidebarSide}
        brandTitle={siteName}
        brandSubtitle={t('nav.siteSubtitle')}
        brandTo="/"
        pinnedItems={pinnedItems}
        moduleSections={sidebarModuleSections}
        navLoading={bq.isPending}
        footerHint={singleNavHint}
        user={{ name: userName, email: userEmail || '—', avatar: userAvatar }}
        logoutLabel={t('nav.logout')}
        onLogout={() => void logout()}
      />
      <SidebarInset dir={i18n.dir()}>
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b bg-background/95 backdrop-blur transition-[width,height] ease-linear supports-[backdrop-filter]:bg-background/80 group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12">
          <div className="flex w-full items-center gap-2 px-4">
            <SidebarTrigger className="-ms-1" />
            <Separator orientation="vertical" className="me-2 data-[orientation=vertical]:h-4" />
            <Breadcrumb>
              <BreadcrumbList>
                <BreadcrumbItem className="hidden md:block">
                  <BreadcrumbLink asChild>
                    <Link to="/">{t('nav.overview')}</Link>
                  </BreadcrumbLink>
                </BreadcrumbItem>
                <BreadcrumbSeparator className="hidden md:block" />
                <BreadcrumbItem>
                  <BreadcrumbPage>{headerTitle}</BreadcrumbPage>
                </BreadcrumbItem>
              </BreadcrumbList>
            </Breadcrumb>
            <div className="ms-auto flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="icon"
                aria-label={t('nav.fullscreen')}
                onClick={() => void toggleFs()}
              >
                {fs ? <Minimize className="size-4" /> : <Maximize className="size-4" />}
              </Button>
              <Button variant="outline" size="icon" asChild>
                <a
                  href={siteUrl}
                  target="_blank"
                  rel="noreferrer"
                  aria-label={t('nav.visitSite')}
                >
                  <ExternalLink className="size-4" />
                </a>
              </Button>
              <LanguageMenu />
              <AccentMenu />
              <ThemeMenu />
            </div>
          </div>
        </header>
        <div className="@container/main flex min-w-0 flex-1 flex-col gap-4 p-4 pt-6 md:p-6">
          {restUnavailable ? (
            <div
              role="alert"
              className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
            >
              {t('errors.restUnavailable')}
            </div>
          ) : null}
          <Outlet />
        </div>
        <footer className="mt-auto border-t px-4 py-3 text-muted-foreground text-xs">
          <p className="text-center md:text-start">{siteName}</p>
        </footer>
      </SidebarInset>
    </SidebarProvider>
  )
}
