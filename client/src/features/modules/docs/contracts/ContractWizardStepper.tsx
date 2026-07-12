import { cn } from "@/lib/utils"
import { ChevronLeft, ChevronRight } from "lucide-react"

type Step = { id: number; label: string }

type Props = {
  steps: Step[]
  current: number
  isRtl?: boolean
}

export function ContractWizardStepper({ steps, current, isRtl }: Props) {
  return (
    <div className="flex items-center gap-2 mb-6 pb-4 border-b overflow-x-auto">
      {steps.map((step, idx) => (
        <div key={step.id} className="flex items-center gap-2 shrink-0">
          <div
            className={cn(
              "w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium",
              current === step.id
                ? "bg-primary text-primary-foreground"
                : current > step.id
                  ? "bg-primary/20 text-primary"
                  : "bg-muted text-muted-foreground",
            )}
          >
            {step.id}
          </div>
          <span className={cn("text-sm whitespace-nowrap", current === step.id ? "font-medium" : "text-muted-foreground")}>
            {step.label}
          </span>
          {idx < steps.length - 1 && (
            isRtl ? <ChevronLeft className="h-4 w-4 text-muted-foreground" /> : <ChevronRight className="h-4 w-4 text-muted-foreground" />
          )}
        </div>
      ))}
    </div>
  )
}
