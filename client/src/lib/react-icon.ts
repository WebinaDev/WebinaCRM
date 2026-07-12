import { createElement, type ComponentType, type ReactElement } from "react"

/** Lucide and similar icons may be functions or forwardRef objects. */
export function isRenderableIcon(
  icon: unknown,
): icon is ComponentType<{ className?: string }> {
  if (typeof icon === "function") {
    return true
  }
  if (typeof icon === "object" && icon !== null && "$$typeof" in icon) {
    const type = (icon as { type?: unknown }).type
    // React elements have a string/function type; component types do not.
    if (typeof type === "string") {
      return false
    }
    return true
  }
  return false
}

export function renderIcon(
  icon: ComponentType<{ className?: string }>,
  className: string,
): ReactElement | null {
  if (!isRenderableIcon(icon)) {
    return null
  }
  return createElement(icon, { className })
}
