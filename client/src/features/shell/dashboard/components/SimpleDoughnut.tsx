import { useLocale } from "@/hooks/use-locale"
import { chartColorAt } from "@/lib/chart-colors"

type Props = {
  values: number[]
  labels: string[]
}

export function SimpleDoughnut({ values, labels }: Props) {
  const { formatNumber } = useLocale()
  const total = values.reduce((a, b) => a + b, 0) || 1
  const stops = values.reduce<{ pct: number; color: string }[]>((acc, v, i) => {
    const from = acc.length ? acc[acc.length - 1].pct : 0
    acc.push({ pct: from + (v / total) * 100, color: chartColorAt(i) })
    return acc
  }, [])
  const gradient = stops
    .map((s, i) => {
      const from = i === 0 ? 0 : stops[i - 1].pct
      return `${s.color} ${from}% ${s.pct}%`
    })
    .join(", ")
  return (
    <div className="flex h-[200px] flex-col items-center justify-center gap-2">
      <div
        className="h-32 w-32 rounded-full"
        style={{ background: `conic-gradient(from 0deg, ${gradient})` }}
      />
      <div className="flex flex-wrap justify-center gap-2 text-xs">
        {labels.map((l, i) => (
          <span key={i} className="flex items-center gap-1">
            <span
              className="h-2 w-2 rounded-full"
              style={{ background: chartColorAt(i) }}
            />
            {l}: {formatNumber(values[i])}
          </span>
        ))}
      </div>
    </div>
  )
}
