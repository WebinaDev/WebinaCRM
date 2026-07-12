import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

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
import { formatNumber } from '@/lib/formatNumber'
import { apiFetch } from '@/lib/api'

type CouponsBulkActionsProps = {
  selectedIds: number[]
  onDone: () => void
}

export function CouponsBulkActions({ selectedIds, onDone }: CouponsBulkActionsProps) {
  const { t, i18n } = useTranslation()
  const [confirmOpen, setConfirmOpen] = useState(false)

  const bulk = useMutation({
    mutationFn: () =>
      apiFetch<{ ok: number; failed: number }>('marketing/coupons/bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'trash', ids: selectedIds }),
      }),
    onSuccess: (res) => {
      toast.success(
        t('coupons.bulkDone', {
          ok: formatNumber(res.ok, i18n.language),
          failed: formatNumber(res.failed, i18n.language),
        }),
      )
      onDone()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  function runBulk() {
    void bulk.mutateAsync()
    setConfirmOpen(false)
  }

  return (
    <>
      <Button
        type="button"
        size="sm"
        variant="outline"
        disabled={selectedIds.length === 0 || bulk.isPending}
        onClick={() => setConfirmOpen(true)}
      >
        {t('coupons.bulkTrash')}
      </Button>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('coupons.bulkConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('coupons.bulkConfirmBody', { count: formatNumber(selectedIds.length, i18n.language) })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={bulk.isPending} onClick={runBulk}>
              {t('coupons.bulkTrash')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
