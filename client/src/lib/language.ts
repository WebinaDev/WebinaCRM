/**
 * Language switching aligned with WebinoDashboard i18n (no full page reload).
 */

import { apiFetch } from '@/lib/api'
import { setDashboardLanguage } from '@/i18n'

const COOKIE_NAME = 'webino_language'
const COOKIE_MAX_AGE = 60 * 60 * 24 * 30

function normalizeLanguage(lang: string | null | undefined): 'fa' | 'en' {
  if (!lang) return 'en'
  const v = String(lang).toLowerCase().trim()
  if (v.startsWith('en') || v === 'english') return 'en'
  if (v.startsWith('fa') || v === 'persian') return 'fa'
  return 'en'
}

function setLanguageCookie(lang: 'fa' | 'en') {
  try {
    document.cookie = `${COOKIE_NAME}=${lang}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax`
  } catch {
    // ignore
  }
}

export async function changeLanguage(lang: 'fa' | 'en') {
  const normalized = normalizeLanguage(lang)
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(COOKIE_NAME, normalized)
  }
  setLanguageCookie(normalized)
  const uiLocale = normalized === 'fa' ? 'fa_IR' : 'en_US'
  void apiFetch('settings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ui_locale: uiLocale }),
  }).catch(() => {})
  await setDashboardLanguage(normalized)
}

export function getCurrentLanguage(): 'fa' | 'en' {
  if (typeof document === 'undefined') return 'fa'
  return normalizeLanguage(document.documentElement.getAttribute('lang'))
}
