import * as React from 'react'
import { ThemeProvider as NextThemesProvider } from 'next-themes'

import { TooltipProvider } from '@/components/ui/tooltip'

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  return (
    <NextThemesProvider attribute="class" defaultTheme="system" enableSystem disableTransitionOnChange storageKey="wd-theme">
      <TooltipProvider delayDuration={0}>{children}</TooltipProvider>
    </NextThemesProvider>
  )
}

export { useTheme } from 'next-themes'
