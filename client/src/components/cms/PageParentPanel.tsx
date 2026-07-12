import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { apiFetch } from '@/lib/api'

type PageParentPanelProps = {
  parent: number
  excludeId?: number
  onChange: (parent: number) => void
}

type PageOption = { id: number; title: string }

export function PageParentPanel({ parent, excludeId, onChange }: PageParentPanelProps) {
  const { t } = useTranslation()

  const pagesQ = useQuery({
    queryKey: ['pages', 'parent-options'],
    queryFn: () => apiFetch<{ items: PageOption[] }>('content/pages?per_page=100'),
  })
  useQueryErrorToast(pagesQ)

  const options = (pagesQ.data?.items ?? []).filter((p) => p.id !== excludeId)

  return (
    <Card className="gap-4 py-4 shadow-sm">
      <CardHeader className="px-4 pb-0">
        <CardTitle className="text-sm font-semibold">{t('pages.panelParent')}</CardTitle>
      </CardHeader>
      <CardContent className="px-4">
        {pagesQ.isLoading ? (
          <Skeleton className="h-9 w-full" />
        ) : (
          <div className="space-y-2">
            <Label htmlFor="page-parent">{t('pages.panelParent')}</Label>
            <Select value={String(parent)} onValueChange={(v) => onChange(parseInt(v, 10) || 0)}>
              <SelectTrigger id="page-parent" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="0">{t('pages.parentNone')}</SelectItem>
                {options.map((p) => (
                  <SelectItem key={p.id} value={String(p.id)}>
                    {p.title}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
