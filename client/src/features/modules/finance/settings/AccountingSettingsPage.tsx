import { useState, useEffect } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { accountingSettingsGet, accountingSettingsSave, accountingSeedChart } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { Loader2 } from "lucide-react"

export function AccountingSettingsPage() {
  const { t } = useLocale()
  const [currency, setCurrency] = useState("rial")
  const [fiscalYearId, setFiscalYearId] = useState(0)
  const [loading, setLoading] = useState(false)
  const [seedLoading, setSeedLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  useEffect(() => {
    accountingSettingsGet().then((res) => {
      if (res.success && res.data) {
        setCurrency(res.data.currency ?? "rial")
        setFiscalYearId(res.data.fiscal_year_id ?? 0)
      }
    })
  }, [])

  const save = async () => {
    setLoading(true)
    setError(null)
    setSuccess(null)
    const res = await accountingSettingsSave({ currency, fiscal_year_id: fiscalYearId })
    setLoading(false)
    if (res.success) setSuccess(t("pages.accounting.accountingSettings.ذخیره_شد"))
    else setError(res.message ?? t("pages.accounting.accountingSettings.خطا"))
  }

  const seed = async () => {
    setSeedLoading(true)
    setError(null)
    setSuccess(null)
    const res = await accountingSeedChart(fiscalYearId || undefined)
    setSeedLoading(false)
    if (res.success && res.data?.count !== undefined) {
      setSuccess(t("pages.accounting.accountingSettings.کدینگ_بارگذاری_شد", { count: res.data.count }))
    } else {
      setError(res.message ?? t("pages.accounting.accountingSettings.خطا_در_بارگذاری_کدینگ"))
    }
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.accountingDashboard.تنظیمات_حسابداری")}
      description={t("pages.accounting.accountingSettings.واحد_پول،_سال_مالی_پیشفرض،_کدینگ_استاندا")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.accounting.accountingSettings.تنظیمات_عمومی")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4 max-w-md">
          <div className="space-y-2">
            <Label>{t("pages.accounting.accountingSettings.واحد_پول")}</Label>
            <Select value={currency} onValueChange={setCurrency}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="rial">{t("pages.accounting.accountingSettings.ریال")}</SelectItem>
                <SelectItem value="toman">{t("common.toman")}</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>{t("pages.accounting.accountingSettings.سال_مالی_پیشفرض")}</Label>
            <FiscalYearSelect value={fiscalYearId} onChange={setFiscalYearId} className="w-full" />
          </div>
          <Button onClick={() => void save()} disabled={loading}>
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
            {t("pages.accounting.accountingSettings.ذخیره_تنظیمات")}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.accounting.accountingSettings.کدینگ_پیشفرض_ایران")}</CardTitle>
          <p className="text-sm text-muted-foreground">
            {t("pages.accounting.accountingSettings.بارگذاری_نمودار_حسابهای_استاندارد_گروهها")}
          </p>
        </CardHeader>
        <CardContent>
          <Button variant="outline" onClick={() => void seed()} disabled={seedLoading || !fiscalYearId}>
            {seedLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
            {t("pages.accounting.accountingSettings.بارگذاری_کدینگ")}
          </Button>
        </CardContent>
      </Card>
    </AccountingPageLayout>
  )
}
