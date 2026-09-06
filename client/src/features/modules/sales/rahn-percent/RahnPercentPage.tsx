import { useEffect, useState } from "react"
import { getRahnSettings, type RahnSettings } from "@/api/rahn"
import { getAjaxMessage } from "@/api/client"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { useLocale } from "@/hooks/use-locale"
import { RahnCalculatorTab } from "@/features/modules/sales/rahn-percent/RahnCalculatorTab"
import { RahnSettingsTab } from "@/features/modules/sales/rahn-percent/RahnSettingsTab"
import { RahnStatementsTab } from "@/features/modules/sales/rahn-percent/RahnStatementsTab"
import { RahnQuotesTab } from "@/features/modules/sales/rahn-percent/RahnQuotesTab"
import { TableListSkeleton } from "@/components/TableListSkeleton"

export function RahnPercentPage() {
  const { t } = useLocale()
  const [settings, setSettings] = useState<RahnSettings | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    void getRahnSettings()
      .then((res) => {
        if (!res.success || !res.data?.settings) {
          setError(getAjaxMessage(res) || t("pages.rahn.loadError"))
          return
        }
        setSettings(res.data.settings)
      })
      .catch(() => setError(t("pages.rahn.loadError")))
      .finally(() => setLoading(false))
  }, [t])

  if (loading) return <TableListSkeleton />
  if (error || !settings) {
    return (
      <Alert>
        <AlertDescription>{error || t("pages.rahn.loadError")}</AlertDescription>
      </Alert>
    )
  }

  return (
    <div className="space-y-6">
      <PmPageHeader
        title={t("pages.rahn.title")}
        description={t("pages.rahn.description")}
      />
      <Tabs defaultValue="calculator">
        <TabsList className="flex flex-wrap h-auto gap-1">
          <TabsTrigger value="calculator">{t("pages.rahn.tabCalculator")}</TabsTrigger>
          <TabsTrigger value="statements">{t("pages.rahn.tabStatements")}</TabsTrigger>
          <TabsTrigger value="quotes">{t("pages.rahn.tabQuotes")}</TabsTrigger>
          <TabsTrigger value="settings">{t("pages.rahn.tabSettings")}</TabsTrigger>
        </TabsList>
        <TabsContent value="calculator" className="mt-4">
          <RahnCalculatorTab settings={settings} />
        </TabsContent>
        <TabsContent value="statements" className="mt-4">
          <RahnStatementsTab settings={settings} />
        </TabsContent>
        <TabsContent value="quotes" className="mt-4">
          <RahnQuotesTab />
        </TabsContent>
        <TabsContent value="settings" className="mt-4">
          <RahnSettingsTab settings={settings} onSaved={setSettings} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
