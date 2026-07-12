import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { formatNumber } from '@/lib/formatNumber'

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { apiFetch } from '@/lib/api'
import { translateOrderStatus } from '@/lib/enumLabels'

type StatusOption = { slug: string; label: string }

type OrdersBulkActionsProps = {
  selectedIds: number[]
  statuses: StatusOption[]
  onDone: () => void
}

const EMAIL_ACTIONS = [
  { value: 'customer_invoice', labelKey: 'orders.bulkEmailInvoice' },
  { value: 'customer_processing_order', labelKey: 'orders.bulkEmailProcessing' },
  { value: 'customer_completed_order', labelKey: 'orders.bulkEmailCompleted' },
  { value: 'customer_on_hold_order', labelKey: 'orders.bulkEmailOnHold' },
] as const

export function OrdersBulkActions({ selectedIds, statuses, onDone }: OrdersBulkActionsProps) {
  const { t, i18n } = useTranslation()
  const [action, setAction] = useState('change_status')
  const [status, setStatus] = useState('processing')
  const [emailType, setEmailType] = useState('customer_invoice')
  const [confirmOpen, setConfirmOpen] = useState(false)

  const bulk = useMutation({
    mutationFn: (body: Record<string, unknown>) =>
      apiFetch<{ ok: number; failed: number }>('shop/orders/bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      }),
    onSuccess: (res) => {
      toast.success(t('orders.bulkDone', { ok: res.ok, failed: res.failed }))
      onDone()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  function needsConfirm() {
    return action === 'trash' || action === 'delete'
  }

  function runBulk() {
    const body: Record<string, unknown> = { action, ids: selectedIds }
    if (action === 'change_status') body.status = status
    if (action === 'send_email') body.email_type = emailType
    void bulk.mutateAsync(body)
    setConfirmOpen(false)
  }

  function handleApply() {
    if (selectedIds.length === 0) return
    if (needsConfirm()) {
      setConfirmOpen(true)
      return
    }
    runBulk()
  }

  return (
    <>
      <div className="flex flex-wrap items-center gap-2">
        <Select value={action} onValueChange={setAction}>
          <SelectTrigger className="h-9 w-[11rem]">
            <SelectValue placeholder={t('orders.bulkActions')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="change_status">{t('orders.bulkChangeStatus')}</SelectItem>
            <SelectItem value="send_email">{t('orders.bulkSendEmail')}</SelectItem>
            <SelectItem value="trash">{t('orders.bulkTrash')}</SelectItem>
            <SelectItem value="delete">{t('orders.bulkDelete')}</SelectItem>
          </SelectContent>
        </Select>
        {action === 'change_status' ? (
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger className="h-9 w-[10rem]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {statuses.map((s) => (
                <SelectItem key={s.slug} value={s.slug}>
                  {translateOrderStatus(t, s.slug) !== s.slug ? translateOrderStatus(t, s.slug) : s.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : null}
        {action === 'send_email' ? (
          <Select value={emailType} onValueChange={setEmailType}>
            <SelectTrigger className="h-9 w-[12rem]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {EMAIL_ACTIONS.map((e) => (
                <SelectItem key={e.value} value={e.value}>
                  {t(e.labelKey)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : null}
        <Button type="button" size="sm" disabled={selectedIds.length === 0 || bulk.isPending} onClick={handleApply}>
          {t('orders.bulkApply')}
        </Button>
      </div>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('orders.bulkConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('orders.bulkConfirmBody', {
                count: formatNumber(selectedIds.length, i18n.language),
                action: t(`orders.bulk_${action}`),
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={runBulk}>
              {t('orders.bulkApply')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
