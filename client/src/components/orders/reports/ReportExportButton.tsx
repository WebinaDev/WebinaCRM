import { Download } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/components/ui/button'
import { buildOrderReportsExportQuery } from '@/hooks/useOrderReports'
import type { OrderReportFilters } from '@/types/orderReports'

type ReportExportButtonProps = {
  filters: OrderReportFilters
}

export function ReportExportButton({ filters }: ReportExportButtonProps) {
  const { t } = useTranslation()

  async function handleExport() {
    const c = window.webinoDashboard
    const path = buildOrderReportsExportQuery(filters)
    const url = path.startsWith('http') ? path : c.restUrl + path.replace(/^\//, '')
    const headers: Record<string, string> = {}
    if (c.nonce) headers['X-WP-Nonce'] = c.nonce
    const res = await fetch(url, { credentials: 'same-origin', headers })
    if (!res.ok) throw new Error(res.statusText)
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `order-reports-${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
  }

  return (
    <Button type="button" variant="outline" size="sm" onClick={() => void handleExport()}>
      <Download className="me-1.5 size-4" />
      {t('reports.exportCsv')}
    </Button>
  )
}
