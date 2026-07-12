import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { apiFetch } from '@/lib/api'

type ResetPasswordDialogProps = {
  userId: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ResetPasswordDialog({ userId, open, onOpenChange }: ResetPasswordDialogProps) {
  const { t } = useTranslation()
  const [generated, setGenerated] = useState('')

  const generate = useMutation({
    mutationFn: () =>
      apiFetch<{ ok: boolean; password?: string; sent_email?: boolean }>(`users/${userId}/reset-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ send_email: false }),
      }),
    onSuccess: (data) => {
      if (data.sent_email) {
        toast.success(t('users.resetEmailSent'))
      } else if (data.password) {
        setGenerated(data.password)
        toast.success(t('users.resetGenerated'))
      }
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const sendEmail = useMutation({
    mutationFn: () =>
      apiFetch(`users/${userId}/reset-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ send_email: true }),
      }),
    onSuccess: () => {
      toast.success(t('users.resetEmailSent'))
      onOpenChange(false)
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('users.actionResetPassword')}</DialogTitle>
        </DialogHeader>
        {generated ? (
          <p className="rounded-md bg-muted p-3 font-mono text-sm">{generated}</p>
        ) : null}
        <DialogFooter className="gap-2 sm:justify-start">
          <Button type="button" disabled={generate.isPending} onClick={() => void generate.mutateAsync()}>
            {t('users.resetGenerate')}
          </Button>
          <Button type="button" variant="outline" disabled={sendEmail.isPending} onClick={() => void sendEmail.mutateAsync()}>
            {t('users.resetSendEmail')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
