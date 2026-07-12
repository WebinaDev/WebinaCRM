import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  getApplicants,
  getJobPostings,
  hireApplicant,
  saveApplicant,
  type Applicant,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { UserPlus, Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

const PIPELINE_STAGES = ["new", "screening", "interview", "offer", "hired", "rejected"] as const

export function RecruitmentPage() {
  const { t, isRtl } = useLocale()
  const [applicants, setApplicants] = useState<Applicant[]>([])
  const [postings, setPostings] = useState<{ id: number; title: string }[]>([])
  const [loading, setLoading] = useState(true)
  const [actingId, setActingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [appRes, postRes] = await Promise.all([getApplicants(), getJobPostings()])
      if (appRes.success && appRes.data?.applicants) {
        setApplicants(appRes.data.applicants)
      } else {
        setError(getAjaxMessage(appRes) ?? t("pages.hrm.loadError"))
      }
      if (postRes.success && postRes.data?.postings) {
        setPostings(postRes.data.postings.map((p) => ({ id: p.id, title: p.title })))
      }
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const handleStageChange = async (a: Applicant, status: string) => {
    setActingId(a.id)
    try {
      const res = await saveApplicant({
        id: a.id,
        job_posting_id: a.job_posting_id,
        first_name: a.first_name,
        last_name: a.last_name,
        email: a.email,
        phone: a.phone,
        status,
      })
      if (res.success) {
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setActingId(null)
    }
  }

  const handleHire = async (a: Applicant) => {
    setActingId(a.id)
    try {
      const res = await hireApplicant(a.id)
      if (res.success) {
        setSuccess(getAjaxMessage(res) ?? t("pages.hrm.recruitment.hired"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setActingId(null)
    }
  }

  const postingTitle = (jobId: number) =>
    postings.find((p) => p.id === jobId)?.title ?? String(jobId)

  return (
    <CrmPageLayout
      title={t("pages.hrm.recruitment.title")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
        <CardContent className="pt-6">
          {loading ? (
            <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
          ) : applicants.length === 0 ? (
            <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.consultations.ایمیل")}</TableHead>
                  <TableHead>{t("pages.hrm.recruitment.posting")}</TableHead>
                  <TableHead>{t("pages.hrm.recruitment.stage")}</TableHead>
                  <TableHead>{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {applicants.map((a) => (
                  <TableRow key={a.id}>
                    <TableCell className="font-medium">
                      {`${a.first_name} ${a.last_name}`.trim()}
                    </TableCell>
                    <TableCell dir="ltr" className="text-start">{a.email}</TableCell>
                    <TableCell>{postingTitle(a.job_posting_id)}</TableCell>
                    <TableCell>
                      <Select
                        value={a.status}
                        onValueChange={(v) => void handleStageChange(a, v)}
                        disabled={actingId === a.id}
                      >
                        <SelectTrigger className="w-[140px]">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {PIPELINE_STAGES.map((s) => (
                            <SelectItem key={s} value={s}>
                              {t(`pages.hrm.recruitment.stages.${s}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </TableCell>
                    <TableCell>
                      {a.status !== "hired" && a.hired_user_id === 0 && (
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => void handleHire(a)}
                          disabled={actingId === a.id}
                        >
                          {actingId === a.id ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <UserPlus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
                          )}
                          {t("pages.hrm.recruitment.hire")}
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </CrmPageLayout>
  )
}
