type TranslateFn = (key: string) => string

const KNOWN_ERROR_KEYS: Array<{ match: RegExp; key: string }> = [
  {
    match: /api key is not configured/i,
    key: "pages.modirpayamak.notConfigured",
  },
  {
    match: /not configured/i,
    key: "pages.modirpayamak.notConfigured",
  },
]

/**
 * Map ModirPayamak REST/proxy English errors to CRM locale keys.
 */
export function mapModirPayamakError(
  message: string | null | undefined,
  t: TranslateFn,
): string | null {
  if (!message?.trim()) {
    return null
  }
  const trimmed = message.trim()
  for (const { match, key } of KNOWN_ERROR_KEYS) {
    if (match.test(trimmed)) {
      return t(key)
    }
  }
  return trimmed
}

export function isModirPayamakNotConfiguredError(message: string | null | undefined): boolean {
  if (!message?.trim()) {
    return false
  }
  return /api key is not configured/i.test(message) || /not configured/i.test(message)
}
