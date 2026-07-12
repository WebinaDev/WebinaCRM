import type { ReactNode } from 'react'
import { NavLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'

import { PageShell } from '@/components/PageShell'
import { cn } from '@/lib/utils'

export type SettingsNavItem = {
  id: string
  to: string
  labelKey: string
  /** When set, shown instead of t(labelKey) — for dynamic marketplace module titles. */
  label?: string
  end?: boolean
}

type SettingsSectionLayoutProps = {
  titleKey: string
  descriptionKey?: string
  navItems: SettingsNavItem[]
  children: ReactNode
}

export function SettingsSectionLayout({ titleKey, descriptionKey, navItems, children }: SettingsSectionLayoutProps) {
  const { t } = useTranslation()

  const linkCls = ({ isActive }: { isActive: boolean }) =>
    cn(
      'rounded-md px-3 py-2 text-sm transition-colors',
      isActive ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
    )

  return (
    <PageShell title={t(titleKey)} description={descriptionKey ? t(descriptionKey) : undefined}>
      <div className="flex flex-col gap-6 lg:flex-row">
        <nav
          className="flex shrink-0 flex-wrap gap-1 border-b border-border pb-2 lg:w-56 lg:flex-col lg:border-b-0 lg:border-e lg:pb-0 lg:pe-4"
          aria-label={t('settings.sectionNav')}
        >
          {navItems.map((item) => (
            <NavLink key={item.id} to={item.to} className={linkCls} end={item.end ?? false}>
              {item.label ?? t(item.labelKey)}
            </NavLink>
          ))}
        </nav>
        <div className="min-w-0 flex-1">{children}</div>
      </div>
    </PageShell>
  )
}
