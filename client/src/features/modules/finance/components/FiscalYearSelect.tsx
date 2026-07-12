import { useEffect, useState } from "react"
import { accountingFiscalYears, type FiscalYear } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { SELECT_ALL_VALUE } from "@/lib/constants"

type Props = {
  value: number
  onChange: (id: number) => void
  onReady?: (years: FiscalYear[], activeId: number) => void
  allowAll?: boolean
  className?: string
}

export function FiscalYearSelect({ value, onChange, onReady, allowAll, className }: Props) {
  const { t } = useLocale()
  const [years, setYears] = useState<FiscalYear[]>([])

  useEffect(() => {
    let cancelled = false
    accountingFiscalYears()
      .then((res) => {
        if (cancelled) return
        if (!res.success || !res.data?.items) {
          onReady?.([], 0)
          return
        }
        const items = res.data.items
        setYears(items)
        const active = items.find((y) => y.is_active === 1)
        const resolvedId = active?.id ?? (items[0]?.id ?? 0)
        if (value <= 0 && resolvedId > 0) {
          onChange(resolvedId)
        }
        onReady?.(items, resolvedId)
      })
      .catch(() => {
        if (!cancelled) onReady?.([], 0)
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- init active year once
  }, [])

  return (
    <Select
      value={value > 0 ? String(value) : allowAll ? SELECT_ALL_VALUE : ""}
      onValueChange={(v) => onChange(v === SELECT_ALL_VALUE ? 0 : Number(v))}
    >
      <SelectTrigger className={className ?? "w-[180px]"}>
        <SelectValue placeholder={t("pages.accounting.accountingReports.سال_مالی")} />
      </SelectTrigger>
      <SelectContent>
        {allowAll ? (
          <SelectItem value={SELECT_ALL_VALUE}>
            {t("pages.accounting.invoices.همه_سالهای_مالی")}
          </SelectItem>
        ) : null}
        {years.map((y) => (
          <SelectItem key={y.id} value={String(y.id)}>
            {y.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
