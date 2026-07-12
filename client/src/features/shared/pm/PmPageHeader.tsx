import type { ReactNode } from "react"

type Props = {
  title: string
  description?: string
  actions?: ReactNode
  isRtl?: boolean
}

export function PmPageHeader({ title, description, actions }: Props) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div className="text-start">
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description ? (
          <p className="text-muted-foreground mt-1 text-sm">{description}</p>
        ) : null}
      </div>
      {actions ? (
        <div className="flex flex-wrap items-center gap-2">{actions}</div>
      ) : null}
    </div>
  )
}
