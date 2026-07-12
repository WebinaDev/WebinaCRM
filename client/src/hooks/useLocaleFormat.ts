import { useTranslation } from 'react-i18next'

import { localizeDigits } from '@/lib/digits'
import { formatNumber } from '@/lib/formatNumber'

export function useLocaleFormat() {
  const { i18n } = useTranslation()
  const locale = i18n.language

  return {
    locale,
    formatNumber: (value: number) => formatNumber(value, locale),
    localizeDigits: (s: string) => localizeDigits(s, locale),
  }
}
