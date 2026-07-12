import { ChevronDown } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { setDashboardLanguage } from '@/i18n'
import { apiFetch } from '@/lib/api'

const LANGS = [
  { code: 'en', labelKey: 'settings.langEn', flag: '🇬🇧' },
  { code: 'fa', labelKey: 'settings.langFa', flag: '🇮🇷' },
] as const

export function LanguageMenu() {
  const { i18n, t } = useTranslation()
  const current = LANGS.find((l) => l.code === i18n.language) ?? LANGS[0]

  function select(lng: string) {
    void setDashboardLanguage(lng)
    void apiFetch('settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ui_locale: lng }),
    }).catch(() => {})
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-8 gap-1.5 px-2 text-xs"
          aria-label={t('settings.language')}
        >
          <span aria-hidden>{current.flag}</span>
          <span className="uppercase">{current.code}</span>
          <ChevronDown className="size-3 opacity-60" aria-hidden />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {LANGS.map((l) => (
          <DropdownMenuItem key={l.code} className="gap-2" onSelect={() => select(l.code)}>
            <span aria-hidden>{l.flag}</span>
            <span>{t(l.labelKey)}</span>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
