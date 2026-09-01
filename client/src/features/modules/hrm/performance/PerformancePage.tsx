import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { DatePicker } from "@/components/ui/date-picker"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  getReviewCycles,
  getReviews,
  saveReviewCycle,
  type PerformanceReview,
  type ReviewCycle,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { Plus, Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

export function PerformancePage() {
  const { t, isRtl, formatDate } = useLocale()
  const [tab, setTab] = useState("cycles")
  const [cycles, setCycles] = useState<ReviewCycle[]>([])
  const [reviews, setReviews] = useState<PerformanceReview[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [cycleDialog, setCycleDialog] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [cycleForm, setCycleForm] = useState({
    name: "",
    year: new Date().getFullYear(),
    start_date: "",
    end_date: "",
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [cRes, rRes] = await Promise.all([getReviewCycles(), getReviews()])
      if (cRes.success && cRes.data?.cycles) setCycles(cRes.data.cycles)
      if (rRes.success && rRes.data?.reviews) setReviews(rRes.data.reviews)
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const handleSaveCycle = async () => {
    if (!cycleForm.name || !cycleForm.start_date || !cycleForm.end_date) return
    setSubmitting(true)
    try {
      const res = await saveReviewCycle({
        name: cycleForm.name.trim(),
        year: cycleForm.year,
        start_date: cycleForm.start_date,
        end_date: cycleForm.end_date,
        status: "draft",
      })
      if (res.success) {
        setCycleDialog(false)
        setSuccess(getAjaxMessage(res) ?? t("common.saved"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.hrm.performance.title")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
      actions={
        tab === "cycles" ? (
          <Button size="sm" onClick={() => setCycleDialog(true)}>
            <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
            {t("pages.hrm.performance.newCycle")}
          </Button>
        ) : null
      }
    >
      <Tabs value={tab} onValueChange={setTab} dir={isRtl ? "rtl" : "ltr"} className="text-start">
        <TabsList className="text-start">
          <TabsTrigger value="cycles">{t("pages.hrm.performance.cycles")}</TabsTrigger>
          <TabsTrigger value="reviews">{t("pages.hrm.performance.reviews")}</TabsTrigger>
        </TabsList>
        <TabsContent value="cycles" className="mt-4">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6">
              {loading ? (
                <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
              ) : cycles.length === 0 ? (
                <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("common.name")}</TableHead>
                      <TableHead>{t("pages.hrm.performance.year")}</TableHead>
                      <TableHead>{t("common.date")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {cycles.map((c) => (
                      <TableRow key={c.id}>
                        <TableCell>{c.name}</TableCell>
                        <TableCell>{c.year}</TableCell>
                        <TableCell className="text-start">
                          {formatDate(c.start_date)} — {formatDate(c.end_date)}
                        </TableCell>
                        <TableCell>{c.status}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="reviews" className="mt-4">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6">
              {reviews.length === 0 ? (
                <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("common.name")}</TableHead>
                      <TableHead>{t("pages.hrm.performance.reviewer")}</TableHead>
                      <TableHead>{t("pages.hrm.performance.score")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {reviews.map((r) => (
                      <TableRow key={r.id}>
                        <TableCell>{r.user_name}</TableCell>
                        <TableCell>{r.reviewer_name}</TableCell>
                        <TableCell>{r.overall_score}</TableCell>
                        <TableCell>{r.status}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={cycleDialog} onOpenChange={setCycleDialog}>
        <DialogContent dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{t("pages.hrm.performance.newCycle")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <Input
              value={cycleForm.name}
              onChange={(e) => setCycleForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("common.name")}
            />
            <Input
              type="number"
              value={cycleForm.year}
              onChange={(e) => setCycleForm((f) => ({ ...f, year: parseInt(e.target.value, 10) || f.year }))}
            />
            <DatePicker value={cycleForm.start_date} onChange={(v) => setCycleForm((f) => ({ ...f, start_date: v }))} />
            <DatePicker value={cycleForm.end_date} onChange={(v) => setCycleForm((f) => ({ ...f, end_date: v }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCycleDialog(false)}>{t("common.cancel")}</Button>
            <Button onClick={() => void handleSaveCycle()} disabled={submitting}>
              {submitting && <Loader2 className="h-4 w-4 animate-spin me-2" />}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  )
}
