import { useMemo, useState } from "react"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useLocale } from "@/hooks/use-locale"
import { ChevronDown, Plus, Trash2 } from "lucide-react"

export type PatternVariable = { name: string; type: "string" | "integer" }

export type PatternFormValue = {
  title: string
  description: string
  website: string
  message: string
  is_share: boolean
  variable: PatternVariable[]
  brandName: string
  checklist: {
    brandInMessage: boolean
    nonPromotional: boolean
    enamadIfLink: boolean
    testWordIfTest: boolean
  }
}

export const emptyPatternForm = (): PatternFormValue => ({
  title: "",
  description: "",
  website: "",
  message: "",
  is_share: false,
  variable: [],
  brandName: "",
  checklist: {
    brandInMessage: false,
    nonPromotional: false,
    enamadIfLink: false,
    testWordIfTest: false,
  },
})

export function validatePatternForm(form: PatternFormValue, t: (k: string) => string): string | null {
  if (!form.title.trim()) return t("pages.modirpayamak.patternTitleRequired")
  if (!form.description.trim()) return t("pages.modirpayamak.patternDescRequired")
  if (!form.message.trim()) return t("pages.modirpayamak.patternMessageRequired")
  if (form.website && !/^https:\/\//i.test(form.website.trim())) {
    return t("pages.modirpayamak.patternWebsiteHttps")
  }
  const brand = form.brandName.trim()
  if (brand && !form.message.includes(brand)) {
    return t("pages.modirpayamak.patternBrandMissing")
  }
  for (const v of form.variable) {
    const name = v.name.trim()
    if (!name) return t("pages.modirpayamak.patternVarNameRequired")
    if (!form.message.includes(`%${name}%`)) {
      return t("pages.modirpayamak.patternVarNotInMessage")
    }
    if (/https?:\/\//i.test(name) || (brand && name === brand)) {
      return t("pages.modirpayamak.patternVarForbidden")
    }
  }
  if (/%https?:\/\//i.test(form.message) || /%(url|link|website)%/i.test(form.message)) {
    return t("pages.modirpayamak.patternLinkAsVar")
  }
  if (!form.checklist.brandInMessage || !form.checklist.nonPromotional) {
    return t("pages.modirpayamak.patternChecklistRequired")
  }
  return null
}

export function patternFormToPayload(form: PatternFormValue): Record<string, unknown> {
  return {
    title: form.title.trim(),
    description: form.description.trim(),
    message: form.message.trim(),
    website: form.website.trim() || undefined,
    is_share: form.is_share,
    variable: form.variable
      .filter((v) => v.name.trim())
      .map((v) => ({ name: v.name.trim(), type: v.type })),
  }
}

type Props = {
  value: PatternFormValue
  onChange: (next: PatternFormValue) => void
  disabled?: boolean
}

export function ModirPayamakPatternForm({ value, onChange, disabled }: Props) {
  const { t } = useLocale()
  const [rulesOpen, setRulesOpen] = useState(true)

  const set = <K extends keyof PatternFormValue>(key: K, v: PatternFormValue[K]) => {
    onChange({ ...value, [key]: v })
  }

  const rules = useMemo(
    () => [
      t("pages.modirpayamak.patternRule1"),
      t("pages.modirpayamak.patternRule2"),
      t("pages.modirpayamak.patternRule3"),
      t("pages.modirpayamak.patternRule4"),
      t("pages.modirpayamak.patternRule5"),
      t("pages.modirpayamak.patternRule6"),
    ],
    [t],
  )

  return (
    <div className="space-y-4">
      <Collapsible open={rulesOpen} onOpenChange={setRulesOpen}>
        <CollapsibleTrigger asChild>
          <Button type="button" variant="outline" size="sm" className="w-full justify-between">
            {t("pages.modirpayamak.patternRulesTitle")}
            <ChevronDown className={`h-4 w-4 transition ${rulesOpen ? "rotate-180" : ""}`} />
          </Button>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <ul className="mt-2 list-disc space-y-1 rounded-md border bg-muted/40 px-5 py-3 text-sm text-muted-foreground">
            {rules.map((r, i) => (
              <li key={i}>{r}</li>
            ))}
          </ul>
        </CollapsibleContent>
      </Collapsible>

      <div>
        <Label>{t("pages.modirpayamak.patternTitle")}</Label>
        <Input
          className="mt-1"
          disabled={disabled}
          value={value.title}
          onChange={(e) => set("title", e.target.value)}
        />
      </div>
      <div>
        <Label>{t("pages.modirpayamak.patternDescription")}</Label>
        <Textarea
          className="mt-1"
          rows={2}
          disabled={disabled}
          value={value.description}
          onChange={(e) => set("description", e.target.value)}
        />
      </div>
      <div>
        <Label>{t("pages.modirpayamak.patternWebsite")}</Label>
        <Input
          className="mt-1"
          dir="ltr"
          placeholder="https://example.com"
          disabled={disabled}
          value={value.website}
          onChange={(e) => set("website", e.target.value)}
        />
      </div>
      <div>
        <Label>{t("pages.modirpayamak.brandName")}</Label>
        <Input
          className="mt-1"
          disabled={disabled}
          value={value.brandName}
          onChange={(e) => set("brandName", e.target.value)}
          placeholder={t("pages.modirpayamak.brandNameHint")}
        />
      </div>
      <div>
        <Label>{t("pages.modirpayamak.patternMessage")}</Label>
        <Textarea
          className="mt-1 font-mono text-sm"
          rows={4}
          disabled={disabled}
          value={value.message}
          onChange={(e) => set("message", e.target.value)}
          placeholder="%code%"
        />
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label>{t("pages.modirpayamak.patternVariables")}</Label>
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={disabled}
            onClick={() =>
              set("variable", [...value.variable, { name: "", type: "string" }])
            }
          >
            <Plus className="me-1 h-3 w-3" />
            {t("pages.modirpayamak.addVariable")}
          </Button>
        </div>
        {value.variable.map((v, idx) => (
          <div key={idx} className="flex gap-2">
            <Input
              className="font-mono"
              dir="ltr"
              placeholder="code"
              disabled={disabled}
              value={v.name}
              onChange={(e) => {
                const next = [...value.variable]
                next[idx] = { ...next[idx], name: e.target.value }
                set("variable", next)
              }}
            />
            <Select
              value={v.type}
              onValueChange={(type) => {
                const next = [...value.variable]
                next[idx] = { ...next[idx], type: type as "string" | "integer" }
                set("variable", next)
              }}
              disabled={disabled}
            >
              <SelectTrigger className="w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="string">string</SelectItem>
                <SelectItem value="integer">integer</SelectItem>
              </SelectContent>
            </Select>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              disabled={disabled}
              onClick={() => set("variable", value.variable.filter((_, i) => i !== idx))}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </div>
        ))}
      </div>

      <div className="space-y-2 rounded-md border p-3">
        <Label className="mb-1 block">{t("pages.modirpayamak.patternChecklist")}</Label>
        {(
          [
            ["brandInMessage", "pages.modirpayamak.checkBrandInMessage"],
            ["nonPromotional", "pages.modirpayamak.checkNonPromotional"],
            ["enamadIfLink", "pages.modirpayamak.checkEnamad"],
            ["testWordIfTest", "pages.modirpayamak.checkTestWord"],
          ] as const
        ).map(([key, labelKey]) => (
          <label key={key} className="flex items-start gap-2 text-sm">
            <Checkbox
              checked={value.checklist[key]}
              disabled={disabled}
              onCheckedChange={(c) =>
                set("checklist", { ...value.checklist, [key]: c === true })
              }
            />
            <span>{t(labelKey)}</span>
          </label>
        ))}
      </div>

      <label className="flex items-center gap-2 text-sm">
        <Checkbox
          checked={value.is_share}
          disabled={disabled}
          onCheckedChange={(c) => set("is_share", c === true)}
        />
        {t("pages.modirpayamak.patternIsShare")}
      </label>
    </div>
  )
}
