import type { TFunction } from 'i18next'
import { toast } from 'sonner'

const CODE_KEYS: Record<string, string> = {
  invalid: 'errors.api.invalid',
  forbidden: 'errors.api.forbidden',
  not_found: 'errors.api.notFound',
  invalid_role: 'errors.api.invalidRole',
  forbidden_role: 'errors.api.forbiddenRole',
  invalid_nonce: 'errors.api.invalidNonce',
  timeout: 'errors.api.timeout',
  empty_reply: 'errors.api.emptyReply',
  transport: 'errors.api.transport',
  crm_unreachable: 'errors.api.crmUnreachable',
}

function messageLooksLikeTimeout(msg: string): boolean {
  const lower = msg.toLowerCase()
  return (
    lower.includes('curl error 28') ||
    lower.includes('timed out') ||
    lower.includes('did not respond in time') ||
    (lower.includes('زمان') && lower.includes('پاسخ'))
  )
}

function messageLooksLikeEmptyReply(msg: string): boolean {
  const lower = msg.toLowerCase()
  return (
    lower.includes('curl error 52') ||
    lower.includes('empty reply') ||
    lower.includes('closed the connection without a response') ||
    (lower.includes('پاسخ') && lower.includes('خالی'))
  )
}

export function apiErrorMessage(t: TFunction, err: unknown): string {
  if (err && typeof err === 'object' && 'code' in err) {
    const code = String((err as { code: string }).code)
    const key = CODE_KEYS[code]
    if (key) return t(key)
  }
  if (err instanceof Error && err.message) {
    const msg = err.message.trim()
    if (messageLooksLikeTimeout(msg)) {
      return t('errors.api.timeout')
    }
    if (messageLooksLikeEmptyReply(msg)) {
      return t('errors.api.emptyReply')
    }
    if (/^(invalid|forbidden|not found)/i.test(msg)) {
      return t('errors.api.generic')
    }
    return msg
  }
  return t('errors.api.generic')
}

/** User-facing license message from REST snapshot (includes friendly PHP strings). */
export function licenseStatusMessage(
  t: TFunction,
  message: string | undefined,
  errorCode?: string,
): string {
  if (errorCode === 'timeout') return t('errors.api.timeout')
  if (errorCode === 'empty_reply') return t('errors.api.emptyReply')
  if (errorCode === 'transport') return t('errors.api.transport')
  if (message && message.trim()) {
    if (messageLooksLikeTimeout(message)) return t('errors.api.timeout')
    if (messageLooksLikeEmptyReply(message)) return t('errors.api.emptyReply')
    return message.trim()
  }
  return t('errors.api.generic')
}

/** Soft notice when REST check failed but last-known good license is shown. */
export function licenseCrmUnreachableMessage(t: TFunction): string {
  return t('errors.api.crmUnreachable')
}

export function toastApiError(t: TFunction, err: unknown): void {
  toast.error(apiErrorMessage(t, err))
}
