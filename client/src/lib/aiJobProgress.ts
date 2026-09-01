export type AiJobProgress = {
  status: string
  result_summary?: string | null
}

const PHASES = ['queued', 'provider', 'seo', 'writing', 'done'] as const

export function jobPhase(job?: AiJobProgress | null): string {
  if (!job) return 'queued'
  if (job.status === 'done') return 'done'
  if (job.status === 'failed') return 'failed'
  if (job.status === 'cancelled') return 'cancelled'
  const summary = String(job.result_summary || '').toLowerCase()
  if ((PHASES as readonly string[]).includes(summary)) return summary
  if (job.status === 'running') return 'provider'
  return 'queued'
}

export function jobPhasePercent(phase: string): number {
  const map: Record<string, number> = {
    queued: 12,
    provider: 40,
    seo: 65,
    writing: 85,
    done: 100,
    failed: 100,
    cancelled: 100,
  }
  return map[phase] ?? 15
}

export function sleep(ms: number) {
  return new Promise((resolve) => window.setTimeout(resolve, ms))
}
