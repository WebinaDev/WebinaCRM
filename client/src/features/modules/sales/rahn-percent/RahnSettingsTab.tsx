import { useState } from "react"
import { toast } from "sonner"
import {
  saveRahnSettings,
  type RahnBilling,
  type RahnCatalogItem,
  type RahnSettings,
} from "@/api/rahn"
import { getAjaxMessage } from "@/api/client"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useLocale } from "@/hooks/use-locale"
import { Plus, Trash2, Save } from "lucide-react"

type Props = {
  settings: RahnSettings
  onSaved: (next: RahnSettings) => void
}

function newItem(sort: number): RahnCatalogItem {
  return {
    id: `svc_${Date.now()}_${sort}`,
    name: "",
    billing: "once",
    period_months: 1,
    renewable: false,
    amount: 0,
    active: true,
    default_selected: false,
    category: "",
    description: "",
    sort_order: sort,
  }
}

export function RahnSettingsTab({ settings, onSaved }: Props) {
  const { t } = useLocale()
  const [draft, setDraft] = useState<RahnSettings>(settings)
  const [saving, setSaving] = useState(false)

  const updateCatalog = (id: string, patch: Partial<RahnCatalogItem>) => {
    setDraft((prev) => ({
      ...prev,
      catalog: prev.catalog.map((row) => (row.id === id ? { ...row, ...patch } : row)),
    }))
  }

  const removeItem = (id: string) => {
    setDraft((prev) => ({
      ...prev,
      catalog: prev.catalog.filter((row) => row.id !== id),
    }))
  }

  const addItem = () => {
    setDraft((prev) => ({
      ...prev,
      catalog: [...prev.catalog, newItem(prev.catalog.length + 1)],
    }))
  }

  const save = async () => {
    setSaving(true)
    try {
      const res = await saveRahnSettings(draft)
      if (!res.success || !res.data?.settings) {
        toast.error(getAjaxMessage(res) || t("pages.rahn.saveError"))
        return
      }
      onSaved(res.data.settings)
      setDraft(res.data.settings)
      toast.success(res.data.message || t("pages.rahn.settingsSaved"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.rahn.formulaSettings")}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {(
            [
              ["T", "pages.rahn.duration"],
              ["m", "pages.rahn.margin"],
              ["k", "pages.rahn.floorRatio"],
              ["p_min", "pages.rahn.pMin"],
              ["p_max", "pages.rahn.pMax"],
              ["p_default", "pages.rahn.pDefault"],
              ["s_hat_default", "pages.rahn.sHatDefault"],
            ] as const
          ).map(([key, labelKey]) => (
            <div key={key} className="grid gap-2">
              <Label>{t(labelKey)}</Label>
              <Input
                type="number"
                step="any"
                value={draft[key]}
                onChange={(e) =>
                  setDraft((prev) => ({
                    ...prev,
                    [key]: Number(e.target.value) || 0,
                  }))
                }
              />
            </div>
          ))}
          <div className="grid gap-2 sm:col-span-2 lg:col-span-3">
            <Label>{t("pages.rahn.clauseTemplate")}</Label>
            <Textarea
              rows={3}
              value={draft.clause_template}
              onChange={(e) => setDraft((prev) => ({ ...prev, clause_template: e.target.value }))}
            />
            <p className="text-xs text-muted-foreground">{t("pages.rahn.clauseHints")}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.rahn.salesDefinition")}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2">
          {(["G", "R", "D", "X"] as const).map((key) => (
            <div key={key} className="flex items-center gap-3 rounded-lg border p-3">
              <Switch
                checked={draft.sales_definition[key].enabled}
                onCheckedChange={(v) =>
                  setDraft((prev) => ({
                    ...prev,
                    sales_definition: {
                      ...prev.sales_definition,
                      [key]: { ...prev.sales_definition[key], enabled: !!v },
                    },
                  }))
                }
              />
              <div className="flex-1 grid gap-1">
                <Label className="text-xs text-muted-foreground">{key}</Label>
                <Input
                  value={draft.sales_definition[key].label}
                  onChange={(e) =>
                    setDraft((prev) => ({
                      ...prev,
                      sales_definition: {
                        ...prev.sales_definition,
                        [key]: { ...prev.sales_definition[key], label: e.target.value },
                      },
                    }))
                  }
                />
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.rahn.reviewSettings")}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-3">
          <div className="flex items-center gap-3">
            <Switch
              checked={draft.review.enabled}
              onCheckedChange={(v) =>
                setDraft((prev) => ({
                  ...prev,
                  review: { ...prev.review, enabled: !!v },
                }))
              }
            />
            <Label>{t("pages.rahn.reviewEnabled")}</Label>
          </div>
          <div className="grid gap-2">
            <Label>{t("pages.rahn.deviationPercent")}</Label>
            <Input
              type="number"
              value={draft.review.deviation_percent}
              onChange={(e) =>
                setDraft((prev) => ({
                  ...prev,
                  review: { ...prev.review, deviation_percent: Number(e.target.value) || 0 },
                }))
              }
            />
          </div>
          <div className="grid gap-2">
            <Label>{t("pages.rahn.consecutiveMonths")}</Label>
            <Input
              type="number"
              value={draft.review.consecutive_months}
              onChange={(e) =>
                setDraft((prev) => ({
                  ...prev,
                  review: { ...prev.review, consecutive_months: Number(e.target.value) || 1 },
                }))
              }
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-base">{t("pages.rahn.catalog")}</CardTitle>
          <Button type="button" size="sm" variant="secondary" onClick={addItem}>
            <Plus className="h-4 w-4" />
            {t("pages.rahn.addService")}
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          {draft.catalog.map((item) => (
            <div key={item.id} className="rounded-xl border p-4 grid gap-3 md:grid-cols-6">
              <div className="md:col-span-2 grid gap-2">
                <Label>{t("pages.rahn.serviceName")}</Label>
                <Input
                  value={item.name}
                  onChange={(e) => updateCatalog(item.id, { name: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t("pages.rahn.billingLabel")}</Label>
                <Select
                  value={item.billing}
                  onValueChange={(v) => updateCatalog(item.id, { billing: v as RahnBilling })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="once">{t("pages.rahn.billing.once")}</SelectItem>
                    <SelectItem value="monthly">{t("pages.rahn.billing.monthly")}</SelectItem>
                    <SelectItem value="yearly">{t("pages.rahn.billing.yearly")}</SelectItem>
                    <SelectItem value="custom">{t("pages.rahn.billing.custom")}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>{t("pages.rahn.periodMonths")}</Label>
                <Input
                  type="number"
                  min={1}
                  value={item.period_months}
                  onChange={(e) =>
                    updateCatalog(item.id, { period_months: Math.max(1, Number(e.target.value) || 1) })
                  }
                />
              </div>
              <div className="grid gap-2">
                <Label>{t("pages.rahn.amount")}</Label>
                <Input
                  type="number"
                  value={item.amount}
                  onChange={(e) => updateCatalog(item.id, { amount: Number(e.target.value) || 0 })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t("pages.rahn.category")}</Label>
                <Input
                  value={item.category}
                  onChange={(e) => updateCatalog(item.id, { category: e.target.value })}
                />
              </div>
              <div className="md:col-span-6 flex flex-wrap items-center gap-4">
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={item.renewable}
                    onCheckedChange={(v) => updateCatalog(item.id, { renewable: !!v })}
                  />
                  {t("pages.rahn.renewable")}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={item.active}
                    onCheckedChange={(v) => updateCatalog(item.id, { active: !!v })}
                  />
                  {t("pages.rahn.active")}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={item.default_selected}
                    onCheckedChange={(v) => updateCatalog(item.id, { default_selected: !!v })}
                  />
                  {t("pages.rahn.defaultSelected")}
                </label>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  className="text-destructive ms-auto"
                  onClick={() => removeItem(item.id)}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button type="button" onClick={() => void save()} disabled={saving}>
          <Save className="h-4 w-4" />
          {t("pages.rahn.saveSettings")}
        </Button>
      </div>
    </div>
  )
}
