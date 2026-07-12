import { useMutation } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { OrderSidebarPanel } from '@/components/orders/OrderSidebarPanel'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiFetch } from '@/lib/api'

type OrderTrackingPanelProps = {
  orderId: number
  trackingCode?: string
  trackingUrl?: string
  trackingProvider?: string
  deliveryDate?: string
  deliveryTime?: string
  onSaved: () => void
}

export function OrderTrackingPanel({
  orderId,
  trackingCode = '',
  trackingUrl = '',
  trackingProvider = '',
  deliveryDate = '',
  deliveryTime = '',
  onSaved,
}: OrderTrackingPanelProps) {
  const { t } = useTranslation()
  const [code, setCode] = useState(trackingCode)
  const [provider, setProvider] = useState(trackingProvider)

  useEffect(() => {
    setCode(trackingCode)
    setProvider(trackingProvider)
  }, [trackingCode, trackingProvider])

  const save = useMutation({
    mutationFn: () =>
      apiFetch(`orders/${orderId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tracking_code: code, tracking_provider: provider }),
      }),
    onSuccess: () => {
      toast.success(t('common.saved'))
      onSaved()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <OrderSidebarPanel title={t('orders.panelTracking')}>
      <div className="space-y-3">
        <div className="space-y-2">
          <Label htmlFor="tracking-code">{t('orders.trackingCode')}</Label>
          <Input id="tracking-code" value={code} onChange={(e) => setCode(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label htmlFor="tracking-provider">{t('orders.trackingProvider')}</Label>
          <Input id="tracking-provider" value={provider} onChange={(e) => setProvider(e.target.value)} />
        </div>
        {trackingUrl ? (
          <p className="text-sm">
            <a href={trackingUrl} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
              {t('orders.trackingLink')}
            </a>
          </p>
        ) : null}
        {deliveryDate || deliveryTime ? (
          <p className="text-muted-foreground text-xs">
            {t('orders.deliverySlot')}: {[deliveryDate, deliveryTime].filter(Boolean).join(' · ')}
          </p>
        ) : null}
        <Button type="button" size="sm" disabled={save.isPending} onClick={() => void save.mutateAsync()}>
          {t('common.save')}
        </Button>
      </div>
    </OrderSidebarPanel>
  )
}
