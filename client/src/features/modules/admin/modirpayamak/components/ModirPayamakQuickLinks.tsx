import { Link } from "react-router-dom"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useLocale } from "@/hooks/use-locale"
import {
  BookUser,
  Code,
  DraftingCompass,
  FileText,
  Headphones,
  Phone,
  Send,
  Settings,
  ShoppingCart,
  UserCog,
  Users,
  Wallet,
} from "lucide-react"

const LINKS = [
  { to: "send", labelKey: "sendTitle", icon: Send },
  { to: "reports", labelKey: "reportsTitle", icon: FileText },
  { to: "customers", labelKey: "customersTitle", icon: Users },
  { to: "packages", labelKey: "packagesTitle", icon: Wallet },
  { to: "orders", labelKey: "ordersTitle", icon: ShoppingCart },
  { to: "patterns", labelKey: "patternsTitle", icon: Code },
  { to: "phonebooks", labelKey: "phonebooksTitle", icon: BookUser },
  { to: "numbers", labelKey: "numbersTitle", icon: Phone },
  { to: "users", labelKey: "usersTitle", icon: UserCog },
  { to: "tickets", labelKey: "ticketsTitle", icon: Headphones },
  { to: "drafts", labelKey: "draftsTitle", icon: DraftingCompass },
  { to: "settings", labelKey: "settingsTitle", icon: Settings },
] as const

export function ModirPayamakQuickLinks() {
  const { t } = useLocale()
  const base = "/admin/integrations/modirpayamak"

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("pages.modirpayamak.quickLinks")}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          {LINKS.map(({ to, labelKey, icon: Icon }) => (
            <Button key={to} variant="outline" className="justify-start h-auto py-3" asChild>
              <Link to={`${base}/${to}`}>
                <Icon className="me-2 h-4 w-4 shrink-0" />
                {t(`pages.modirpayamak.${labelKey}`)}
              </Link>
            </Button>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
