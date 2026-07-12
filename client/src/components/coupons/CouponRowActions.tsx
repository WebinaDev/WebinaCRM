import { Pencil, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

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

export type CouponListRow = {
  id: number
  code: string
  trash_url?: string
}

type CouponRowActionsProps = {
  row: CouponListRow
  onTrashed?: () => void
  onTrash: (id: number) => Promise<void>
  isTrashing?: boolean
}

export function CouponRowActions({ row, onTrashed, onTrash, isTrashing }: CouponRowActionsProps) {
  const { t } = useTranslation()
  const [trashOpen, setTrashOpen] = useState(false)

  async function confirmTrash() {
    await onTrash(row.id)
    setTrashOpen(false)
    onTrashed?.()
  }

  return (
    <>
      <div className="flex flex-wrap items-center gap-0.5">
        <Button type="button" size="icon" variant="ghost" className="size-8" asChild>
          <Link to={`/marketing/coupons/${row.id}`}>
            <Pencil className="size-4" />
            <span className="sr-only">{t('coupons.actionEdit')}</span>
          </Link>
        </Button>
        <Button
          type="button"
          size="icon"
          variant="ghost"
          className="size-8 text-destructive hover:text-destructive"
          disabled={isTrashing}
          onClick={() => setTrashOpen(true)}
        >
          <Trash2 className="size-4" />
          <span className="sr-only">{t('coupons.actionTrash')}</span>
        </Button>
      </div>

      <AlertDialog open={trashOpen} onOpenChange={setTrashOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('coupons.trashConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('coupons.trashConfirmBody', { code: row.code })}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={isTrashing} onClick={() => void confirmTrash()}>
              {t('coupons.actionTrash')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
