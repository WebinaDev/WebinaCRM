import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import dayjs from 'dayjs'

import { DatePicker } from '@/components/ui/date-picker'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { useLocale } from '@/hooks/use-locale'
import { toastApiError } from '@/lib/apiError'
import { AiContentLayout } from '../components/AiContentLayout'
import {
  bulkCalendar,
  createCalendarSlot,
  deleteCalendarSlot,
  fetchCalendar,
  runCalendarDue,
} from '../lib/ai-content-api'

export default function AiCalendarPage() {
  const { t } = useTranslation()
  const { formatDate } = useLocale()
  const qc = useQueryClient()
  const [topic, setTopic] = useState('')
  const [focus, setFocus] = useState('')
  const [date, setDate] = useState(() => dayjs().format('YYYY-MM-DD'))
  const [type, setType] = useState<'blog' | 'product'>('blog')
  const [bulk, setBulk] = useState('')

  const calQ = useQuery({
    queryKey: ['ai-content', 'calendar'],
    queryFn: () => fetchCalendar(),
  })
  useQueryErrorToast(calQ)

  const invalidate = () => void qc.invalidateQueries({ queryKey: ['ai-content'] })

  const create = useMutation({
    mutationFn: () =>
      createCalendarSlot({
        slot_date: date,
        content_type: type,
        topic,
        focus_keyword: focus || topic,
      }),
    onSuccess: () => {
      toast.success(t('common.saved'))
      setTopic('')
      setFocus('')
      invalidate()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const bulkMut = useMutation({
    mutationFn: () =>
      bulkCalendar({
        topics: bulk,
        start_date: date,
        content_type: type,
        focus_keyword: focus,
      }),
    onSuccess: (res) => {
      toast.success(t('aiContent.bulkCreated', { count: res.created }))
      setBulk('')
      invalidate()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const remove = useMutation({
    mutationFn: (id: number) => deleteCalendarSlot(id),
    onSuccess: () => {
      toast.success(t('common.deleted'))
      invalidate()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const runDue = useMutation({
    mutationFn: runCalendarDue,
    onSuccess: () => {
      toast.success(t('aiContent.dueQueued'))
      invalidate()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <AiContentLayout>
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        <Button size="sm" onClick={() => void runDue.mutateAsync()} disabled={runDue.isPending}>
          {t('aiContent.runDue')}
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.addSlot')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <div className="space-y-1">
            <Label>{t('aiContent.fieldDate')}</Label>
            <DatePicker value={date} onChange={setDate} />
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.fieldType')}</Label>
            <select
              className="flex h-9 w-full rounded-md border bg-background px-3 text-sm"
              value={type}
              onChange={(e) => setType(e.target.value as 'blog' | 'product')}
            >
              <option value="blog">{t('aiContent.typeBlog')}</option>
              <option value="product">{t('aiContent.typeProduct')}</option>
            </select>
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.fieldTopic')}</Label>
            <Input value={topic} onChange={(e) => setTopic(e.target.value)} />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.fieldFocus')}</Label>
            <Input value={focus} onChange={(e) => setFocus(e.target.value)} />
          </div>
          <Button disabled={!topic.trim() || create.isPending} onClick={() => void create.mutateAsync()}>
            {t('common.save')}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.bulkTopics')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <p className="text-sm text-muted-foreground">{t('aiContent.bulkHint')}</p>
          <Textarea rows={6} value={bulk} onChange={(e) => setBulk(e.target.value)} />
          <Button disabled={!bulk.trim() || bulkMut.isPending} onClick={() => void bulkMut.mutateAsync()}>
            {t('aiContent.bulkCreate')}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.calendarList')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {(calQ.data?.items ?? []).map((slot) => (
            <div key={slot.id} className="flex flex-wrap items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
              <div>
                <div className="font-medium">
                  {formatDate(slot.slot_date)} · {slot.content_type} · {slot.status}
                </div>
                <div>
                  {slot.topic} — <span className="text-muted-foreground">{slot.focus_keyword}</span>
                </div>
              </div>
              <Button size="sm" variant="destructive" onClick={() => void remove.mutateAsync(slot.id)}>
                {t('common.delete')}
              </Button>
            </div>
          ))}
          {!calQ.data?.items?.length ? (
            <p className="text-sm text-muted-foreground">{t('aiContent.noSlots')}</p>
          ) : null}
        </CardContent>
      </Card>
    </div>
    </AiContentLayout>
  )
}
