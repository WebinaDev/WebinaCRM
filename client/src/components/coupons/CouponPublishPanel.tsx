import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { translatePostStatus } from '@/lib/enumLabels'

export type CouponVisibility = 'public' | 'private' | 'password'

type CouponPublishPanelProps = {
  status: string
  onStatusChange: (status: string) => void
  visibility: CouponVisibility
  onVisibilityChange: (visibility: CouponVisibility) => void
  password: string
  onPasswordChange: (password: string) => void
  publishImmediately: boolean
  onPublishImmediatelyChange: (value: boolean) => void
  publishDate: string
  onPublishDateChange: (date: string) => void
  onSave: () => void
  isSaving: boolean
}

function MetaRow({
  label,
  value,
  onEdit,
}: {
  label: string
  value: string
  onEdit?: () => void
}) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-wrap items-baseline gap-x-1 gap-y-0.5 text-sm">
      <span className="font-medium text-foreground">{label}:</span>
      <span className="text-muted-foreground">{value}</span>
      {onEdit ? (
        <Button type="button" variant="link" size="sm" className="h-auto px-1 py-0 text-xs" onClick={onEdit}>
          {t('common.edit')}
        </Button>
      ) : null}
    </div>
  )
}

export function CouponPublishPanel({
  status,
  onStatusChange,
  visibility,
  onVisibilityChange,
  password,
  onPasswordChange,
  publishImmediately,
  onPublishImmediatelyChange,
  publishDate,
  onPublishDateChange,
  onSave,
  isSaving,
}: CouponPublishPanelProps) {
  const { t } = useTranslation()
  const [statusOpen, setStatusOpen] = useState(false)
  const [visibilityOpen, setVisibilityOpen] = useState(false)
  const [dateOpen, setDateOpen] = useState(false)

  const [draftStatus, setDraftStatus] = useState(status)
  const [draftVisibility, setDraftVisibility] = useState(visibility)
  const [draftPassword, setDraftPassword] = useState(password)
  const [draftImmediate, setDraftImmediate] = useState(publishImmediately)
  const [draftDate, setDraftDate] = useState(publishDate)

  const visibilityLabel =
    visibility === 'private'
      ? t('posts.visibilityPrivate')
      : visibility === 'password'
        ? t('posts.visibilityPassword')
        : t('posts.visibilityPublic')

  const publishLabel = publishImmediately
    ? t('posts.publishImmediately')
    : dayjs(publishDate).format('YYYY/MM/DD HH:mm')

  const saveLabel =
    status === 'publish' || status === 'future' ? t('posts.publishButton') : t('posts.saveDraftButton')

  function openStatusDialog() {
    setDraftStatus(status)
    setStatusOpen(true)
  }

  function openVisibilityDialog() {
    setDraftVisibility(visibility)
    setDraftPassword(password)
    setVisibilityOpen(true)
  }

  function openDateDialog() {
    setDraftImmediate(publishImmediately)
    setDraftDate(publishDate)
    setDateOpen(true)
  }

  return (
    <>
      <Card className="gap-4 py-4 shadow-sm">
        <CardHeader className="px-4 pb-0">
          <CardTitle className="text-sm font-semibold">{t('coupons.publishPanel')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3 px-4">
          <MetaRow
            label={t('posts.statusLabel')}
            value={translatePostStatus(t, status)}
            onEdit={openStatusDialog}
          />
          <MetaRow label={t('posts.visibilityLabel')} value={visibilityLabel} onEdit={openVisibilityDialog} />
          <MetaRow label={t('posts.publishDateLabel')} value={publishLabel} onEdit={openDateDialog} />
        </CardContent>
        <CardFooter className="border-t px-4 pt-4">
          <Button type="button" className="w-full" disabled={isSaving} onClick={onSave}>
            {saveLabel}
          </Button>
        </CardFooter>
      </Card>

      <Dialog open={statusOpen} onOpenChange={setStatusOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('posts.statusLabel')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="coupon-status">{t('posts.fieldStatus')}</Label>
            <Select value={draftStatus} onValueChange={setDraftStatus}>
              <SelectTrigger id="coupon-status" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="draft">{translatePostStatus(t, 'draft')}</SelectItem>
                <SelectItem value="pending">{translatePostStatus(t, 'pending')}</SelectItem>
                <SelectItem value="publish">{translatePostStatus(t, 'publish')}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setStatusOpen(false)}>
              {t('posts.dialogClose')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                onStatusChange(draftStatus)
                setStatusOpen(false)
              }}
            >
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={visibilityOpen} onOpenChange={setVisibilityOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('posts.visibilityLabel')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label htmlFor="coupon-visibility">{t('posts.visibilityLabel')}</Label>
              <Select value={draftVisibility} onValueChange={(v) => setDraftVisibility(v as CouponVisibility)}>
                <SelectTrigger id="coupon-visibility" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="public">{t('posts.visibilityPublic')}</SelectItem>
                  <SelectItem value="private">{t('posts.visibilityPrivate')}</SelectItem>
                  <SelectItem value="password">{t('posts.visibilityPassword')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            {draftVisibility === 'password' ? (
              <div className="space-y-2">
                <Label htmlFor="coupon-password">{t('posts.passwordLabel')}</Label>
                <Input
                  id="coupon-password"
                  type="password"
                  value={draftPassword}
                  onChange={(e) => setDraftPassword(e.target.value)}
                  placeholder={t('posts.passwordPlaceholder')}
                />
              </div>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setVisibilityOpen(false)}>
              {t('posts.dialogClose')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                onVisibilityChange(draftVisibility)
                onPasswordChange(draftPassword)
                setVisibilityOpen(false)
              }}
            >
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={dateOpen} onOpenChange={setDateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('posts.publishDateLabel')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="flex items-center justify-between gap-3">
              <Label htmlFor="coupon-publish-immediate">{t('posts.publishImmediately')}</Label>
              <Switch
                id="coupon-publish-immediate"
                checked={draftImmediate}
                onCheckedChange={setDraftImmediate}
              />
            </div>
            {!draftImmediate ? (
              <div className="space-y-2">
                <Label htmlFor="coupon-publish-date">{t('posts.publishScheduled')}</Label>
                <Input
                  id="coupon-publish-date"
                  type="datetime-local"
                  value={draftDate}
                  onChange={(e) => setDraftDate(e.target.value)}
                />
              </div>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDateOpen(false)}>
              {t('posts.dialogClose')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                onPublishImmediatelyChange(draftImmediate)
                if (!draftImmediate) onPublishDateChange(draftDate)
                setDateOpen(false)
              }}
            >
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
