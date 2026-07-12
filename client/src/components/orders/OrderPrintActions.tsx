import { Printer } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/components/ui/button'
import { openOrderPrint } from '@/lib/orderPrint'

type OrderPrintActionsProps = {
  orderId: number
}

export function OrderPrintActions({ orderId }: OrderPrintActionsProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-wrap gap-2">
      <Button type="button" variant="outline" size="sm" onClick={() => openOrderPrint(orderId, 'invoice')}>
        <Printer className="size-4" aria-hidden />
        {t('orders.printInvoice')}
      </Button>
      <Button type="button" variant="outline" size="sm" onClick={() => openOrderPrint(orderId, 'label')}>
        <Printer className="size-4" aria-hidden />
        {t('orders.printLabel')}
      </Button>
      <Button type="button" variant="outline" size="sm" onClick={() => openOrderPrint(orderId, 'receipt')}>
        <Printer className="size-4" aria-hidden />
        {t('orders.printReceipt')}
      </Button>
    </div>
  )
}
