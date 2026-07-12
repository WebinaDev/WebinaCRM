/** Chart series colors from theme CSS variables (light/dark aware). */
export const CHART_SERIES = [
  "hsl(var(--chart-1))",
  "hsl(var(--chart-2))",
  "hsl(var(--chart-3))",
  "hsl(var(--chart-4))",
  "hsl(var(--chart-5))",
] as const

export function chartColorAt(index: number): string {
  return CHART_SERIES[index % CHART_SERIES.length]
}
