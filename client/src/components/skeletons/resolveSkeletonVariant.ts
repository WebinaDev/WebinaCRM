export type SkeletonVariant =
  | 'dashboard'
  | 'list'
  | 'reports'
  | 'settingsForm'
  | 'cardGrid'
  | 'cardList'
  | 'detail'
  | 'kanban'
  | 'chat'

/** Map dashboard pathname (without basename) to skeleton layout. */
export function resolveSkeletonVariant(pathname: string): SkeletonVariant {
  const p = pathname.replace(/\/$/, '') || '/'

  if (p === '/' || p === '') return 'dashboard'

  if (
    p.startsWith('/reports') ||
    p.startsWith('/finance/reports') ||
    p.startsWith('/accounting/reports') ||
    p.startsWith('/admin/analytics/visitors') ||
    p.startsWith('/visitor-statistics')
  ) {
    return 'reports'
  }

  if (
    p === '/finance' ||
    p === '/accounting' ||
    p.startsWith('/admin/integrations/modirpayamak') && !p.includes('/settings') && !p.includes('/send')
  ) {
    if (/\/\d+/.test(p) || p.endsWith('/customers') || p.endsWith('/orders')) {
      return 'list'
    }
    if (p.includes('/modirpayamak') && (p.endsWith('/modirpayamak') || p.endsWith('/dashboard'))) {
      return 'dashboard'
    }
    if (p === '/finance' || p === '/accounting') return 'dashboard'
  }

  if (p.startsWith('/admin/settings') || p.startsWith('/settings')) return 'settingsForm'

  if (p.startsWith('/admin/marketplace/products') || p.startsWith('/marketplace/products')) {
    return 'cardGrid'
  }

  if (p.startsWith('/admin/marketplace/categories') || p.startsWith('/marketplace/categories')) {
    return 'cardList'
  }

  if (
    p.includes('/marketplace/modules/') ||
    (p.startsWith('/pm/projects/') && p !== '/pm/projects' && !p.endsWith('/projects')) ||
    (p.startsWith('/projects/') && p !== '/projects')
  ) {
    return 'detail'
  }

  if (p.startsWith('/pm/tasks') || p.startsWith('/tasks')) return 'kanban'

  if (p.startsWith('/pm/chat') || p.startsWith('/chat')) return 'chat'

  if (p.startsWith('/docs/contracts') || p.startsWith('/contracts')) return 'detail'

  return 'list'
}
