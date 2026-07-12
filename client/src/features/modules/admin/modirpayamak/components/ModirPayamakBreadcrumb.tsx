import { Link } from "react-router-dom"
import { ChevronRight } from "lucide-react"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"

type Props = {
  current: string
}

export function ModirPayamakBreadcrumb({ current }: Props) {
  const { t, isRtl } = useLocale()

  return (
    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
      <Button variant="link" className="h-auto p-0 text-muted-foreground" asChild>
        <Link to="/admin/integrations/modirpayamak">{t("pages.modirpayamak.dashboardTitle")}</Link>
      </Button>
      <ChevronRight className={cn("h-4 w-4", isRtl && "rotate-180")} />
      <span className="text-foreground font-medium">{current}</span>
    </div>
  )
}
