import * as React from 'react'
import { useTheme } from '@/theme/ThemeProvider'
import { normalizeAccent, type AccentPreset } from '@/lib/accent'

interface AccentContextValue {
  accentId: AccentPreset
  setAccentId: (id: AccentPreset) => void
  hasUserPreset: boolean
  applyServerColor: (hex: string) => void
}

const AccentContext = React.createContext<AccentContextValue | null>(null)

export function AccentProvider({ children }: { children: React.ReactNode }) {
  const { resolvedTheme } = useTheme()
  const [accentId, setAccentIdState] = React.useState<AccentPreset>(() => {
    if (typeof document !== 'undefined') {
      const attr = document.documentElement.getAttribute('data-accent')
      return normalizeAccent(attr ?? 'default')
    }
    return 'default'
  })

  React.useEffect(() => {
    document.documentElement.setAttribute('data-accent', accentId)
  }, [accentId, resolvedTheme])

  const value: AccentContextValue = {
    accentId,
    setAccentId: (id) => setAccentIdState(normalizeAccent(id)),
    hasUserPreset: true,
    applyServerColor: () => {},
  }

  return <AccentContext.Provider value={value}>{children}</AccentContext.Provider>
}

export function useAccent() {
  const ctx = React.useContext(AccentContext)
  if (!ctx) {
    return {
      accentId: 'default' as AccentPreset,
      setAccentId: () => {},
      hasUserPreset: false,
      applyServerColor: () => {},
    }
  }
  return ctx
}
