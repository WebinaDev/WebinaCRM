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
import { apiFetch } from '@/lib/api'

const ROLES = [
  { value: 'subscriber', key: 'users.roleSubscriber' },
  { value: 'customer', key: 'users.roleCustomer' },
  { value: 'author', key: 'users.roleAuthor' },
  { value: 'editor', key: 'users.roleEditor' },
  { value: 'shop_manager', key: 'users.roleShopManager' },
] as const

type ChangeRoleDialogProps = {
  userId: number
  currentRole?: string
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ChangeRoleDialog({ userId, currentRole, open, onOpenChange }: ChangeRoleDialogProps) {
  const { t } = useTranslation()
  const [role, setRole] = useState(currentRole || 'subscriber')

  const save = useMutation({
    mutationFn: () =>
      apiFetch(`users/${userId}/change-role`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ role }),
      }),
    onSuccess: () => {
      toast.success(t('common.saved'))
      onOpenChange(false)
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('users.actionChangeRole')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-2">
          <Label>{t('users.fieldRole')}</Label>
          <Select value={role} onValueChange={setRole}>
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {ROLES.map((r) => (
                <SelectItem key={r.value} value={r.value}>
                  {t(r.key)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <DialogFooter>
          <Button type="button" disabled={save.isPending} onClick={() => void save.mutateAsync()}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
