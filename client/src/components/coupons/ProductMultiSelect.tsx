import { useQuery } from '@tanstack/react-query'
import { X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiFetch } from '@/lib/api'

type ProductOption = { id: number; name: string }

type ProductMultiSelectProps = {
  id: string
  label: string
  value: number[]
  onChange: (ids: number[]) => void
}

export function ProductMultiSelect({ id, label, value, onChange }: ProductMultiSelectProps) {
  const { t } = useTranslation()
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [labels, setLabels] = useState<Record<number, string>>({})

  useEffect(() => {
    const timer = window.setTimeout(() => setSearch(searchInput), 300)
    return () => window.clearTimeout(timer)
  }, [searchInput])

  const q = useQuery({
    queryKey: ['products', 'search', search],
    queryFn: () =>
      apiFetch<{ items: ProductOption[] }>(
        `shop/products?page=1&per_page=20${search.trim() ? `&search=${encodeURIComponent(search.trim())}` : ''}`,
      ),
    enabled: search.trim().length >= 1,
  })

  function addProduct(p: ProductOption) {
    if (value.includes(p.id)) return
    setLabels((prev) => ({ ...prev, [p.id]: p.name }))
    onChange([...value, p.id])
    setSearchInput('')
    setSearch('')
  }

  function removeProduct(pid: number) {
    onChange(value.filter((x) => x !== pid))
  }

  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <Input
        id={id}
        value={searchInput}
        onChange={(e) => setSearchInput(e.target.value)}
        placeholder={t('coupons.productSearchPlaceholder')}
      />
      {q.isFetching && search ? (
        <p className="text-muted-foreground text-xs">{t('common.loading')}</p>
      ) : null}
      {q.data?.items && search ? (
        <div className="max-h-32 overflow-y-auto rounded-md border border-border p-1 text-sm">
          {q.data.items.length === 0 ? (
            <p className="text-muted-foreground p-2">{t('coupons.noProductsFound')}</p>
          ) : (
            q.data.items.map((p) => (
              <button
                key={p.id}
                type="button"
                className="hover:bg-muted block w-full rounded px-2 py-1.5 text-start"
                onClick={() => addProduct(p)}
              >
                {p.name} <span className="text-muted-foreground">#{p.id}</span>
              </button>
            ))
          )}
        </div>
      ) : null}
      {value.length > 0 ? (
        <div className="flex flex-wrap gap-1">
          {value.map((pid) => (
            <Badge key={pid} variant="secondary" className="gap-1 pe-1">
              {labels[pid] ? `${labels[pid]} (#${pid})` : `#${pid}`}
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className="size-5"
                onClick={() => removeProduct(pid)}
              >
                <X className="size-3" />
                <span className="sr-only">{t('common.delete')}</span>
              </Button>
            </Badge>
          ))}
        </div>
      ) : null}
    </div>
  )
}
