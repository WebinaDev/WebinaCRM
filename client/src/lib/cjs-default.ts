import type { ComponentType } from "react"

/** CJS/ESM interop: `module.exports` vs `export default` in production bundles. */
export function unwrapDefaultExport<T>(mod: T): T {
  if (mod == null || typeof mod !== "object") {
    return mod
  }
  const withDefault = mod as T & { default?: T }
  let candidate = withDefault.default ?? mod
  if (candidate && typeof candidate === "object" && "default" in candidate) {
    const nested = (candidate as T & { default?: T }).default
    if (nested != null) {
      candidate = nested
    }
  }
  return candidate
}

export function isReactComponentType(
  value: unknown,
): value is ComponentType<Record<string, unknown>> {
  if (typeof value === "function") {
    return true
  }
  if (value && typeof value === "object" && "$$typeof" in value) {
    const type = (value as { type?: unknown }).type
    if (typeof type === "string") {
      return false
    }
    return true
  }
  return false
}

export function resolveDatePickerComponent(
  mod: unknown,
): ComponentType<Record<string, unknown>> | null {
  const unwrapped = unwrapDefaultExport(mod as object)
  if (isReactComponentType(unwrapped)) {
    return unwrapped
  }
  return null
}

export function hasDateObjectName(value: unknown): value is { name: string } {
  return (
    value != null &&
    typeof value === "object" &&
    "name" in value &&
    typeof (value as { name: unknown }).name === "string"
  )
}
