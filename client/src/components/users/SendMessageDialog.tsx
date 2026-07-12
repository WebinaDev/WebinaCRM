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
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { apiFetch } from '@/lib/api'

export type BotConnectionInfo = {
  telegram?: { connected?: boolean; chat_id?: string }
  bale?: { connected?: boolean; chat_id?: string }
}

type SendMessageDialogProps = {
  userId: number
  open: boolean
  onOpenChange: (open: boolean) => void
  phone?: string
  bots?: BotConnectionInfo
}

export function SendMessageDialog({ userId, open, onOpenChange, phone, bots }: SendMessageDialogProps) {
  const { t } = useTranslation()
  const [channel, setChannel] = useState<'sms' | 'telegram' | 'bale'>('sms')
  const [message, setMessage] = useState('')

  const send = useMutation({
    mutationFn: () =>
      apiFetch(`users/${userId}/send-message`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel, message }),
      }),
    onSuccess: () => {
      toast.success(t('users.messageSent'))
      setMessage('')
      onOpenChange(false)
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('users.actionSendMessage')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <div className="space-y-2">
            <Label>{t('users.messageChannel')}</Label>
            <Select value={channel} onValueChange={(v) => setChannel(v as 'sms' | 'telegram' | 'bale')}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="sms" disabled={!phone}>
                  {t('users.channelSms')} {phone ? '' : `(${t('users.noPhone')})`}
                </SelectItem>
                <SelectItem value="telegram" disabled={!bots?.telegram?.connected}>
                  {t('users.channelTelegram')}
                </SelectItem>
                <SelectItem value="bale" disabled={!bots?.bale?.connected}>
                  {t('users.channelBale')}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>{t('users.messageBody')}</Label>
            <Textarea rows={4} value={message} onChange={(e) => setMessage(e.target.value)} />
          </div>
        </div>
        <DialogFooter>
          <Button type="button" disabled={!message.trim() || send.isPending} onClick={() => void send.mutateAsync()}>
            {t('users.sendMessage')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
