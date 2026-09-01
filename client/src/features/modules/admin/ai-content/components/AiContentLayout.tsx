import type { ReactNode } from 'react'

import { useLocale } from '@/hooks/use-locale'

export function AiContentLayout({ children }: { children: ReactNode }) {
  const { isRtl } = useLocale()
  return (
    <div dir={isRtl ? 'rtl' : 'ltr'} className="text-start">
      {children}
    </div>
  )
}
