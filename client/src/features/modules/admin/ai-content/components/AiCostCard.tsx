import { useTranslation } from 'react-i18next'

import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { AiCostEstimate, AiSettings } from '../lib/ai-content-api'
import { AiToman } from './AiToman'

const FEATURED = ['gpt-5.6-luna', 'gpt-5.6-terra', 'gpt-5.6-sol']

type Props = {
  estimate: AiCostEstimate | undefined
  draft: Partial<AiSettings>
  onPickModel: (id: string) => void
  onUsdToToman: (n: number) => void
  onRate: (id: string, field: 'in_per_1m' | 'out_per_1m', value: number) => void
}

export function AiCostCard({ estimate, draft, onPickModel, onUsdToToman, onRate }: Props) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language
  const models = estimate?.models ?? []
  const selected = (draft.gapgpt_model || estimate?.current.model || '').toLowerCase()
  const primary = models.filter((m) => FEATURED.includes(m.id) || m.id === selected || m.selected)
  const extra = models.filter((m) => !primary.some((p) => p.id === m.id))
  const product = estimate?.entities.product
  const brand = estimate?.entities.product_brand
  const cat = estimate?.entities.product_cat
  const blog = estimate?.entities.blog

  return (
    <div className="space-y-4">
      <p className="text-muted-foreground text-sm">{t('aiContent.costLead')}</p>
      {!estimate ? (
        <p className="text-muted-foreground text-sm">{t('common.loading')}</p>
      ) : null}
      {product ? (
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          <CostChip
            locale={locale}
            label={t('aiContent.settingsProduct')}
            mid={product.cost_toman.mid}
            hi={product.cost_toman.hi}
            source={product.source}
          />
          <CostChip
            locale={locale}
            label={t('aiContent.settingsBrand')}
            mid={brand?.cost_toman.mid ?? 0}
            hi={brand?.cost_toman.hi ?? 0}
            source={brand?.source ?? ''}
          />
          <CostChip
            locale={locale}
            label={t('aiContent.settingsProductCat')}
            mid={cat?.cost_toman.mid ?? 0}
            hi={cat?.cost_toman.hi ?? 0}
            source={cat?.source ?? ''}
          />
          <CostChip
            locale={locale}
            label={t('aiContent.settingsBlog')}
            mid={blog?.cost_toman.mid ?? 0}
            hi={blog?.cost_toman.hi ?? 0}
            source={blog?.source ?? ''}
          />
        </div>
      ) : null}

      <div className="overflow-x-auto rounded-lg border">
        <table className="w-full min-w-[720px] text-sm">
          <thead className="bg-muted/40 text-muted-foreground text-xs">
            <tr>
              <th className="px-3 py-2 text-start font-medium">{t('aiContent.costModel')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('aiContent.costIn')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('aiContent.costOut')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('aiContent.settingsProduct')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('aiContent.settingsBrand')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('aiContent.settingsProductCat')}</th>
              <th className="px-3 py-2 text-start font-medium">{t('aiContent.settingsBlog')}</th>
            </tr>
          </thead>
          <tbody>
            {primary.map((m) => (
              <ModelRow key={m.id} model={m} selected={m.id === selected} locale={locale} onPick={onPickModel} />
            ))}
          </tbody>
        </table>
      </div>
      {extra.length ? (
        <details className="rounded-lg border p-3">
          <summary className="cursor-pointer text-sm font-medium">{t('aiContent.costMoreModels')}</summary>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm">
              <tbody>
                {extra.slice(0, 20).map((m) => (
                  <ModelRow key={m.id} model={m} selected={m.id === selected} locale={locale} onPick={onPickModel} />
                ))}
              </tbody>
            </table>
          </div>
        </details>
      ) : null}

      <p className="text-muted-foreground text-xs">
        {t('aiContent.costDisclaimer', { date: estimate?.rates_updated_at ?? '' })}{' '}
        <a className="underline-offset-2 hover:underline" href={estimate?.pricing_url} target="_blank" rel="noreferrer">
          {t('aiContent.costPricingLink')}
        </a>
      </p>

      <details className="rounded-lg border p-3">
        <summary className="cursor-pointer text-sm font-medium">{t('aiContent.costEditRates')}</summary>
        <div className="mt-3 grid gap-3 md:grid-cols-2">
          <div className="space-y-1">
            <Label>{t('aiContent.costUsdToToman')}</Label>
            <Input
              type="number"
              min={1000}
              value={draft.usd_to_toman ?? estimate?.usd_to_toman ?? 100000}
              onChange={(e) => onUsdToToman(Number(e.target.value))}
            />
            <p className="text-muted-foreground text-xs">{t('aiContent.costUsdToTomanHint')}</p>
          </div>
        </div>
        <div className="mt-3 space-y-2">
          {primary.slice(0, 6).map((m) => {
            const ov = draft.gapgpt_rates?.[m.id]
            return (
              <div key={m.id} className="grid grid-cols-[1fr_auto_auto] items-center gap-2 text-sm">
                <span className="truncate font-medium">{m.id}</span>
                <Input
                  className="w-28"
                  type="number"
                  min={0}
                  value={ov?.in_per_1m ?? Math.round(m.in_per_1m)}
                  onChange={(e) => onRate(m.id, 'in_per_1m', Number(e.target.value))}
                  aria-label={t('aiContent.costIn')}
                />
                <Input
                  className="w-28"
                  type="number"
                  min={0}
                  value={ov?.out_per_1m ?? Math.round(m.out_per_1m)}
                  onChange={(e) => onRate(m.id, 'out_per_1m', Number(e.target.value))}
                  aria-label={t('aiContent.costOut')}
                />
              </div>
            )
          })}
        </div>
      </details>
    </div>
  )
}

function CostChip({
  label,
  mid,
  hi,
  locale,
  source,
}: {
  label: string
  mid: number
  hi: number
  locale: string
  source: string
}) {
  const { t } = useTranslation()
  return (
    <div className="rounded-lg border p-3">
      <div className="text-muted-foreground text-xs">{label}</div>
      <div className="mt-1 text-lg font-semibold">
        <AiToman amount={mid} locale={locale} />
      </div>
      <div className="text-muted-foreground text-xs">
        {t('aiContent.costUpTo')} <AiToman amount={hi} locale={locale} />
        {source === 'jobs' ? ` · ${t('aiContent.costFromJobs')}` : ''}
      </div>
    </div>
  )
}

function ModelRow({
  model,
  selected,
  locale,
  onPick,
}: {
  model: AiCostEstimate['models'][number]
  selected: boolean
  locale: string
  onPick: (id: string) => void
}) {
  return (
    <tr
      className={`cursor-pointer border-t hover:bg-muted/50 ${selected ? 'bg-primary/10' : ''}`}
      onClick={() => onPick(model.id)}
    >
      <td className="px-3 py-2 font-medium">
        {model.id}
        {selected ? ' ✓' : ''}
      </td>
      <td className="px-3 py-2">
        <AiToman amount={model.in_per_1m} locale={locale} />
      </td>
      <td className="px-3 py-2">
        <AiToman amount={model.out_per_1m} locale={locale} />
      </td>
      <td className="px-3 py-2">
        <AiToman amount={model.costs.product ?? 0} locale={locale} />
      </td>
      <td className="px-3 py-2">
        <AiToman amount={model.costs.product_brand ?? 0} locale={locale} />
      </td>
      <td className="px-3 py-2">
        <AiToman amount={model.costs.product_cat ?? 0} locale={locale} />
      </td>
      <td className="px-3 py-2">
        <AiToman amount={model.costs.blog ?? 0} locale={locale} />
      </td>
    </tr>
  )
}
