import { useTranslation } from 'react-i18next'

import type { CommentCounts, CommentStatusFilter } from '@/components/comments/types'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { formatNumber } from '@/lib/formatNumber'
import { cn } from '@/lib/utils'

type CommentStatusTabsProps = {
  value: CommentStatusFilter
  counts: CommentCounts
  locale: string
  onChange: (status: CommentStatusFilter) => void
}

const TABS: { id: CommentStatusFilter; labelKey: string }[] = [
  { id: 'all', labelKey: 'comments.tabAll' },
  { id: 'hold', labelKey: 'comments.tabPending' },
  { id: 'approve', labelKey: 'comments.tabApproved' },
  { id: 'spam', labelKey: 'comments.tabSpam' },
  { id: 'trash', labelKey: 'comments.tabTrash' },
]

export function CommentStatusTabs({ value, counts, locale, onChange }: CommentStatusTabsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-wrap gap-1 border-b border-border pb-2">
      {TABS.map((tab) => {
        const count = counts[tab.id] ?? 0
        const active = value === tab.id
        return (
          <Button
            key={tab.id}
            type="button"
            size="sm"
            variant={active ? 'secondary' : 'ghost'}
            className={cn('h-8 gap-1.5', active && 'font-medium')}
            onClick={() => onChange(tab.id)}
          >
            {t(tab.labelKey)}
            <Badge variant={active ? 'default' : 'outline'} className="min-w-[1.25rem] px-1.5">
              {formatNumber(count, locale)}
            </Badge>
          </Button>
        )
      })}
    </div>
  )
}
