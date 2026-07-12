import { useMutation } from '@tanstack/react-query'
import { Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { OrderSidebarPanel } from '@/components/orders/OrderSidebarPanel'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { apiFetch } from '@/lib/api'
import { formatDisplayDateTime } from '@/lib/date'
import { translateNoteAddedBy } from '@/lib/enumLabels'

export type OrderNote = {
  id: number
  content: string
  date: string | null
  customer_note: boolean
  added_by: string
}

type OrderNotesPanelProps = {
  orderId: number
  notes: OrderNote[]
  locale: string
  onChanged: () => void
}

export function OrderNotesPanel({ orderId, notes, locale, onChanged }: OrderNotesPanelProps) {
  const { t } = useTranslation()
  const [content, setContent] = useState('')
  const [customerNote, setCustomerNote] = useState(false)

  const addNote = useMutation({
    mutationFn: () =>
      apiFetch(`orders/${orderId}/notes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content, customer_note: customerNote }),
      }),
    onSuccess: () => {
      setContent('')
      setCustomerNote(false)
      toast.success(t('orders.noteAdded'))
      onChanged()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const deleteNote = useMutation({
    mutationFn: (noteId: number) =>
      apiFetch(`orders/${orderId}/notes/${noteId}`, { method: 'DELETE' }),
    onSuccess: () => {
      toast.success(t('common.deleted'))
      onChanged()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <OrderSidebarPanel title={t('orders.panelNotes')}>
      <ul className="mb-4 max-h-64 space-y-3 overflow-y-auto">
        {notes.length === 0 ? (
          <li className="text-muted-foreground text-sm">{t('orders.noNotes')}</li>
        ) : (
          notes.map((note) => (
            <li key={note.id} className="border-b pb-3 last:border-0">
              <div className="flex items-start justify-between gap-2">
                <p className="text-sm whitespace-pre-wrap">{note.content}</p>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  disabled={deleteNote.isPending}
                  onClick={() => void deleteNote.mutateAsync(note.id)}
                  title={t('common.delete')}
                >
                  <Trash2 className="size-3.5" aria-hidden />
                </Button>
              </div>
              <p className="text-muted-foreground mt-1 text-xs">
                {formatDisplayDateTime(note.date ?? undefined, locale)}
                {note.added_by ? ` · ${translateNoteAddedBy(t, note.added_by)}` : ''}
                {note.customer_note ? ` · ${t('orders.noteToCustomer')}` : ''}
              </p>
            </li>
          ))
        )}
      </ul>
      <div className="space-y-3">
        <Textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          placeholder={t('orders.notePlaceholder')}
          rows={3}
        />
        <div className="flex items-center gap-2">
          <Checkbox
            id="customer-note"
            checked={customerNote}
            onCheckedChange={(v) => setCustomerNote(v === true)}
          />
          <Label htmlFor="customer-note" className="text-sm font-normal">
            {t('orders.noteToCustomer')}
          </Label>
        </div>
        <Button
          type="button"
          size="sm"
          disabled={!content.trim() || addNote.isPending}
          onClick={() => void addNote.mutateAsync()}
        >
          {t('orders.addNote')}
        </Button>
      </div>
    </OrderSidebarPanel>
  )
}
