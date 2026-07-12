import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useLocale } from "@/hooks/use-locale"
import {
  getCourses,
  getSessions,
  getEnrollments,
  saveCourse,
  type TrainingCourse,
  type TrainingSession,
  type Enrollment,
} from "@/api/hrm"
import { getAjaxMessage } from "@/api/client"
import { Loader2 } from "lucide-react"

export function TrainingPage() {
  const { t, isRtl } = useLocale()
  const [tab, setTab] = useState("courses")
  const [courses, setCourses] = useState<TrainingCourse[]>([])
  const [sessions, setSessions] = useState<TrainingSession[]>([])
  const [enrollments, setEnrollments] = useState<Enrollment[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [courseTitle, setCourseTitle] = useState("")
  const [submitting, setSubmitting] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [cRes, sRes, eRes] = await Promise.all([
        getCourses(),
        getSessions(),
        getEnrollments(),
      ])
      if (cRes.success && cRes.data?.courses) setCourses(cRes.data.courses)
      if (sRes.success && sRes.data?.sessions) setSessions(sRes.data.sessions)
      if (eRes.success && eRes.data?.enrollments) setEnrollments(eRes.data.enrollments)
    } catch {
      setError(t("pages.hrm.loadError"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    void load()
  }, [load])

  const handleAddCourse = async () => {
    if (!courseTitle.trim()) return
    setSubmitting(true)
    try {
      const res = await saveCourse({ title: courseTitle.trim(), status: "draft" })
      if (res.success) {
        setCourseTitle("")
        setSuccess(getAjaxMessage(res) ?? t("common.saved"))
        void load()
      } else {
        setError(getAjaxMessage(res) ?? t("common.errors.saveFailed"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  const courseTitleById = (id: number) => courses.find((c) => c.id === id)?.title ?? String(id)

  return (
    <CrmPageLayout
      title={t("pages.hrm.training.title")}
      error={error}
      success={success}
      onDismissError={() => setError(null)}
      onDismissSuccess={() => setSuccess(null)}
    >
      <Tabs value={tab} onValueChange={setTab} dir={isRtl ? "rtl" : "ltr"} className="text-start">
        <TabsList className="text-start">
          <TabsTrigger value="courses">{t("pages.hrm.training.courses")}</TabsTrigger>
          <TabsTrigger value="sessions">{t("pages.hrm.training.sessions")}</TabsTrigger>
          <TabsTrigger value="enrollments">{t("pages.hrm.training.enrollments")}</TabsTrigger>
        </TabsList>
        <TabsContent value="courses" className="mt-4">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6 space-y-4">
              <div className="flex gap-2 items-center">
                <Input
                  placeholder={t("pages.hrm.training.courseTitle")}
                  value={courseTitle}
                  onChange={(e) => setCourseTitle(e.target.value)}
                  className="max-w-sm"
                />
                <Button onClick={() => void handleAddCourse()} disabled={submitting}>
                  {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : t("common.add")}
                </Button>
              </div>
              {loading ? (
                <div className="py-8 text-muted-foreground">{t("common.loading")}</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("common.name")}</TableHead>
                      <TableHead>{t("pages.hrm.training.hours")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {courses.map((c) => (
                      <TableRow key={c.id}>
                        <TableCell>{c.title}</TableCell>
                        <TableCell>{c.duration_hours}</TableCell>
                        <TableCell>{c.status}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="sessions" className="mt-4">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6">
              {sessions.length === 0 ? (
                <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.hrm.training.courseTitle")}</TableHead>
                      <TableHead>{t("common.date")}</TableHead>
                      <TableHead>{t("pages.hrm.training.location")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sessions.map((s) => (
                      <TableRow key={s.id}>
                        <TableCell>{courseTitleById(s.course_id)}</TableCell>
                        <TableCell dir="ltr" className="text-start">{s.session_date}</TableCell>
                        <TableCell>{s.location || "—"}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="enrollments" className="mt-4">
          <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
            <CardContent className="pt-6">
              {enrollments.length === 0 ? (
                <div className="py-8 text-muted-foreground">{t("pages.hrm.empty")}</div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t("pages.hrm.training.courseTitle")}</TableHead>
                      <TableHead>{t("common.name")}</TableHead>
                      <TableHead>{t("common.status")}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {enrollments.map((e) => (
                      <TableRow key={e.id}>
                        <TableCell>{courseTitleById(e.course_id)}</TableCell>
                        <TableCell>{e.user_name ?? e.user_id}</TableCell>
                        <TableCell>{e.status}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </CrmPageLayout>
  )
}
