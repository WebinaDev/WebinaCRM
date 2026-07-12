import { useMutation } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { TableCell, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { apiFetch } from '@/lib/api'

type CommentQuickEditRowProps = {
  commentId: number
  initialAuthor: string
  initialEmail: string
  initialContent: string
  colSpan: number
  onSaved: () => void
  onCancel: () => void
}

export function CommentQuickEditRow({
  commentId,
  initialAuthor,
  initialEmail,
  initialContent,
  colSpan,
  onSaved,
  onCancel,
}: CommentQuickEditRowProps) {
  const { t } = useTranslation()
  const [author, setAuthor] = useState(initialAuthor)
  const [email, setEmail] = useState(initialEmail)
  const [content, setContent] = useState(initialContent)

  useEffect(() => {
    setAuthor(initialAuthor)
    setEmail(initialEmail)
    setContent(initialContent)
  }, [initialAuthor, initialEmail, initialContent])

  const save = useMutation({
    mutationFn: () =>
      apiFetch(`comments/${commentId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ author, author_email: email, content }),
      }),
    onSuccess: () => {
      toast.success(t('common.saved'))
      onSaved()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <TableRow className="bg-muted/30">
      <TableCell colSpan={colSpan} className="p-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor={`comment-author-${commentId}`}>{t('comments.colAuthor')}</Label>
            <Input id={`comment-author-${commentId}`} value={author} onChange={(e) => setAuthor(e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label htmlFor={`comment-email-${commentId}`}>{t('comments.colEmail')}</Label>
            <Input id={`comment-email-${commentId}`} type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
          </div>
        </div>
        <div className="mt-4 space-y-2">
          <Label htmlFor={`comment-content-${commentId}`}>{t('comments.colExcerpt')}</Label>
          <Textarea id={`comment-content-${commentId}`} rows={4} value={content} onChange={(e) => setContent(e.target.value)} />
        </div>
        <div className="mt-4 flex gap-2">
          <Button type="button" size="sm" disabled={save.isPending} onClick={() => void save.mutateAsync()}>
            {t('comments.quickEditSave')}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={onCancel}>
            {t('comments.quickEditCancel')}
          </Button>
        </div>
      </TableCell>
    </TableRow>
  )
}
