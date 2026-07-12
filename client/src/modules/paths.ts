import { getErpModule, modulePath } from '@/modules/registry'

export { modulePath, getErpModule }

/** Build dashboard-relative href (leading slash, no basename). */
export function dashHref(path: string): string {
  const p = path.replace(/^\//, '')
  return p ? `/${p}` : '/'
}
