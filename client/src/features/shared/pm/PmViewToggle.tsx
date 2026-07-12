import { Button } from "@/components/ui/button"

export type ViewToggleOption<T extends string> = {
  id: T
  label: string
  icon?: React.ReactNode
}

type Props<T extends string> = {
  value: T
  options: ViewToggleOption<T>[]
  onChange: (value: T) => void
  isRtl?: boolean
}

export function PmViewToggle<T extends string>({ value, options, onChange }: Props<T>) {
  return (
    <div className="flex flex-wrap gap-1 rounded-lg border p-1 bg-muted/30">
      {options.map((opt) => (
        <Button
          key={opt.id}
          type="button"
          variant={value === opt.id ? "default" : "ghost"}
          size="sm"
          className="gap-1.5"
          onClick={() => onChange(opt.id)}
        >
          {opt.icon}
          {opt.label}
        </Button>
      ))}
    </div>
  )
}
