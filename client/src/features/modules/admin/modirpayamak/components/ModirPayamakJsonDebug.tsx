import { useState } from "react"
import { ChevronDown } from "lucide-react"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"

type Props = {
  data: unknown
}

/** Dev-only raw API dump — never shown in production builds. */
export function ModirPayamakJsonDebug({ data }: Props) {
  const { t } = useLocale()
  const [open, setOpen] = useState(false)
  if (!import.meta.env.DEV) return null
  if (data == null) return null

  return (
    <Collapsible open={open} onOpenChange={setOpen} className="rounded-lg border px-3">
      <CollapsibleTrigger className="flex w-full items-center justify-between py-3 text-sm text-muted-foreground hover:text-foreground">
        {t("pages.modirpayamak.rawApi")}
        <ChevronDown className={cn("h-4 w-4 transition-transform", open && "rotate-180")} />
      </CollapsibleTrigger>
      <CollapsibleContent>
        <pre className="max-h-64 overflow-auto rounded bg-muted p-3 text-xs">{JSON.stringify(data, null, 2)}</pre>
      </CollapsibleContent>
    </Collapsible>
  )
}
