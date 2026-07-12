export type AnalyticsSectionId = 'overview' | 'visitors' | 'pages' | 'referrals' | 'geo' | 'devices'

export const ANALYTICS_SECTIONS: AnalyticsSectionId[] = [
  'overview',
  'visitors',
  'pages',
  'referrals',
  'geo',
  'devices',
]

export function isAnalyticsSection(s: string | undefined): s is AnalyticsSectionId {
  return !!s && (ANALYTICS_SECTIONS as readonly string[]).includes(s)
}

export function analyticsPeriodQuery(days: number) {
  const to = Math.floor(Date.now() / 1000)
  const from = to - days * 86400
  return `from=${from}&to=${to}`
}
