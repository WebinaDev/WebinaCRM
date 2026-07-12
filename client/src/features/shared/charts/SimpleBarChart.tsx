type Props = {
  data: number[]
  labels: string[]
  max?: number
  className?: string
}

export function SimpleBarChart({ data, labels, max, className }: Props) {
  const values = Array.isArray(data) ? data.map((v) => Number(v) || 0) : []
  const chartLabels = Array.isArray(labels) ? labels.map((l) => String(l ?? "")) : []
  const m = max ?? Math.max(...values, 1)
  return (
    <div className={`flex h-[180px] items-end gap-1 ${className ?? ""}`}>
      {values.map((v, i) => (
        <div key={i} className="flex flex-1 flex-col items-center gap-1 min-w-0">
          <div
            className="w-full min-h-[4px] rounded-t bg-primary transition-all"
            style={{ height: `${(v / m) * 100}%` }}
          />
          <span className="text-[10px] text-muted-foreground truncate max-w-full" title={chartLabels[i]}>
            {chartLabels[i]}
          </span>
        </div>
      ))}
    </div>
  )
}
