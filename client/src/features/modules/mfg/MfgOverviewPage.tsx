import { useTranslation } from 'react-i18next'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

export function MfgOverviewPage() {
  const { t } = useTranslation()
  return (
    <div className="mx-auto max-w-2xl p-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('nav.module.mfg')}</CardTitle>
          <CardDescription>{t('nav.erp.mfg.placeholder')}</CardDescription>
        </CardHeader>
        <CardContent className="text-muted-foreground text-sm leading-relaxed">
          {t('nav.erp.mfg.placeholderBody')}
        </CardContent>
      </Card>
    </div>
  )
}
