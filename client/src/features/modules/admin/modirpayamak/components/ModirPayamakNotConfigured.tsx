import { Link } from "react-router-dom"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import { useLocale } from "@/hooks/use-locale"

type Props = {
  configured?: boolean
}

export function ModirPayamakNotConfigured({ configured = false }: Props) {
  const { t } = useLocale()
  if (configured) return null

  return (
    <Alert>
      <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
        <span>{t("pages.modirpayamak.notConfigured")}</span>
        <Button variant="outline" size="sm" asChild>
          <Link to="/admin/integrations/modirpayamak/settings">{t("pages.modirpayamak.settings")}</Link>
        </Button>
      </AlertDescription>
    </Alert>
  )
}
