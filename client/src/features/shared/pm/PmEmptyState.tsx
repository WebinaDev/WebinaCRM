import type { LucideIcon } from "lucide-react"
import type { ReactNode } from "react"
import { useLocale } from "@/hooks/use-locale"
import { renderIcon } from "@/lib/react-icon"
import { cn } from "@/lib/utils"

type Props = {
  icon?: LucideIcon
  iconNode?: ReactNode
  message: string
  className?: string
  /** Force centered layout (e.g. charts). Default: start-aligned in RTL. */
  centered?: boolean
}

export function PmEmptyState({ icon: Icon, iconNode, message, className, centered }: Props) {
  const { isRtl } = useLocale()
  const alignStart = isRtl && !centered

  return (
    <div
      className={cn(
        "flex flex-col py-12 text-muted-foreground",
        alignStart ? "items-start text-start" : "items-center justify-center",
        className,
      )}
    >
      {iconNode ?? (Icon ? renderIcon(Icon, cn("h-10 w-10 mb-3 opacity-40", alignStart && "self-start")) : null)}
      <p className="text-sm">{message}</p>
    </div>
  )
}
