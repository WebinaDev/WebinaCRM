const TOMAN_LABEL = /تومان|toman|irt/i

export function isTomanCurrency(codeOrLabel: string | undefined | null, symbol?: string | null): boolean {
  const code = (codeOrLabel ?? '').trim()
  const sym = (symbol ?? '').trim()
  if (!code && !sym) return false
  const upper = code.toUpperCase()
  if (upper === 'IRT' || upper === 'TOMAN') return true
  if (TOMAN_LABEL.test(code) || TOMAN_LABEL.test(sym)) return true
  return false
}

export function parseWcPriceText(text: string): { amount: string; isToman: boolean } {
  const raw = text.trim()
  if (!raw) return { amount: '', isToman: false }
  if (TOMAN_LABEL.test(raw)) {
    const amount = raw.replace(TOMAN_LABEL, '').replace(/\s+/g, ' ').trim()
    return { amount, isToman: true }
  }
  return { amount: raw, isToman: false }
}
