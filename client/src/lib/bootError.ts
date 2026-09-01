import en from '@/i18n/locales/en.json'
import fa from '@/i18n/locales/fa.json'

type BootBundle = Record<string, string>

const bundles: Record<string, BootBundle> = {
  fa,
  en,
}

function bootLocale(): string {
  const raw = window.webinoDashboard?.locale ?? 'fa'
  return String(raw).startsWith('en') ? 'en' : 'fa'
}

export function bootI18n(key: string): string {
  const locale = bootLocale()
  const bundle = bundles[locale] ?? bundles.fa
  return bundle[key] ?? bundles.en[key] ?? key
}

/** Visible fallback when React cannot mount (avoids a blank white screen). */
export function showBootError(message: string, detail?: string) {
  const root = document.getElementById('root')
  if (!root) {
    return
  }

  root.replaceChildren()

  const wrap = document.createElement('div')
  wrap.setAttribute('role', 'alert')
  wrap.style.cssText =
    'box-sizing:border-box;min-height:100vh;padding:1.5rem;font-family:system-ui,sans-serif;background:#fef2f2;color:#7f1d1d;'

  const heading = document.createElement('h1')
  heading.style.cssText = 'margin:0 0 0.5rem;font-size:1.125rem;font-weight:600;'
  heading.textContent = message
  wrap.appendChild(heading)

  if (detail) {
    const para = document.createElement('p')
    para.style.cssText = 'margin:0 0 1rem;font-size:0.875rem;word-break:break-word;'
    para.textContent = detail
    wrap.appendChild(para)
  }

  const reloadBtn = document.createElement('button')
  reloadBtn.type = 'button'
  reloadBtn.id = 'wd-boot-reload'
  reloadBtn.style.cssText =
    'padding:0.5rem 1rem;font-size:0.875rem;cursor:pointer;border:1px solid #b91c1c;border-radius:0.375rem;background:#fff;'
  reloadBtn.textContent = bootI18n('errors.reloadPage')
  reloadBtn.addEventListener('click', () => {
    window.location.reload()
  })
  wrap.appendChild(reloadBtn)

  root.appendChild(wrap)
}
