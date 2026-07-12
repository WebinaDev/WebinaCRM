import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Link, useSearchParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
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
import { SELECT_NONE_VALUE } from "@/lib/constants"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  getStaff,
  saveStaff,
  deleteStaff,
  saveOrgPosition,
  deleteOrgPosition,
  type StaffUser,
  type GetStaffResponse,
} from "@/api/hrm"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import { Plus, Loader2, Pencil, Trash2, MessageSquare, Eye } from "lucide-react"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { MessagingDialog } from "@/features/modules/crm/components/MessagingDialog"
const STAFF_ROLE_SLUGS = ["system_manager", "finance_manager", "team_member", "administrator"] as const

export function StaffPage() {
  const { t, isRtl } = useLocale()
  const [searchParams, setSearchParams] = useSearchParams()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [data, setData] = useState<GetStaffResponse | null>(null)
  const [section, setSection] = useState<"users" | "org">("users")
  const [success, setSuccess] = useState<string | null>(null)
  const [messagingOpen, setMessagingOpen] = useState(false)
  const [messagingUser, setMessagingUser] = useState<StaffUser | null>(null)
  const [messagingChannel, setMessagingChannel] = useState<"sms" | "bale">("sms")
  const [orgDeleteId, setOrgDeleteId] = useState<number | null>(null)
  const [orgName, setOrgName] = useState("")
  const [orgParent, setOrgParent] = useState(0)
  const [orgEditingId, setOrgEditingId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const [formOpen, setFormOpen] = useState(false)
  const [editUser, setEditUser] = useState<StaffUser | null>(null)
  const [deleteUserId, setDeleteUserId] = useState<number | null>(null)
  const [deleteNonce, setDeleteNonce] = useState<string>("")
  const [submitting, setSubmitting] = useState(false)
  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    email: "",
    webino_mobile_phone: "",
    role: "team_member",
    password: "",
    department: 0,
    job_title: 0,
  })

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getStaff({
        search: search || undefined,
        paged: currentPage,
        per_page: 25,
      })
      if (res.success && res.data) {
        setData(res.data)
        setTotalPages(res.data.total_pages ?? 1)
      } else {
        setError(res.message ?? t("pages.staff.خطا_در_بارگذاری_کارکنان"))
      }
    } catch {
      setError(t("pages.staff.خطا_در_بارگذاری_کارکنان"))
    } finally {
      setLoading(false)
    }
  }, [search, currentPage, setTotalPages])

  useEffect(() => {
    const timer = setTimeout(() => void load(), search ? 300 : 0)
    return () => clearTimeout(timer)
  }, [load, search])

  useEffect(() => {
    resetPage()
  }, [search, resetPage])

  useEffect(() => {
    const userId = searchParams.get("user")
    if (!userId || !data?.users?.length) return
    const id = parseInt(userId, 10)
    if (Number.isNaN(id)) return
    const u = data.users.find((x) => x.id === id)
    if (u) openEdit(u)
    setSearchParams({}, { replace: true })
  }, [data?.users, searchParams])

  function openEdit(u: StaffUser) {
    setEditUser(u)
    setForm({
      first_name: u.first_name ?? "",
      last_name: u.last_name ?? "",
      email: u.email ?? "",
      webino_mobile_phone: u.phone ?? "",
      role: u.role_slug ?? "team_member",
      password: "",
      department: u.department_id ?? 0,
      job_title: u.job_title_id ?? 0,
    })
    setFormOpen(true)
  }

  const openCreate = () => {
    setEditUser(null)
    setForm({
      first_name: "",
      last_name: "",
      email: "",
      webino_mobile_phone: "",
      role: "team_member",
      password: "",
      department: 0,
      job_title: 0,
    })
    setFormOpen(true)
  }

  const staffRoles = STAFF_ROLE_SLUGS.map((slug) => {
    const fromApi = data?.roles?.find((r) => r.slug === slug)
    return { slug, name: fromApi?.name ?? slug }
  })

  const handleOrgSave = async () => {
    if (!orgName.trim()) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await saveOrgPosition({
        term_id: orgEditingId ?? undefined,
        name: orgName.trim(),
        parent_id: orgParent || undefined,
      })
      if (res.success) {
        setOrgName("")
        setOrgParent(0)
        setOrgEditingId(null)
        load()
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleOrgDelete = async (termId: number) => {
    setSubmitting(true)
    try {
      const res = await deleteOrgPosition(termId)
      if (res.success) {
        setOrgDeleteId(null)
        load()
      } else setError(res.message ?? t("common.errors.deleteFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const openMessaging = (u: StaffUser, channel: "sms" | "bale") => {
    if (!u.phone?.trim()) {
      setError(channel === "sms" ? t("pages.customers.بدون_موبایل_پیامک") : t("pages.customers.بدون_موبایل_بله"))
      return
    }
    setError(null)
    setMessagingChannel(channel)
    setMessagingUser(u)
    setMessagingOpen(true)
  }

  const handleSubmit = useCallback(async () => {
    if (!form.last_name.trim() || !form.email.trim() || !form.role) {
      setError(t("pages.customers.نام_خانوادگی،_ایمیل_و_نقش_الزامی_هستند"))
      return
    }
    if (!editUser && !form.password) {
      setError(t("pages.customers.برای_کاربر_جدید،_رمز_عبور_الزامی_است"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await saveStaff({
        user_id: editUser?.id,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim(),
        webino_mobile_phone: form.webino_mobile_phone.trim() || undefined,
        role: form.role,
        password: form.password || undefined,
        department: form.department,
        job_title: form.job_title,
      })
      if (res.success) {
        setFormOpen(false)
        load()
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }, [form, editUser, load])

  const handleDelete = useCallback(async () => {
    if (deleteUserId == null || !deleteNonce) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await deleteStaff(deleteUserId, deleteNonce)
      if (res.success) {
        setDeleteUserId(null)
        setDeleteNonce("")
        load()
      } else {
        setError(res.message ?? t("common.errors.deleteFailed"))
      }
    } catch {
      setError(t("common.errors.deleteFailed"))
    } finally {
      setSubmitting(false)
    }
  }, [deleteUserId, deleteNonce, load])

  const users = data?.users ?? []
  const departments = data?.departments ?? []
  const jobTitles = data?.job_titles ?? []
  const jobTitlesForDept = form.department
    ? jobTitles.filter((j) => j.parent === form.department)
    : []

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.staff.کارکنان")}
        isRtl={isRtl}
        actions={
          section === "users" ? (
            <Button size="sm" onClick={openCreate}>
              <Plus className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.staff.افزودن_کارمند")}
            </Button>
          ) : null
        }
      />

      <PmAlerts error={error} success={success} />

      <Tabs value={section} onValueChange={(v) => setSection(v as "users" | "org")} dir={isRtl ? "rtl" : "ltr"} className="text-start">
        <TabsList className="text-start">
          <TabsTrigger value="users">{t("pages.staff.کارکنان")}</TabsTrigger>
          <TabsTrigger value="org">{t("pages.staff.ساختار_سازمانی")}</TabsTrigger>
        </TabsList>

        <TabsContent value="org" className="mt-4">
        <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
          <CardContent className="pt-6 space-y-4 text-start">
            <p className="text-sm text-muted-foreground">{t("pages.staff.ساختار_توضیح")}</p>
            <div className="flex flex-wrap gap-2 items-end">
              <Input
                placeholder={t("pages.staff.نام_دپارتمان_یا_سمت")}
                value={orgName}
                onChange={(e) => setOrgName(e.target.value)}
                className="max-w-xs"
              />
              <Select
                value={orgParent ? String(orgParent) : SELECT_NONE_VALUE}
                onValueChange={(v) => setOrgParent(v === SELECT_NONE_VALUE ? 0 : parseInt(v, 10))}
              >
                <SelectTrigger className="w-[180px]">
                  <SelectValue placeholder={t("pages.staff.دپارتمان_والد")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SELECT_NONE_VALUE}>{t("pages.staff.دپارتمان_اصلی")}</SelectItem>
                  {departments.map((d) => (
                    <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button onClick={handleOrgSave} disabled={submitting}>
                {orgEditingId ? t("common.save") : t("common.add")}
              </Button>
              {orgEditingId && (
                <Button variant="outline" onClick={() => { setOrgEditingId(null); setOrgName(""); setOrgParent(0) }}>
                  {t("common.cancel")}
                </Button>
              )}
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.staff.نوع")}</TableHead>
                  <TableHead>{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {departments.map((d) => (
                  <TableRow key={d.id}>
                    <TableCell>{d.name}</TableCell>
                    <TableCell>{t("pages.staff.دپارتمان")}</TableCell>
                    <TableCell>
                      <div className={cn("flex gap-1", isRtl && "flex-row-reverse")}>
                        <Button size="sm" variant="outline" onClick={() => { setOrgEditingId(d.id); setOrgName(d.name); setOrgParent(0) }}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button size="sm" variant="outline" className="text-destructive" onClick={() => setOrgDeleteId(d.id)}>
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
                {jobTitles.map((j) => (
                  <TableRow key={j.id}>
                    <TableCell>{j.name}</TableCell>
                    <TableCell>{t("pages.staff.عنوان_شغلی")}</TableCell>
                    <TableCell>
                      <div className={cn("flex gap-1", isRtl && "flex-row-reverse")}>
                        <Button size="sm" variant="outline" onClick={() => { setOrgEditingId(j.id); setOrgName(j.name); setOrgParent(j.parent) }}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button size="sm" variant="outline" className="text-destructive" onClick={() => setOrgDeleteId(j.id)}>
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
        </TabsContent>

        <TabsContent value="users" className="mt-4">
      <Card className="text-start" dir={isRtl ? "rtl" : "ltr"}>
        <CardContent className="pt-6 text-start">
          <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={() => void load()} isRtl={isRtl}>
            <Input
              placeholder={t("pages.staff.جستجو_نام،_ایمیل،_تلفن")}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="max-w-sm"
            />
          </PmFilterBar>
          {loading ? (

            <TableListSkeleton rows={8} columns={6} withAvatarColumn />

          ) : users.length === 0 ? (
            <div className="py-12 text-start text-muted-foreground">{t("pages.staff.هیچ_کارمندی_یافت_نشد")}</div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("common.name")}</TableHead>
                  <TableHead>{t("pages.consultations.ایمیل")}</TableHead>
                  <TableHead>{t("pages.staff.دپارتمان")}</TableHead>
                  <TableHead>{t("pages.staff.عنوان_شغلی")}</TableHead>
                  <TableHead>{t("pages.customers.نقش")}</TableHead>
                  <TableHead className="w-[120px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {users.map((u) => (
                  <TableRow key={u.id}>
                    <TableCell className="font-medium">
                      <Link to={`/hrm/staff/${u.id}`} className="hover:underline">
                        {(u.first_name || u.last_name)?.trim()
                          ? `${u.first_name ?? ""} ${u.last_name ?? ""}`.trim()
                          : u.display_name}
                      </Link>
                    </TableCell>
                    <TableCell dir="ltr" className="text-start">{u.email}</TableCell>
                    <TableCell>{u.department_name ?? "—"}</TableCell>
                    <TableCell>{u.job_title_name ?? "—"}</TableCell>
                    <TableCell>{u.role_name}</TableCell>
                    <TableCell>
                      <div className={cn("flex gap-1", isRtl && "flex-row-reverse")}>
                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openMessaging(u, "sms")} title={t("pages.customers.send_sms")}>
                          <MessageSquare className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openMessaging(u, "bale")} title={t("pages.customers.send_bale")}>
                          <MessageSquare className="h-4 w-4 text-primary" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-8 w-8" asChild>
                          <Link to={`/hrm/staff/${u.id}`} title={t("pages.hrm.profile")}>
                            <Eye className="h-4 w-4" />
                          </Link>
                        </Button>
                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => openEdit(u)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        {u.can_delete && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-destructive hover:text-destructive"
                            onClick={() => {
                              setDeleteUserId(u.id)
                              setDeleteNonce(u.delete_nonce)
                            }}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
      <PmPagination
        page={currentPage}
        totalPages={totalPages}
        onPageChange={setCurrentPage}
        prevLabel={t("common.prevPage")}
        nextLabel={t("common.nextPage")}
        isRtl={isRtl}
      />
        </TabsContent>
      </Tabs>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent className="sm:max-w-md" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>{editUser ? t("pages.staff.ویرایش_کارمند") : t("pages.staff.افزودن_کارمند")}</DialogTitle>
          </DialogHeader>
          <form
            onSubmit={(e) => {
              e.preventDefault()
              handleSubmit()
            }}
            className="space-y-4 py-4"
          >
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("common.name")}</label>
                <Input
                  value={form.first_name}
                  onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))}
                  placeholder={t("common.name")}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.customers.نام_خانوادگی")}</label>
                <Input
                  value={form.last_name}
                  onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))}
                  placeholder={t("pages.staff.نام_خانوادگی")}
                />
              </div>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.customers.ایمیل")}</label>
              <Input
                type="email"
                dir="ltr"
                value={form.email}
                onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                placeholder="email@example.com"
                disabled={!!editUser}
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.accounting.persons.موبایل")}</label>
              <Input
                dir="ltr"
                value={form.webino_mobile_phone}
                onChange={(e) => setForm((f) => ({ ...f, webino_mobile_phone: e.target.value }))}
                placeholder="09..."
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.staff.نقش")}</label>
              <Select value={form.role} onValueChange={(v) => setForm((f) => ({ ...f, role: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {staffRoles.map((r) => (
                    <SelectItem key={r.slug} value={r.slug}>{r.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">
                {t("pages.staff.رمز_عبور_برچسب")} {editUser ? t("common.passwordOptionalHint") : "*"}
              </label>
              <Input
                type="password"
                value={form.password}
                onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                placeholder={editUser ? t("common.passwordLeaveEmpty") : t("common.password")}
              />
            </div>
            {departments.length > 0 && (
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.staff.دپارتمان")}</label>
                <Select
                  value={form.department ? String(form.department) : SELECT_NONE_VALUE}
                  onValueChange={(v) =>
                    setForm((f) => ({
                      ...f,
                      department: v === SELECT_NONE_VALUE ? 0 : parseInt(v, 10) || 0,
                      job_title: 0,
                    }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t("pages.staff.انتخاب_دپارتمان")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SELECT_NONE_VALUE}>—</SelectItem>
                    {departments.map((d) => (
                      <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
            {jobTitlesForDept.length > 0 && (
              <div className="space-y-2">
                <label className="text-sm font-medium">{t("pages.staff.عنوان_شغلی")}</label>
                <Select
                  value={form.job_title ? String(form.job_title) : SELECT_NONE_VALUE}
                  onValueChange={(v) =>
                    setForm((f) => ({
                      ...f,
                      job_title: v === SELECT_NONE_VALUE ? 0 : parseInt(v, 10) || 0,
                    }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t("pages.staff.انتخاب_عنوان_شغلی")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={SELECT_NONE_VALUE}>—</SelectItem>
                    {jobTitlesForDept.map((j) => (
                      <SelectItem key={j.id} value={String(j.id)}>{j.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>{t("common.cancel")}</Button>
            <Button type="submit" disabled={submitting}>
              {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
              {editUser ? t("common.save") : t("pages.staff.ایجاد_کارمند")}
            </Button>
          </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {messagingUser && (
        <MessagingDialog
          mode="single"
          open={messagingOpen}
          onOpenChange={setMessagingOpen}
          user={messagingUser}
          channel={messagingChannel}
          onSuccess={(msg) => setSuccess(msg)}
          onError={setError}
        />
      )}

      <PmConfirmDialog
        open={deleteUserId !== null}
        onOpenChange={(open) => !open && setDeleteUserId(null)}
        title={t("pages.staff.حذف_کارمند")}
        description={t("pages.staff.confirm.deleteUser")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        onConfirm={handleDelete}
      />

      <PmConfirmDialog
        open={orgDeleteId !== null}
        onOpenChange={(open) => !open && setOrgDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.staff.confirm.deleteOrg")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        onConfirm={async () => {
          if (orgDeleteId != null) await handleOrgDelete(orgDeleteId)
        }}
      />
    </div>
  )
}
