import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import type { CommentRow } from '@/components/comments/types'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { TableCell, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { apiFetch } from '@/lib/api'

type CommentReplyRowProps = {
  row: CommentRow
  colSpan: number
  onSaved: () => void
  onCancel: () => void
}

export function CommentReplyRow({ row, colSpan, onSaved, onCancel }: CommentReplyRowProps) {
  const { t } = useTranslation()
  const [content, setContent] = useState('')

  const send = useMutation({
    mutationFn: () =>
      apiFetch('comments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          post_id: row.post_id,
          parent_id: row.id,
          content,
        }),
      }),
    onSuccess: () => {
      toast.success(t('comments.replySent'))
      setContent('')
      onSaved()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <TableRow className="bg-muted/30">
      <TableCell colSpan={colSpan} className="p-4">
        <p className="text-muted-foreground mb-3 text-sm">
          {t('comments.replyTo')}: <span className="text-foreground font-medium">{row.author}</span>
        </p>
        <p className="text-muted-foreground mb-3 line-clamp-2 text-sm">{row.excerpt}</p>
        <div className="space-y-2">
          <Label htmlFor={`comment-reply-${row.id}`}>{t('comments.reply')}</Label>
          <Textarea id={`comment-reply-${row.id}`} rows={4} value={content} onChange={(e) => setContent(e.target.value)} />
        </div>
        <div className="mt-4 flex gap-2">
          <Button type="button" size="sm" disabled={!content.trim() || send.isPending} onClick={() => void send.mutateAsync()}>
            {t('comments.replySend')}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={onCancel}>
            {t('comments.quickEditCancel')}
          </Button>
        </div>
      </TableCell>
    </TableRow>
  )
}
