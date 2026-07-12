import { ExternalLink, Layout, Pencil, Trash2, Zap } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

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

export type PageListRow = {
  id: number
  title: string
  url: string
  elementor_url: string
  trash_url: string
}

type PageRowActionsProps = {
  row: PageListRow
  quickEditOpen: boolean
  onQuickEditToggle: () => void
  onTrashed?: () => void
}

export function PageRowActions({ row, quickEditOpen, onQuickEditToggle, onTrashed }: PageRowActionsProps) {
  const { t } = useTranslation()
  const [trashOpen, setTrashOpen] = useState(false)
  const showElementor = Boolean(row.elementor_url) && Boolean(window.webinoDashboard.flags?.elementor)

  function confirmTrash() {
    window.open(row.trash_url, '_blank', 'noopener,noreferrer')
    setTrashOpen(false)
    onTrashed?.()
  }

  return (
    <>
      <div className="flex flex-wrap items-center gap-0.5">
        <Button type="button" size="icon" variant="ghost" className="size-8" asChild>
          <Link to={`/pages/${row.id}`}>
            <Pencil className="size-4" />
            <span className="sr-only">{t('pages.actionEdit')}</span>
          </Link>
        </Button>
        <Button
          type="button"
          size="icon"
          variant={quickEditOpen ? 'secondary' : 'ghost'}
          className="size-8"
          onClick={onQuickEditToggle}
        >
          <Zap className="size-4" />
          <span className="sr-only">{t('pages.actionQuickEdit')}</span>
        </Button>
        {showElementor ? (
          <Button type="button" size="icon" variant="ghost" className="size-8" asChild>
            <a href={row.elementor_url} target="_blank" rel="noopener noreferrer">
              <Layout className="size-4" />
              <span className="sr-only">{t('pages.actionElementor')}</span>
            </a>
          </Button>
        ) : null}
        <Button type="button" size="icon" variant="ghost" className="size-8 text-destructive hover:text-destructive" onClick={() => setTrashOpen(true)}>
          <Trash2 className="size-4" />
          <span className="sr-only">{t('pages.actionTrash')}</span>
        </Button>
        {row.url ? (
          <Button type="button" size="icon" variant="ghost" className="size-8" asChild>
            <a href={row.url} target="_blank" rel="noopener noreferrer">
              <ExternalLink className="size-4" />
              <span className="sr-only">{t('pages.actionView')}</span>
            </a>
          </Button>
        ) : null}
      </div>

      <AlertDialog open={trashOpen} onOpenChange={setTrashOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('pages.trashConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('pages.trashConfirmBody', { name: row.title })}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('pages.quickEditCancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={confirmTrash}>
              {t('pages.actionTrash')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
