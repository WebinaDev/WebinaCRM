import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { Button } from '@/components/ui/button'

export default function NotFoundPage() {
  const { t } = useTranslation()
  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 py-6 text-center">
      <h1 className="text-2xl font-semibold">{t('errors.notFoundTitle')}</h1>
      <p className="max-w-md text-sm text-muted-foreground">{t('errors.notFoundBody')}</p>
      <Button asChild variant="default">
        <Link to="/">{t('errors.backHome')}</Link>
      </Button>
    </div>
  )
}
