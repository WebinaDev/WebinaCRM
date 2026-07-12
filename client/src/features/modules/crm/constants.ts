export const STAFF_ROLE_SLUGS = [
  "system_manager",
  "finance_manager",
  "team_member",
  "administrator",
] as const

export const STAFF_ROLES = new Set<string>(STAFF_ROLE_SLUGS)

export const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
