import { useTranslation } from 'react-i18next'

import { OrderSidebarPanel } from '@/components/orders/OrderSidebarPanel'
import {
  formatAttributionSource,
  translateAttributionDevice,
} from '@/lib/enumLabels'

export type OrderAttribution = {
  source?: string
  source_type?: string
  created_via?: string
  device_type?: string
  session_count?: number
  utm_source?: string
  utm_medium?: string
  utm_campaign?: string
}

type OrderAttributionPanelProps = {
  attribution?: OrderAttribution
}

export function OrderAttributionPanel({ attribution }: OrderAttributionPanelProps) {
  const { t } = useTranslation()
  const a = attribution ?? {}

  const rows = [
    {
      label: t('orders.attrSource'),
      value: formatAttributionSource(t, a),
    },
    {
      label: t('orders.attrDevice'),
      value: a.device_type ? translateAttributionDevice(t, a.device_type) : '',
    },
    {
      label: t('orders.attrSessions'),
      value: a.session_count != null ? String(a.session_count) : '',
    },
    { label: t('orders.attrUtmSource'), value: a.utm_source },
    { label: t('orders.attrUtmMedium'), value: a.utm_medium },
    { label: t('orders.attrUtmCampaign'), value: a.utm_campaign },
  ].filter((row) => row.value)

  return (
    <OrderSidebarPanel title={t('orders.panelAttribution')} defaultOpen={rows.length > 0}>
      {rows.length === 0 ? (
        <p className="text-muted-foreground text-sm">{t('common.empty')}</p>
      ) : (
        <dl className="space-y-2 text-start text-sm">
          {rows.map((row) => (
            <div key={row.label}>
              <dt className="text-muted-foreground text-xs">{row.label}</dt>
              <dd className="mt-0.5 font-medium" dir="auto">
                {row.value}
              </dd>
            </div>
          ))}
        </dl>
      )}
    </OrderSidebarPanel>
  )
}
