import { Ban, Check, ExternalLink, MessageSquare, RotateCcw, Trash2, Zap } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { CommentRow } from '@/components/comments/types'
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

type CommentRowActionsProps = {
  row: CommentRow
  statusFilter: string
  quickEditOpen: boolean
  replyOpen: boolean
  busy?: boolean
  onApprove: () => Promise<void>
  onUnapprove: () => Promise<void>
  onSpam: () => Promise<void>
  onTrash: () => Promise<void>
  onDelete: () => Promise<void>
  onQuickEditToggle: () => void
  onReplyToggle: () => void
}

export function CommentRowActions({
  row,
  statusFilter,
  quickEditOpen,
  replyOpen,
  busy,
  onApprove,
  onUnapprove,
  onSpam,
  onTrash,
  onDelete,
  onQuickEditToggle,
  onReplyToggle,
}: CommentRowActionsProps) {
  const { t } = useTranslation()
  const [deleteOpen, setDeleteOpen] = useState(false)
  const isTrashTab = statusFilter === 'trash'
  const isApproved = row.status === 'approved' || row.status === 'approve'

  async function confirmDelete() {
    await onDelete()
    setDeleteOpen(false)
  }

  return (
    <>
      <div className="flex flex-wrap items-center gap-0.5">
        {!isApproved ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type="button" size="icon" variant="ghost" className="size-8" disabled={busy} onClick={() => void onApprove()}>
                <Check className="size-4" />
                <span className="sr-only">{t('comments.approve')}</span>
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t('comments.approve')}</TooltipContent>
          </Tooltip>
        ) : null}
        {isApproved ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type="button" size="icon" variant="ghost" className="size-8" disabled={busy} onClick={() => void onUnapprove()}>
                <RotateCcw className="size-4" />
                <span className="sr-only">{t('comments.unapprove')}</span>
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t('comments.unapprove')}</TooltipContent>
          </Tooltip>
        ) : null}
        {row.status !== 'spam' ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type="button" size="icon" variant="ghost" className="size-8" disabled={busy} onClick={() => void onSpam()}>
                <Ban className="size-4" />
                <span className="sr-only">{t('comments.spam')}</span>
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t('comments.spam')}</TooltipContent>
          </Tooltip>
        ) : null}
        {!isTrashTab && row.status !== 'trash' ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type="button" size="icon" variant="ghost" className="size-8" disabled={busy} onClick={() => void onTrash()}>
                <Trash2 className="size-4" />
                <span className="sr-only">{t('comments.trash')}</span>
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t('comments.trash')}</TooltipContent>
          </Tooltip>
        ) : null}
        {row.post_url ? (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type="button" size="icon" variant="ghost" className="size-8" asChild>
                <a href={row.post_url} target="_blank" rel="noopener noreferrer">
                  <ExternalLink className="size-4" />
                  <span className="sr-only">{t('comments.viewPost')}</span>
                </a>
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t('comments.viewPost')}</TooltipContent>
          </Tooltip>
        ) : null}
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              size="icon"
              variant={quickEditOpen ? 'secondary' : 'ghost'}
              className="size-8"
              disabled={busy}
              onClick={onQuickEditToggle}
            >
              <Zap className="size-4" />
              <span className="sr-only">{t('comments.quickEdit')}</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>{t('comments.quickEdit')}</TooltipContent>
        </Tooltip>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              size="icon"
              variant={replyOpen ? 'secondary' : 'ghost'}
              className="size-8"
              disabled={busy}
              onClick={onReplyToggle}
            >
              <MessageSquare className="size-4" />
              <span className="sr-only">{t('comments.reply')}</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>{t('comments.reply')}</TooltipContent>
        </Tooltip>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              size="icon"
              variant="ghost"
              className="size-8 text-destructive hover:text-destructive"
              disabled={busy}
              onClick={() => setDeleteOpen(true)}
            >
              <Trash2 className="size-4" />
              <span className="sr-only">{t('common.delete')}</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>{t('common.delete')}</TooltipContent>
        </Tooltip>
      </div>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('comments.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('comments.deleteConfirmBody')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={() => void confirmDelete()}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
