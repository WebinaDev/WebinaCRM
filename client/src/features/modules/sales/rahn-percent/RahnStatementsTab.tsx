import { useEffect, useState } from "react"
import { toast } from "sonner"
import {
  calculateRahnStatement,
  listRahnContracts,
  listRahnStatements,
  saveRahnStatement,
  type RahnBill,
  type RahnContract,
  type RahnSettings,
} from "@/api/rahn"
import { getAjaxMessage } from "@/api/client"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Alert, AlertDescription } from "@/components/ui/alert"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useLocale } from "@/hooks/use-locale"
import { Calculator, FilePlus2 } from "lucide-react"

type Props = { settings: RahnSettings }

export function RahnStatementsTab({ settings }: Props) {
  const { t, formatNumber } = useLocale()
  const [contracts, setContracts] = useState<RahnContract[]>([])
  const [contractId, setContractId] = useState("")
  const [yearMonth, setYearMonth] = useState(() => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`
  })
  const [G, setG] = useState(0)
  const [R, setR] = useState(0)
  const [D, setD] = useState(0)
  const [X, setX] = useState(0)
  const [bill, setBill] = useState<RahnBill | null>(null)
  const [reviewAlert, setReviewAlert] = useState<string | null>(null)
  const [history, setHistory] = useState<Record<string, unknown>[]>([])

  useEffect(() => {
    void listRahnContracts().then((res) => {
      if (res.success && res.data?.contracts) {
        setContracts(res.data.contracts)
      }
    })
  }, [])

  useEffect(() => {
    if (!contractId) {
      setHistory([])
      return
    }
    void listRahnStatements(Number(contractId)).then((res) => {
      if (res.success && res.data?.statements) {
        setHistory(res.data.statements)
      }
    })
  }, [contractId])

  const selected = contracts.find((c) => String(c.id) === contractId)

  const calc = async () => {
    if (!contractId) {
      toast.error(t("pages.rahn.pickContract"))
      return
    }
    const res = await calculateRahnStatement({
      contract_id: Number(contractId),
      year_month: yearMonth,
      G,
      R,
      D,
      X,
    })
    if (!res.success || !res.data) {
      toast.error(getAjaxMessage(res) || t("pages.rahn.calcError"))
      return
    }
    setBill(res.data.bill)
    setReviewAlert(res.data.review_alert?.message ?? null)
  }

  const save = async (createInvoice: boolean) => {
    if (!contractId) return
    const res = await saveRahnStatement({
      contract_id: Number(contractId),
      year_month: yearMonth,
      G,
      R,
      D,
      X,
      create_invoice: createInvoice,
    })
    if (!res.success || !res.data) {
      toast.error(getAjaxMessage(res) || t("pages.rahn.saveError"))
      return
    }
    setBill(res.data.bill)
    setReviewAlert(res.data.review_alert?.message ?? null)
    toast.success(res.data.message || t("pages.rahn.statementSaved"))
    const hist = await listRahnStatements(Number(contractId))
    if (hist.success && hist.data?.statements) setHistory(hist.data.statements)
  }

  const salesFields = (
    [
      ["G", G, setG],
      ["R", R, setR],
      ["D", D, setD],
      ["X", X, setX],
    ] as const
  ).filter(([key]) => settings.sales_definition[key]?.enabled)

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.rahn.monthlyBilling")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-2">
            <Label>{t("pages.rahn.lockedContract")}</Label>
            <Select value={contractId || "__none"} onValueChange={(v) => setContractId(v === "__none" ? "" : v)}>
              <SelectTrigger>
                <SelectValue placeholder={t("pages.rahn.pickContract")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">—</SelectItem>
                {contracts.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.title} — {formatNumber(Math.round(c.F))} + {formatNumber(Math.round(c.p_percent * 100) / 100)}%
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          {selected ? (
            <Alert>
              <AlertDescription>{selected.clause}</AlertDescription>
            </Alert>
          ) : null}
          <div className="grid gap-2">
            <Label>{t("pages.rahn.yearMonth")}</Label>
            <Input type="month" value={yearMonth} onChange={(e) => setYearMonth(e.target.value)} />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            {salesFields.map(([key, val, setter]) => (
              <div key={key} className="grid gap-2">
                <Label>{settings.sales_definition[key].label}</Label>
                <Input
                  type="number"
                  value={val}
                  onChange={(e) => setter(Number(e.target.value) || 0)}
                />
              </div>
            ))}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="secondary" onClick={() => void calc()}>
              <Calculator className="h-4 w-4" />
              {t("pages.rahn.calculate")}
            </Button>
            <Button type="button" onClick={() => void save(false)}>
              {t("pages.rahn.saveStatement")}
            </Button>
            <Button type="button" variant="outline" onClick={() => void save(true)}>
              <FilePlus2 className="h-4 w-4" />
              {t("pages.rahn.saveAndInvoice")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="space-y-4">
        {reviewAlert ? (
          <Alert>
            <AlertDescription>{reviewAlert}</AlertDescription>
          </Alert>
        ) : null}
        {bill ? (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t("pages.rahn.result")}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg bg-muted/50 p-3">
                <div className="text-xs text-muted-foreground">S_t</div>
                <div className="text-xl font-semibold">{formatNumber(Math.round(bill.S))}</div>
              </div>
              <div className="rounded-lg bg-primary/10 p-3">
                <div className="text-xs text-muted-foreground">V_t = F + p·S</div>
                <div className="text-xl font-semibold">{formatNumber(Math.round(bill.V))}</div>
              </div>
              <div className="rounded-lg border p-3">
                <div className="text-xs text-muted-foreground">F</div>
                <div className="font-medium">{formatNumber(Math.round(bill.F))}</div>
              </div>
              <div className="rounded-lg border p-3">
                <div className="text-xs text-muted-foreground">p·S</div>
                <div className="font-medium">{formatNumber(Math.round(bill.p_share))}</div>
              </div>
              {typeof bill.Pi === "number" ? (
                <div className="rounded-lg border p-3 sm:col-span-2">
                  <div className="text-xs text-muted-foreground">Π_t</div>
                  <div className="font-medium">{formatNumber(Math.round(bill.Pi))}</div>
                </div>
              ) : null}
            </CardContent>
          </Card>
        ) : null}

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("pages.rahn.history")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {history.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t("pages.rahn.noHistory")}</p>
            ) : (
              history.map((row) => (
                <div
                  key={String(row.id)}
                  className="flex items-center justify-between gap-2 rounded-lg border p-3 text-sm"
                >
                  <span>{String(row.year_month)}</span>
                  <span className="font-medium tabular-nums">
                    {formatNumber(Math.round(Number(row.V) || 0))}
                  </span>
                </div>
              ))
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
