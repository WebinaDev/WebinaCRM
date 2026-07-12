import { useMutation } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import type { BotConnectionInfo } from '@/components/users/SendMessageDialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { apiFetch } from '@/lib/api'

type Channel = 'email' | 'sms' | 'bale' | 'telegram' | 'whatsapp'

type SendMessageResponse = {
  ok?: boolean
  results?: Partial<Record<Exclude<Channel, 'whatsapp'>, boolean>>
}

type UserCommunicationPanelProps = {
  userId: number
  email?: string
  phone?: string
  bots?: BotConnectionInfo
}

function whatsappDigits(phone: string): string {
  let digits = phone.replace(/\D/g, '')
  if (digits.startsWith('0')) {
    digits = `98${digits.slice(1)}`
  } else if (!digits.startsWith('98')) {
    digits = `98${digits}`
  }
  return digits
}

export function UserCommunicationPanel({ userId, email, phone, bots }: UserCommunicationPanelProps) {
  const { t } = useTranslation()
  const [message, setMessage] = useState('')
  const [channels, setChannels] = useState<Record<Channel, boolean>>({
    email: Boolean(email),
    sms: Boolean(phone),
    bale: Boolean(bots?.bale?.connected),
    telegram: Boolean(bots?.telegram?.connected),
    whatsapp: Boolean(phone),
  })

  const availability = useMemo(
    () => ({
      email: Boolean(email),
      sms: Boolean(phone),
      bale: Boolean(bots?.bale?.connected),
      telegram: Boolean(bots?.telegram?.connected),
      whatsapp: Boolean(phone),
    }),
    [email, phone, bots]
  )

  const enabledChannels = (Object.keys(channels) as Channel[]).filter((ch) => channels[ch] && availability[ch])
  const serverChannels = enabledChannels.filter((ch) => ch !== 'whatsapp')

  const toggle = (channel: Channel, checked: boolean) => {
    setChannels((prev) => ({ ...prev, [channel]: checked }))
  }

  const send = useMutation({
    mutationFn: async () => {
      if (enabledChannels.length === 0) {
        throw new Error(t('users.commNoChannel'))
      }
      const trimmed = message.trim()
      if (!trimmed) {
        throw new Error(t('users.messageBody'))
      }

      let response: SendMessageResponse = { ok: true, results: {} }
      if (serverChannels.length > 0) {
        response = await apiFetch<SendMessageResponse>(`users/${userId}/send-message`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: trimmed, channels: serverChannels }),
        })
      }

      if (channels.whatsapp && phone) {
        const url = `https://wa.me/${whatsappDigits(phone)}?text=${encodeURIComponent(trimmed)}`
        window.open(url, '_blank', 'noopener,noreferrer')
      }

      return response
    },
    onSuccess: (response) => {
      const results = response.results ?? {}
      const failed = Object.entries(results).filter(([, ok]) => !ok).map(([ch]) => ch)
      if (failed.length > 0) {
        toast.warning(t('users.commPartialSuccess'))
      } else {
        toast.success(t('users.messageSent'))
      }
      setMessage('')
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const channelRows: { key: Channel; label: string }[] = [
    { key: 'email', label: t('users.commChannelEmail') },
    { key: 'sms', label: t('users.channelSms') },
    { key: 'bale', label: t('users.channelBale') },
    { key: 'telegram', label: t('users.channelTelegram') },
    { key: 'whatsapp', label: t('users.commChannelWhatsapp') },
  ]

  return (
    <Card className="shadow-sm">
      <CardContent className="space-y-3 pt-6">
        <h2 className="text-sm font-semibold">{t('users.sectionCommunication')}</h2>
        <div className="space-y-2">
          <Label>{t('users.messageBody')}</Label>
          <Textarea rows={4} value={message} onChange={(e) => setMessage(e.target.value)} />
        </div>
        <div className="space-y-2">
          {channelRows.map(({ key, label }) => (
            <div key={key} className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
              <Label htmlFor={`comm-${key}`} className="text-sm font-normal">
                {label}
              </Label>
              <Switch
                id={`comm-${key}`}
                checked={channels[key] && availability[key]}
                disabled={!availability[key]}
                onCheckedChange={(checked) => toggle(key, checked)}
              />
            </div>
          ))}
        </div>
        <Button
          type="button"
          disabled={!message.trim() || enabledChannels.length === 0 || send.isPending}
          onClick={() => void send.mutateAsync()}
        >
          {t('users.commSend')}
        </Button>
      </CardContent>
    </Card>
  )
}
