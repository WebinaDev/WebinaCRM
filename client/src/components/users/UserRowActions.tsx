import {
  Eye,
  KeyRound,
  MessageSquare,
  Pencil,
  Trash2,
  UserCog,
  UserRound,
} from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { ChangeRoleDialog } from '@/components/users/ChangeRoleDialog'
import { ResetPasswordDialog } from '@/components/users/ResetPasswordDialog'
import { SendMessageDialog } from '@/components/users/SendMessageDialog'
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
import type { UserListRow } from '@/components/users/UsersTable'

type UserRowActionsProps = {
  row: UserListRow
  canDelete?: boolean
  canEdit?: boolean
  canPromote?: boolean
  onDeleted?: () => void
  onDelete: (id: number) => Promise<void>
  isDeleting?: boolean
}

export function UserRowActions({
  row,
  canDelete,
  canEdit,
  canPromote,
  onDeleted,
  onDelete,
  isDeleting,
}: UserRowActionsProps) {
  const { t } = useTranslation()
  const [trashOpen, setTrashOpen] = useState(false)
  const [resetOpen, setResetOpen] = useState(false)
  const [roleOpen, setRoleOpen] = useState(false)
  const [msgOpen, setMsgOpen] = useState(false)

  async function confirmDelete() {
    await onDelete(row.id)
    setTrashOpen(false)
    onDeleted?.()
  }

  return (
    <>
      <div className="flex flex-wrap items-center gap-0.5">
        <Button type="button" size="icon" variant="ghost" className="size-8" asChild>
          <Link to={`/users/${row.id}`}>
            <Eye className="size-4" />
            <span className="sr-only">{t('common.view')}</span>
          </Link>
        </Button>
        {canEdit ? (
          <Button type="button" size="icon" variant="ghost" className="size-8" asChild>
            <Link to={`/users/${row.id}`}>
              <Pencil className="size-4" />
              <span className="sr-only">{t('common.edit')}</span>
            </Link>
          </Button>
        ) : null}
        {canDelete ? (
          <Button
            type="button"
            size="icon"
            variant="ghost"
            className="size-8 text-destructive hover:text-destructive"
            disabled={isDeleting}
            onClick={() => setTrashOpen(true)}
          >
            <Trash2 className="size-4" />
            <span className="sr-only">{t('common.delete')}</span>
          </Button>
        ) : null}
        {canEdit ? (
          <Button type="button" size="icon" variant="ghost" className="size-8" onClick={() => setResetOpen(true)}>
            <KeyRound className="size-4" />
            <span className="sr-only">{t('users.actionResetPassword')}</span>
          </Button>
        ) : null}
        {canPromote ? (
          <Button type="button" size="icon" variant="ghost" className="size-8" onClick={() => setRoleOpen(true)}>
            <UserCog className="size-4" />
            <span className="sr-only">{t('users.actionChangeRole')}</span>
          </Button>
        ) : null}
        {canEdit ? (
          <Button type="button" size="icon" variant="ghost" className="size-8" onClick={() => setMsgOpen(true)}>
            <MessageSquare className="size-4" />
            <span className="sr-only">{t('users.actionSendMessage')}</span>
          </Button>
        ) : null}
        <Tooltip>
          <TooltipTrigger asChild>
            <span>
              <Button type="button" size="icon" variant="ghost" className="size-8" disabled>
                <UserRound className="size-4 opacity-50" />
                <span className="sr-only">{t('users.actionSwitchUserSoon')}</span>
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent>{t('users.actionSwitchUserSoon')}</TooltipContent>
        </Tooltip>
      </div>

      <AlertDialog open={trashOpen} onOpenChange={setTrashOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('users.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('users.deleteConfirmBody', { login: row.login })}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>{t('common.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {resetOpen ? (
        <ResetPasswordDialog userId={row.id} open={resetOpen} onOpenChange={setResetOpen} />
      ) : null}
      {roleOpen ? (
        <ChangeRoleDialog userId={row.id} currentRole={row.role} open={roleOpen} onOpenChange={setRoleOpen} />
      ) : null}
      {msgOpen ? (
        <SendMessageDialog userId={row.id} open={msgOpen} onOpenChange={setMsgOpen} bots={undefined} phone={row.phone} />
      ) : null}
    </>
  )
}
