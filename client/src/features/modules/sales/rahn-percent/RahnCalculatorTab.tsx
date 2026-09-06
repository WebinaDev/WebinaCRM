import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"
import {
  calculateRahn,
  getRahnSettings,
  type RahnCalcResponse,
  type RahnCatalogItem,
  type RahnSettings,
} from "@/api/rahn"
import { getAjaxMessage } from "@/api/client"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Checkbox } from "@/components/ui/checkbox"
import { Slider } from "@/components/ui/slider"
import { Badge } from "@/components/ui/badge"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { useLocale } from "@/hooks/use-locale"
import { Loader2, Lock, Save, Link2 } from "lucide-react"
import {
  contractFromRahnQuote,
  lockRahnQuote,
  saveRahnQuote,
} from "@/api/rahn"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

type Props = {
  settings: RahnSettings
  onSettingsNeedRefresh?: () => void
}

function money(n: number, formatNumber: (n: number) => string) {
  return formatNumber(Math.round(n))
}

export function RahnCalculatorTab({ settings }: Props) {
  const { t, formatNumber } = useLocale()
  const activeCatalog = useMemo(
    () => settings.catalog.filter((c) => c.active),
    [settings.catalog],
  )

  const [selected, setSelected] = useState<string[]>(() =>
    activeCatalog.filter((c) => c.default_selected).map((c) => c.id),
  )
  const [sHat, setSHat] = useState(settings.s_hat_default)
  const [duration, setDuration] = useState(settings.T)
  const [mode, setMode] = useState<"from_p" | "from_f">("from_p")
  const [pPercent, setPPercent] = useState(settings.p_default * 100)
  const [fWanted, setFWanted] = useState(0)
  const [calc, setCalc] = useState<RahnCalcResponse | null>(null)
  const [loading, setLoading] = useState(false)
  const [quoteId, setQuoteId] = useState<number | null>(null)
  const [shareUrl, setShareUrl] = useState<string | null>(null)
  const [customers, setCustomers] = useState<{ id: number; display_name: string }[]>([])
  const [customerId, setCustomerId] = useState("")
  const [title, setTitle] = useState("")

  const pMinPct = settings.p_min * 100
  const pMaxPct = settings.p_max * 100

  const runCalc = useCallback(async () => {
    setLoading(true)
    try {
      const body: Record<string, unknown> = {
        selected_ids: selected,
        s_hat: sHat,
        T: duration,
        mode,
      }
      if (mode === "from_p") {
        body.p_percent = pPercent
      } else {
        body.F_wanted = fWanted
      }
      const res = await calculateRahn(body)
      if (!res.success || !res.data) {
        toast.error(getAjaxMessage(res) || t("pages.rahn.calcError"))
        return
      }
      setCalc(res.data)
      if (res.data.lock) {
        setPPercent(res.data.lock.p_percent)
        setFWanted(res.data.lock.F)
      }
    } finally {
      setLoading(false)
    }
  }, [selected, sHat, duration, mode, pPercent, fWanted, t])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void runCalc()
    }, 250)
    return () => window.clearTimeout(timer)
  }, [runCalc])

  useEffect(() => {
    // Soft-load customers via contracts list endpoint when available.
    void import("@/api/contracts").then(({ getContracts }) =>
      getContracts({ paged: 1 }).then((res) => {
        if (res.success && res.data?.customers) {
          setCustomers(res.data.customers)
        }
      }),
    )
  }, [])

  const toggleItem = (id: string, checked: boolean) => {
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))
  }

  const billingLabel = (item: RahnCatalogItem) => {
    const key = `pages.rahn.billing.${item.billing}`
    const base = t(key)
    if (item.billing === "custom") {
      return `${base} / ${item.period_months} ${t("pages.rahn.months")}${item.renewable ? ` · ${t("pages.rahn.renewable")}` : ""}`
    }
    return base
  }

  const onPercentSlide = (vals: number[]) => {
    setMode("from_p")
    setPPercent(vals[0] ?? pMinPct)
  }

  const onFixedSlide = (vals: number[]) => {
    setMode("from_f")
    setFWanted(vals[0] ?? 0)
  }

  const lock = calc?.lock
  const internal = calc?.internal
  const fMax = Math.max(
    (lock?.V_hat ?? settings.s_hat_default) * 1.5,
    (lock?.F_min ?? 0) * 2,
    fWanted * 1.2,
    1,
  )

  const handleSaveQuote = async () => {
    const res = await saveRahnQuote({
      id: quoteId ?? undefined,
      title,
      customer_id: customerId ? Number(customerId) : 0,
      selected_ids: selected,
      s_hat: sHat,
      T: duration,
      mode,
      p_percent: pPercent,
      F_wanted: fWanted,
    })
    if (!res.success || !res.data) {
      toast.error(getAjaxMessage(res) || t("pages.rahn.saveError"))
      return
    }
    setQuoteId(res.data.quote.id)
    setShareUrl(res.data.quote.share_url)
    setCalc(res.data.calculation)
    toast.success(res.data.message || t("pages.rahn.saved"))
  }

  const handleLock = async () => {
    let id = quoteId
    if (!id) {
      const saved = await saveRahnQuote({
        title,
        customer_id: customerId ? Number(customerId) : 0,
        selected_ids: selected,
        s_hat: sHat,
        T: duration,
        mode,
        p_percent: pPercent,
        F_wanted: fWanted,
      })
      if (!saved.success || !saved.data) {
        toast.error(getAjaxMessage(saved) || t("pages.rahn.saveError"))
        return
      }
      id = saved.data.quote.id
      setQuoteId(id)
      setShareUrl(saved.data.quote.share_url)
    }
    const res = await lockRahnQuote(id, {
      selected_ids: selected,
      s_hat: sHat,
      T: duration,
      mode,
      p_percent: pPercent,
      F_wanted: fWanted,
    })
    if (!res.success || !res.data) {
      toast.error(getAjaxMessage(res) || t("pages.rahn.lockError"))
      return
    }
    setCalc(res.data.calculation)
    setShareUrl(res.data.quote.share_url)
    toast.success(res.data.message || t("pages.rahn.locked"))
  }

  const handleContract = async () => {
    if (!quoteId) {
      toast.error(t("pages.rahn.lockFirst"))
      return
    }
    if (!customerId) {
      toast.error(t("pages.rahn.customerRequired"))
      return
    }
    const res = await contractFromRahnQuote(quoteId, {
      customer_id: Number(customerId),
      contract_title: title,
    })
    if (!res.success || !res.data) {
      toast.error(getAjaxMessage(res) || t("pages.rahn.contractError"))
      return
    }
    toast.success(res.data.message || t("pages.rahn.contractCreated"))
  }

  const copyShare = async () => {
    if (!shareUrl) {
      await handleSaveQuote()
      return
    }
    try {
      await navigator.clipboard.writeText(shareUrl)
      toast.success(t("pages.rahn.linkCopied"))
    } catch {
      toast.message(shareUrl)
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-5">
      <div className="lg:col-span-2 space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("pages.rahn.services")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {activeCatalog.map((item) => {
              const checked = selected.includes(item.id)
              return (
                <label
                  key={item.id}
                  className="flex items-start gap-3 rounded-lg border p-3 hover:bg-muted/40 cursor-pointer"
                >
                  <Checkbox
                    checked={checked}
                    onCheckedChange={(v) => toggleItem(item.id, !!v)}
                    className="mt-0.5"
                  />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-medium">{item.name}</span>
                      {item.category ? <Badge variant="secondary">{item.category}</Badge> : null}
                    </div>
                    <div className="text-xs text-muted-foreground mt-1">
                      {billingLabel(item)} · {money(item.amount, formatNumber)} {t("pages.rahn.toman")}
                    </div>
                  </div>
                </label>
              )
            })}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("pages.rahn.sessionInputs")}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4">
            <div className="grid gap-2">
              <Label>{t("pages.rahn.sHat")}</Label>
              <Input
                type="number"
                value={sHat}
                onChange={(e) => setSHat(Number(e.target.value) || 0)}
              />
            </div>
            <div className="grid gap-2">
              <Label>{t("pages.rahn.duration")}</Label>
              <Input
                type="number"
                min={1}
                value={duration}
                onChange={(e) => setDuration(Math.max(1, Number(e.target.value) || 1))}
              />
            </div>
            <div className="grid gap-2">
              <Label>{t("pages.rahn.quoteTitle")}</Label>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} />
            </div>
            <div className="grid gap-2">
              <Label>{t("pages.rahn.customer")}</Label>
              <Select value={customerId || "__none"} onValueChange={(v) => setCustomerId(v === "__none" ? "" : v)}>
                <SelectTrigger>
                  <SelectValue placeholder={t("pages.rahn.customer")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none">—</SelectItem>
                  {customers.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.display_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="lg:col-span-3 space-y-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <CardTitle className="text-base">{t("pages.rahn.negotiator")}</CardTitle>
            {loading ? <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /> : null}
          </CardHeader>
          <CardContent className="space-y-6">
            {lock ? (
              <Alert>
                <AlertDescription>
                  {t("pages.rahn.alphaHint", {
                    sHat: money(lock.S_hat, formatNumber),
                    alpha: money(lock.alpha, formatNumber),
                  })}
                </AlertDescription>
              </Alert>
            ) : null}

            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <Label>{t("pages.rahn.percentSlider")}</Label>
                <span className="font-semibold tabular-nums">
                  {formatNumber(Math.round(pPercent * 100) / 100)}%
                </span>
              </div>
              <Slider
                min={pMinPct}
                max={pMaxPct}
                step={0.1}
                value={[Math.min(pMaxPct, Math.max(pMinPct, pPercent))]}
                onValueChange={onPercentSlide}
              />
            </div>

            <div className="space-y-3">
              <div className="flex items-center justify-between gap-2">
                <Label>{t("pages.rahn.fixedSlider")}</Label>
                <span className="font-semibold tabular-nums">
                  {money(fWanted, formatNumber)} {t("pages.rahn.toman")}
                </span>
              </div>
              <Slider
                min={0}
                max={Math.ceil(fMax)}
                step={100000}
                value={[Math.min(fMax, Math.max(0, fWanted))]}
                onValueChange={onFixedSlide}
              />
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-xl border bg-primary/5 p-4">
                <div className="text-xs text-muted-foreground">{t("pages.rahn.fixedMonthly")}</div>
                <div className="text-2xl font-bold mt-1">
                  {money(lock?.F ?? 0, formatNumber)}
                </div>
              </div>
              <div className="rounded-xl border bg-emerald-500/10 p-4">
                <div className="text-xs text-muted-foreground">{t("pages.rahn.percentOfSales")}</div>
                <div className="text-2xl font-bold mt-1">
                  {formatNumber(Math.round((lock?.p_percent ?? 0) * 100) / 100)}%
                </div>
              </div>
              <div className="rounded-xl border p-4">
                <div className="text-xs text-muted-foreground">{t("pages.rahn.expectedValue")}</div>
                <div className="text-xl font-semibold mt-1">
                  {money(lock?.V_hat ?? 0, formatNumber)}
                </div>
              </div>
              <div className="rounded-xl border p-4">
                <div className="text-xs text-muted-foreground">{t("pages.rahn.breakeven")}</div>
                <div className="text-xl font-semibold mt-1">
                  {lock?.S_BE == null ? t("pages.rahn.undefined") : money(lock.S_BE, formatNumber)}
                </div>
              </div>
            </div>

            {calc?.clause ? (
              <div className="rounded-xl border border-dashed p-4 text-sm leading-7 bg-muted/30">
                {calc.clause}
              </div>
            ) : null}

            {internal ? (
              <div className="grid gap-2 sm:grid-cols-3 text-sm">
                <div className="rounded-lg bg-muted/50 p-3">
                  <div className="text-muted-foreground text-xs">C</div>
                  <div className="font-medium">{money(internal.C, formatNumber)}</div>
                </div>
                <div className="rounded-lg bg-muted/50 p-3">
                  <div className="text-muted-foreground text-xs">V*</div>
                  <div className="font-medium">{money(internal.V_star, formatNumber)}</div>
                </div>
                <div className="rounded-lg bg-muted/50 p-3">
                  <div className="text-muted-foreground text-xs">F_min</div>
                  <div className="font-medium">{money(internal.F_min, formatNumber)}</div>
                </div>
              </div>
            ) : null}

            {internal?.breakdown?.length ? (
              <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50">
                    <tr>
                      <th className="text-start p-2">{t("pages.rahn.service")}</th>
                      <th className="text-end p-2">U</th>
                      <th className="text-end p-2">M</th>
                      <th className="text-end p-2">{t("pages.rahn.monthlyShare")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {internal.breakdown.map((row) => (
                      <tr key={row.id} className="border-t">
                        <td className="p-2">{row.name}</td>
                        <td className="p-2 text-end tabular-nums">{money(row.U, formatNumber)}</td>
                        <td className="p-2 text-end tabular-nums">{money(row.M, formatNumber)}</td>
                        <td className="p-2 text-end tabular-nums">{money(row.monthly_share, formatNumber)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}

            <div className="flex flex-wrap gap-2">
              <Button type="button" variant="secondary" onClick={() => void handleSaveQuote()}>
                <Save className="h-4 w-4" />
                {t("pages.rahn.saveQuote")}
              </Button>
              <Button type="button" onClick={() => void handleLock()}>
                <Lock className="h-4 w-4" />
                {t("pages.rahn.lockFp")}
              </Button>
              <Button type="button" variant="outline" onClick={() => void copyShare()}>
                <Link2 className="h-4 w-4" />
                {t("pages.rahn.copyLink")}
              </Button>
              <Button type="button" variant="default" onClick={() => void handleContract()}>
                {t("pages.rahn.createContract")}
              </Button>
            </div>
            {shareUrl ? (
              <p className="text-xs text-muted-foreground break-all dir-ltr text-left">{shareUrl}</p>
            ) : null}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

export async function loadRahnSettingsOrThrow() {
  const res = await getRahnSettings()
  if (!res.success || !res.data?.settings) {
    throw new Error(getAjaxMessage(res) || "settings")
  }
  return res.data.settings
}
