import { useCallback, useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
import { Card, CardContent } from "@/components/ui/card"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import { getDecrees, printDecreeHtml, saveDecree } from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"

export function PayrollDecreesPage() {
  const { t, isRtl } = useLocale()
  const [rows, setRows] = useState<Record<string, unknown>[]>([])
  const [userId, setUserId] = useState("")
  const [daily, setDaily] = useState("0")
  const [jobCode, setJobCode] = useState("")
  const [effectiveFrom, setEffectiveFrom] = useState(() => new Date().toISOString().slice(0, 10))
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const load = useCallback(async () => {
    const res = await getDecrees()
    if (res.success && res.data?.decrees) {
      setRows(res.data.decrees)
    } else {
      setError(getAjaxMessage(res) ?? t("pages.hrm.loadError"))
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const create = async () => {
    const res = await saveDecree({
      user_id: Number(userId),
      decree_type: "hire",
      status: "issued",
      effective_from: effectiveFrom,
      daily_wage: Number(daily) || 0,
      job_code: jobCode,
    })
    if (res.success) {
      setSuccess(t("common.saved"))
      setUserId("")
      void load()
    } else {
      setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
    }
  }

  const print = async (id: number) => {
    const res = await printDecreeHtml(id)
    if (res.success && res.data?.html) {
      const w = window.open("", "_blank")
      if (w) {
        w.document.write(res.data.html)
        w.document.close()
      }
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.payroll.decrees")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      <Card className="mb-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
        <CardContent className="pt-6 flex flex-wrap gap-2">
          <Input className="max-w-[8rem]" value={userId} onChange={(e) => setUserId(e.target.value)} placeholder={t("pages.hrm.payroll.userId")} />
          <Input className="max-w-[8rem]" value={daily} onChange={(e) => setDaily(e.target.value)} placeholder={t("pages.hrm.payroll.dailyWage")} />
          <Input className="max-w-[8rem]" value={jobCode} onChange={(e) => setJobCode(e.target.value)} placeholder={t("pages.hrm.payroll.jobCode")} />
          <DatePicker className="max-w-[12rem]" value={effectiveFrom} onChange={setEffectiveFrom} />
          <Button onClick={() => void create()} disabled={!userId}>
            {t("pages.hrm.payroll.issueDecree")}
          </Button>
        </CardContent>
      </Card>
      <div className="space-y-2">
        {rows.map((d) => (
          <div key={String(d.id)} className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
            <span>
              {String(d.decree_no ?? d.id)} — user {String(d.user_id)} — {String(d.status)}
            </span>
            <Button size="sm" variant="outline" onClick={() => void print(Number(d.id))}>
              {t("pages.hrm.payroll.print")}
            </Button>
          </div>
        ))}
      </div>
    </CrmPageLayout>
  )
}
