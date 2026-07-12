import { useTranslation } from 'react-i18next'

import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type CouponUsagePanelProps = {
  usageLimit: string
  onUsageLimitChange: (value: string) => void
  usageLimitPerUser: string
  onUsageLimitPerUserChange: (value: string) => void
}

export function CouponUsagePanel({
  usageLimit,
  onUsageLimitChange,
  usageLimitPerUser,
  onUsageLimitPerUserChange,
}: CouponUsagePanelProps) {
  const { t } = useTranslation()

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <Label>{t('coupons.fieldUsageLimit')}</Label>
        <Input value={usageLimit} onChange={(e) => onUsageLimitChange(e.target.value)} inputMode="numeric" />
      </div>
      <div className="space-y-2">
        <Label>{t('coupons.usageLimitPerUser')}</Label>
        <Input
          value={usageLimitPerUser}
          onChange={(e) => onUsageLimitPerUserChange(e.target.value)}
          inputMode="numeric"
        />
      </div>
    </div>
  )
}
