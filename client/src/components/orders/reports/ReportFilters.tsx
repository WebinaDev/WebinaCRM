import { useTranslation } from 'react-i18next'

import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { ReportInterval, ReportPreset } from '@/types/orderReports'

const PRESETS: ReportPreset[] = [
  'today',
  'yesterday',
  'last7',
  'last30',
  'thisWeek',
  'lastWeek',
  'thisMonth',
  'lastMonth',
  'thisYear',
  'custom',
]

const STATUS_OPTIONS = ['completed', 'processing', 'on-hold', 'pending', 'cancelled', 'refunded', 'failed']

type ReportFiltersProps = {
  preset: ReportPreset
  from: Date
  to: Date
  interval: ReportInterval
  compare: boolean
  statuses: string[]
  onPresetChange: (p: ReportPreset) => void
  onFromChange: (v: string) => void
  onToChange: (v: string) => void
  onIntervalChange: (v: ReportInterval) => void
  onCompareChange: (v: boolean) => void
  onStatusesChange: (v: string[]) => void
}

function toInputDate(d: Date) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function toggleStatus(list: string[], slug: string, checked: boolean) {
  if (checked) return list.includes(slug) ? list : [...list, slug]
  return list.filter((x) => x !== slug)
}

export function ReportFilters({
  preset,
  from,
  to,
  interval,
  compare,
  statuses,
  onPresetChange,
  onFromChange,
  onToChange,
  onIntervalChange,
  onCompareChange,
  onStatusesChange,
}: ReportFiltersProps) {
  const { t } = useTranslation()

  return (
    <div className="space-y-4 rounded-lg border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap gap-2">
        {PRESETS.map((p) => (
          <Button
            key={p}
            type="button"
            size="sm"
            variant={preset === p ? 'default' : 'outline'}
            onClick={() => onPresetChange(p)}
          >
            {t(`reports.preset.${p}`)}
          </Button>
        ))}
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-2">
          <Label htmlFor="report-from">{t('reports.dateFrom')}</Label>
          <Input
            id="report-from"
            type="date"
            value={toInputDate(from)}
            onChange={(e) => onFromChange(e.target.value)}
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="report-to">{t('reports.dateTo')}</Label>
          <Input
            id="report-to"
            type="date"
            value={toInputDate(to)}
            onChange={(e) => onToChange(e.target.value)}
          />
        </div>
        <div className="space-y-2">
          <Label>{t('reports.interval')}</Label>
          <Select value={interval} onValueChange={(v) => onIntervalChange(v as ReportInterval)}>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="day">{t('reports.intervalDay')}</SelectItem>
              <SelectItem value="week">{t('reports.intervalWeek')}</SelectItem>
              <SelectItem value="month">{t('reports.intervalMonth')}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="flex items-end pb-2">
          <label className="flex cursor-pointer items-center gap-2">
            <Checkbox checked={compare} onCheckedChange={(v) => onCompareChange(v === true)} />
            <span className="text-sm">{t('reports.comparePrevious')}</span>
          </label>
        </div>
      </div>

      <div className="space-y-2">
        <Label>{t('reports.statusFilter')}</Label>
        <div className="flex flex-wrap gap-3">
          {STATUS_OPTIONS.map((slug) => (
            <label key={slug} className="flex cursor-pointer items-center gap-2 text-sm">
              <Checkbox
                checked={statuses.includes(slug)}
                onCheckedChange={(v) => onStatusesChange(toggleStatus(statuses, slug, v === true))}
              />
              <span>{t(`reports.status.${slug}`, slug)}</span>
            </label>
          ))}
        </div>
      </div>
    </div>
  )
}
