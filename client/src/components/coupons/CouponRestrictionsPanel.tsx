import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'

import { ProductMultiSelect } from '@/components/coupons/ProductMultiSelect'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { apiFetch } from '@/lib/api'

type Term = { id: number; name: string }

type CouponRestrictionsPanelProps = {
  productIds: number[]
  onProductIdsChange: (ids: number[]) => void
  excludedProductIds: number[]
  onExcludedProductIdsChange: (ids: number[]) => void
  categoryIds: number[]
  onCategoryIdsChange: (ids: number[]) => void
  excludedCategoryIds: number[]
  onExcludedCategoryIdsChange: (ids: number[]) => void
  brandIds: number[]
  onBrandIdsChange: (ids: number[]) => void
  excludedBrandIds: number[]
  onExcludedBrandIdsChange: (ids: number[]) => void
  emailsText: string
  onEmailsTextChange: (value: string) => void
}

function toggleTax(ids: number[], tid: number, checked: boolean) {
  if (checked) return ids.includes(tid) ? ids : [...ids, tid]
  return ids.filter((x) => x !== tid)
}

function TermCheckboxList({
  label,
  items,
  selected,
  onChange,
  loading,
  emptyKey,
}: {
  label: string
  items: Term[]
  selected: number[]
  onChange: (ids: number[]) => void
  loading: boolean
  emptyKey: string
}) {
  const { t } = useTranslation()

  return (
    <div className="space-y-2">
      <Label>{label}</Label>
      <div className="max-h-40 overflow-y-auto rounded-md border border-border p-2 text-sm">
        {loading ? (
          <p className="text-muted-foreground">{t('common.loading')}</p>
        ) : items.length === 0 ? (
          <p className="text-muted-foreground">{t(emptyKey)}</p>
        ) : (
          items.map((c) => (
            <label key={c.id} className="flex cursor-pointer items-center gap-2 py-1">
              <Checkbox
                checked={selected.includes(c.id)}
                onCheckedChange={(v) => onChange(toggleTax(selected, c.id, v === true))}
              />
              <span>{c.name}</span>
            </label>
          ))
        )}
      </div>
    </div>
  )
}

export function CouponRestrictionsPanel({
  productIds,
  onProductIdsChange,
  excludedProductIds,
  onExcludedProductIdsChange,
  categoryIds,
  onCategoryIdsChange,
  excludedCategoryIds,
  onExcludedCategoryIdsChange,
  brandIds,
  onBrandIdsChange,
  excludedBrandIds,
  onExcludedBrandIdsChange,
  emailsText,
  onEmailsTextChange,
}: CouponRestrictionsPanelProps) {
  const { t } = useTranslation()

  const catsQ = useQuery({
    queryKey: ['product-categories'],
    queryFn: () => apiFetch<{ items: Term[] }>('shop/product-categories'),
  })

  const brandsQ = useQuery({
    queryKey: ['brands'],
    queryFn: () => apiFetch<{ items: Term[] }>('shop/brands'),
  })

  const cats = catsQ.data?.items ?? []
  const brands = brandsQ.data?.items ?? []

  return (
    <div className="space-y-6">
      <ProductMultiSelect
        id="coupon-products-include"
        label={t('coupons.productsInclude')}
        value={productIds}
        onChange={onProductIdsChange}
      />
      <ProductMultiSelect
        id="coupon-products-exclude"
        label={t('coupons.productsExclude')}
        value={excludedProductIds}
        onChange={onExcludedProductIdsChange}
      />

      <div className="grid gap-4 md:grid-cols-2">
        <TermCheckboxList
          label={t('coupons.categoriesInclude')}
          items={cats}
          selected={categoryIds}
          onChange={onCategoryIdsChange}
          loading={catsQ.isLoading}
          emptyKey="products.noCategories"
        />
        <TermCheckboxList
          label={t('coupons.categoriesExclude')}
          items={cats}
          selected={excludedCategoryIds}
          onChange={onExcludedCategoryIdsChange}
          loading={catsQ.isLoading}
          emptyKey="products.noCategories"
        />
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <TermCheckboxList
          label={t('coupons.brandsInclude')}
          items={brands}
          selected={brandIds}
          onChange={onBrandIdsChange}
          loading={brandsQ.isLoading}
          emptyKey="products.noBrands"
        />
        <TermCheckboxList
          label={t('coupons.brandsExclude')}
          items={brands}
          selected={excludedBrandIds}
          onChange={onExcludedBrandIdsChange}
          loading={brandsQ.isLoading}
          emptyKey="products.noBrands"
        />
      </div>

      <div className="space-y-2">
        <Label htmlFor="coupon-emails">{t('coupons.allowedEmails')}</Label>
        <Textarea
          id="coupon-emails"
          value={emailsText}
          onChange={(e) => onEmailsTextChange(e.target.value)}
          rows={3}
          placeholder={t('coupons.allowedEmailsHint')}
        />
      </div>
    </div>
  )
}
