/**
 * Login page API — REST auth routes (webinocrm/v1/auth/*).
 */

import { apiFetch } from '@/lib/api'

function getConfig() {
  const c = window.WebinoLoginConfig
  const rest = c?.restUrl ?? (c?.ajaxUrl ? c.ajaxUrl.replace(/\/admin-ajax\.php$/, '/wp-json/webinocrm/v1/') : '')
  if (!rest) {
    throw new Error('WebinoLoginConfig restUrl not found')
  }
  return { restUrl: rest.endsWith('/') ? rest : `${rest}/` }
}

async function authPost<T>(path: string, params: Record<string, string | number>) {
  const { restUrl } = getConfig()
  const body: Record<string, string> = {}
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== '') body[k] = String(v)
  })
  const json = await apiFetch<{ success: boolean; data?: T; message?: string }>(
    `${restUrl}${path.replace(/^\//, '')}`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    },
  )
  if (!json.success) {
    const msg =
      (json.data && typeof json.data === 'object' && 'message' in json.data
        ? (json.data as { message?: string }).message
        : undefined) ?? json.message ?? 'Request failed'
    throw new Error(msg)
  }
  return json.data as T
}

export async function sendLoginOtp(phoneNumber: string) {
  return authPost<{ message: string; expires_in: number }>('auth/login/otp/send', {
    phone_number: phoneNumber,
  })
}

export async function verifyLoginOtp(phoneNumber: string, otpCode: string) {
  return authPost<{ message: string; redirect_url: string; needs_password?: boolean }>(
    'auth/login/otp/verify',
    { phone_number: phoneNumber, otp_code: otpCode },
  )
}

export async function sendEmailOtp(email: string) {
  return authPost<{ message: string; expires_in: number }>('auth/login/email-otp/send', { email })
}

export async function verifyEmailOtp(email: string, otpCode: string) {
  return authPost<{ message: string; redirect_url: string }>('auth/login/email-otp/verify', {
    email,
    otp_code: otpCode,
  })
}

export async function loginWithPassword(username: string, password: string, remember: boolean) {
  return authPost<{ message: string; redirect_url: string }>('auth/login/password', {
    username,
    password,
    remember: remember ? 1 : 0,
  })
}

export async function registerWithPassword(
  registerKind: 'email' | 'username' | 'national_id',
  identifier: string,
  password: string,
  confirmPassword: string,
) {
  return authPost<{ message: string; redirect_url: string }>('auth/register', {
    register_kind: registerKind,
    identifier,
    password,
    confirm_password: confirmPassword,
  })
}

export async function setPassword(phoneNumber: string, password: string, confirmPassword: string) {
  return authPost<{ message: string; redirect_url: string }>('auth/set-password', {
    phone_number: phoneNumber,
    password,
    confirm_password: confirmPassword,
  })
}

export function getLoginConfig() {
  return window.WebinoLoginConfig
}
