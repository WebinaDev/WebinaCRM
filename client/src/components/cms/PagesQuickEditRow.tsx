import { useMutation } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { TableCell, TableRow } from '@/components/ui/table'
import { apiFetch } from '@/lib/api'
import { translatePostStatus } from '@/lib/enumLabels'

type PageOption = { id: number; title: string }

type PagesQuickEditRowProps = {
  pageId: number
  initialTitle: string
  initialStatus: string
  initialParent: number
  pageOptions: PageOption[]
  colSpan: number
  onSaved: () => void
  onCancel: () => void
}

export function PagesQuickEditRow({
  pageId,
  initialTitle,
  initialStatus,
  initialParent,
  pageOptions,
  colSpan,
  onSaved,
  onCancel,
}: PagesQuickEditRowProps) {
  const { t } = useTranslation()
  const [title, setTitle] = useState(initialTitle)
  const [status, setStatus] = useState(initialStatus)
  const [parent, setParent] = useState(initialParent)

  useEffect(() => {
    setTitle(initialTitle)
    setStatus(initialStatus)
    setParent(initialParent)
  }, [initialTitle, initialStatus, initialParent])

  const save = useMutation({
    mutationFn: () =>
      apiFetch(`content/pages/${pageId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, status, parent }),
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
        <div className="grid gap-4 sm:grid-cols-3">
          <div className="space-y-2">
            <Label htmlFor={`quick-title-${pageId}`}>{t('pages.fieldTitle')}</Label>
            <Input id={`quick-title-${pageId}`} value={title} onChange={(e) => setTitle(e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label htmlFor={`quick-status-${pageId}`}>{t('pages.fieldStatus')}</Label>
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger id={`quick-status-${pageId}`} className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="draft">{translatePostStatus(t, 'draft')}</SelectItem>
                <SelectItem value="publish">{translatePostStatus(t, 'publish')}</SelectItem>
                <SelectItem value="pending">{translatePostStatus(t, 'pending')}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor={`quick-parent-${pageId}`}>{t('pages.panelParent')}</Label>
            <Select value={String(parent)} onValueChange={(v) => setParent(parseInt(v, 10) || 0)}>
              <SelectTrigger id={`quick-parent-${pageId}`} className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="0">{t('pages.parentNone')}</SelectItem>
                {pageOptions
                  .filter((p) => p.id !== pageId)
                  .map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.title}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <div className="mt-4 flex gap-2">
          <Button type="button" size="sm" disabled={save.isPending} onClick={() => void save.mutateAsync()}>
            {t('pages.quickEditSave')}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={onCancel}>
            {t('pages.quickEditCancel')}
          </Button>
        </div>
      </TableCell>
    </TableRow>
  )
}
