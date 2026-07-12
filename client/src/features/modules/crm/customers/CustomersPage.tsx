import { TableListSkeleton } from "@/components/TableListSkeleton"
import { StatCardsSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { useSearchParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Badge } from "@/components/ui/badge"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
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
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { SELECT_ALL_VALUE } from "@/lib/constants"
import {
  getUsers,
  manageUser,
  deleteUser,
  exportCustomers,
  importCustomers,
  type CustomerUser,
} from "@/api/customers"
import { ImportCsvButton } from "../components/ImportCsvButton"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import { Plus, Loader2, Pencil, Trash2, MessageSquare, Users, UserCircle, UserCheck, Eye, Download } from "lucide-react"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { MessagingDialog } from "../components/MessagingDialog"
import { Customer360Sheet } from "../components/Customer360Sheet"
import { STAFF_ROLES, EMAIL_RE } from "../constants"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"

export function CustomersPage() {
  const { t, isRtl } = useLocale()
  const [searchParams, setSearchParams] = useSearchParams()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [users, setUsers] = useState<CustomerUser[]>([])
  const [stats, setStats] = useState({ total: 0, customers: 0, staff: 0 })
  const [roles, setRoles] = useState<{ slug: string; name: string }[]>([])
  const [departments, setDepartments] = useState<{ id: number; name: string }[]>([])
  const [jobTitles, setJobTitles] = useState<{ id: number; name: string; parent: number }[]>([])
  const [success, setSuccess] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState("")
  const [roleFilter, setRoleFilter] = useState("")
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editingUser, setEditingUser] = useState<CustomerUser | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<CustomerUser | null>(null)
  const [customer360Id, setCustomer360Id] = useState<number | null>(null)
  const [messagingOpen, setMessagingOpen] = useState(false)
  const [messagingMode, setMessagingMode] = useState<"single" | "bulk">("single")
  const [messagingChannel, setMessagingChannel] = useState<"sms" | "bale">("sms")
  const [messagingUser, setMessagingUser] = useState<CustomerUser | null>(null)
  const [form, setForm] = useState({
    first_name: "",
    last_name: "",
    email: "",
    webino_mobile_phone: "",
    role: "customer",
    password: "",
    department: 0,
    job_title: 0,
  })

  const isStaffRole = STAFF_ROLES.has(form.role)
  const filteredJobTitles = jobTitles.filter(
    (jt) => !form.department || jt.parent === form.department
  )

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getUsers({
        search: search || undefined,
        role: roleFilter || undefined,
        paged: currentPage,
        per_page: 25,
      })
      if (res.success && res.data) {
        const d = res.data
        setUsers(d.users ?? [])
        setStats(d.stats ?? { total: 0, customers: 0, staff: 0 })
        setRoles(d.roles ?? [])
        setDepartments(d.departments ?? [])
        setJobTitles(d.job_titles ?? [])
        setTotalPages(d.total_pages ?? 1)
      } else {
        setError(res.message ?? t("pages.customers.خطا_در_بارگذاری_کاربران"))
      }
    } catch {
      setError(t("pages.customers.خطا_در_بارگذاری_کاربران"))
    } finally {
      setLoading(false)
    }
  }, [search, roleFilter, currentPage, setTotalPages])

  useEffect(() => {
    const timer = setTimeout(load, search || roleFilter ? 300 : 0)
    return () => clearTimeout(timer)
  }, [load, search, roleFilter])

  useEffect(() => {
    resetPage()
  }, [search, roleFilter, resetPage])

  useEffect(() => {
    const editId = searchParams.get("edit")
    if (!editId || users.length === 0) return
    const id = parseInt(editId, 10)
    if (Number.isNaN(id)) return
    const u = users.find((x) => x.id === id)
    if (u) openEdit(u)
    setSearchParams({}, { replace: true })
  }, [users, searchParams])

  const openNew = () => {
    setEditingUser(null)
    setForm({
      first_name: "",
      last_name: "",
      email: "",
      webino_mobile_phone: "",
      role: "customer",
      password: "",
      department: 0,
      job_title: 0,
    })
    setError(null)
    setDialogOpen(true)
  }

  const openEdit = (u: CustomerUser) => {
    setEditingUser(u)
    setForm({
      first_name: u.first_name ?? "",
      last_name: u.last_name ?? "",
      email: u.email ?? "",
      webino_mobile_phone: u.phone ?? "",
      role: u.role_slug || "customer",
      password: "",
      department: u.department_id ?? 0,
      job_title: u.job_title_id ?? 0,
    })
    setError(null)
    setDialogOpen(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.last_name.trim() || !form.email.trim() || !form.role) {
      setError(t("pages.customers.نام_خانوادگی،_ایمیل_و_نقش_الزامی_هستند"))
      return
    }
    if (!EMAIL_RE.test(form.email.trim())) {
      setError(t("pages.customers.ایمیل_نامعتبر"))
      return
    }
    if (!editingUser && !form.password.trim()) {
      setError(t("pages.customers.برای_کاربر_جدید،_رمز_عبور_الزامی_است"))
      return
    }
    setSubmitting(true)
    setError(null)
    try {
      const res = await manageUser({
        user_id: editingUser?.id,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim(),
        webino_mobile_phone: form.webino_mobile_phone.trim() || undefined,
        role: form.role,
        password: form.password.trim() || undefined,
        ...(isStaffRole
          ? {
              department: form.department || undefined,
              job_title: form.job_title || undefined,
            }
          : {}),
      })
      if (res.success) {
        setDialogOpen(false)
        setSuccess(res.message ?? t("common.save"))
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

  const handleDelete = async (u: CustomerUser) => {
    if (!u.can_delete || !u.delete_nonce) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await deleteUser(u.id, u.delete_nonce)
      if (res.success) {
        setDeleteTarget(null)
        load()
      } else setError(res.message ?? t("common.errors.deleteFailed"))
    } catch {
      setError(t("common.errors.deleteFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const openMessaging = (u: CustomerUser, channel: "sms" | "bale") => {
    if (!u.phone?.trim()) {
      setError(channel === "sms" ? t("pages.customers.بدون_موبایل_پیامک") : t("pages.customers.بدون_موبایل_بله"))
      return
    }
    setError(null)
    setMessagingMode("single")
    setMessagingChannel(channel)
    setMessagingUser(u)
    setMessagingOpen(true)
  }

  const handleExport = async () => {
    setSubmitting(true)
    try {
      const res = await exportCustomers()
      if (res.success && res.data?.file_url) {
        window.open(res.data.file_url, "_blank")
        setSuccess(res.data.message ?? t("pages.customers.export_done"))
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleImport = async (file: File) => {
    setError(null)
    const res = await importCustomers(file)
    if (res.success && res.data) {
      const errCount = res.data.errors?.length ?? 0
      const base = res.data.message ?? t("pages.customers.import_done")
      setSuccess(errCount > 0 ? `${base} (${t("pages.customers.import_errors", { count: errCount })})` : base)
      load()
    } else {
      setError(res.message ?? t("common.errors.saveFailed"))
    }
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader
        title={t("pages.customers.مدیریت_مشتریان_و_کاربران")}
        isRtl={isRtl}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={handleExport} disabled={submitting}>
              <Download className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
              {t("pages.customers.export_csv")}
            </Button>
            <ImportCsvButton
              label={t("pages.customers.import_csv")}
              disabled={submitting}
              onImport={handleImport}
            />
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setMessagingMode("bulk")
                setMessagingOpen(true)
              }}
            >
              {t("pages.customers.ارسال_پیام_انبوه_بله")}
            </Button>
            <Button size="sm" onClick={openNew}>
              <Plus className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
              {t("pages.customers.افزودن_کاربر")}
            </Button>
          </div>
        }
      />

      {loading ? (
        <StatCardsSkeleton count={3} />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-4">
                  <div className="rounded-lg bg-primary/10 p-3">
                    <Users className="h-6 w-6 text-primary" />
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">{t("pages.customers.کل_کاربران")}</p>
                    <p className="text-2xl font-semibold">{stats.total}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-4">
                  <div className="rounded-lg bg-success/10 p-3">
                    <UserCircle className="h-6 w-6 text-success" />
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">{t("pages.customers.مشتریان")}</p>
                    <p className="text-2xl font-semibold">{stats.customers}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center gap-4">
                  <div className="rounded-lg bg-amber-500/10 p-3">
                    <UserCheck className="h-6 w-6 text-amber-600" />
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">{t("pages.customers.کارمندان")}</p>
                    <p className="text-2xl font-semibold">{stats.staff}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
          <p className="text-xs text-muted-foreground">{t("pages.customers.آمار_کل_سیستم")}</p>
        </>
      )}

      <PmAlerts error={error} success={success} />

      <PmFilterBar applyLabel={t("pages.reports.اعمال_فیلتر")} onApply={load} isRtl={isRtl}>
        <Input
          dir="ltr"
          placeholder={t("pages.customers.نام،_ایمیل،_موبایل")}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-[220px]"
        />
        <Select
          value={roleFilter === "" ? SELECT_ALL_VALUE : roleFilter}
          onValueChange={(v) => setRoleFilter(v === SELECT_ALL_VALUE ? "" : v)}
        >
          <SelectTrigger className="w-[180px]">
            <SelectValue placeholder={t("pages.customers.همه_نقشها")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SELECT_ALL_VALUE}>{t("pages.customers.همه_نقشها")}</SelectItem>
            {roles.map((r) => (
              <SelectItem key={r.slug} value={r.slug}>{r.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </PmFilterBar>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <Card>
        <CardContent className="p-0">
          {loading ? (

            <TableListSkeleton rows={8} columns={6} withAvatarColumn />

          ) : users.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">
              {t("common.noResults")}
            </div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.customers.کاربر")}</TableHead>
                  <TableHead>{t("pages.consultations.ایمیل")}</TableHead>
                  <TableHead>{t("pages.accounting.persons.موبایل")}</TableHead>
                  <TableHead>{t("pages.customers.نقش")}</TableHead>
                  <TableHead>{t("pages.customers.تاریخ_عضویت")}</TableHead>
                  <TableHead>{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {users.map((u) => (
                  <TableRow key={u.id}>
                    <TableCell>
                      <div className="flex items-center gap-3">
                        <Avatar className="h-10 w-10">
                          <AvatarImage src={u.avatar_url} alt="" />
                          <AvatarFallback>{u.display_name?.slice(0, 2) ?? "?"}</AvatarFallback>
                        </Avatar>
                        <div>
                          <div className="font-medium">{u.display_name}</div>
                          <div className="text-xs text-muted-foreground">ID: {u.id}</div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell dir="ltr" className="font-mono text-sm">
                      {u.email}
                    </TableCell>
                    <TableCell dir="ltr">{u.phone || "—"}</TableCell>
                    <TableCell>
                      <Badge variant="secondary">{u.role_name}</Badge>
                    </TableCell>
                    <TableCell>{u.registered_jalali}</TableCell>
                    <TableCell>
                      <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => setCustomer360Id(u.id)} title={t("pages.customers.customer_360")}>
                          <Eye className="h-4 w-4" />
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => openEdit(u)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => openMessaging(u, "sms")}
                          disabled={!u.phone?.trim()}
                          title={
                            u.phone?.trim()
                              ? t("pages.customers.ارسال_پیامک")
                              : t("pages.customers.بدون_موبایل_پیامک")
                          }
                        >
                          <MessageSquare className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => openMessaging(u, "bale")}
                          disabled={!u.phone?.trim()}
                          title={
                            u.phone?.trim()
                              ? t("pages.customers.ارسال_پیام_بله")
                              : t("pages.customers.بدون_موبایل_بله")
                          }
                        >
                          {t("pages.customers.بله")}
                        </Button>
                        {u.can_delete && (
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleteTarget(u)}
                            disabled={submitting}
                            title={t("common.delete")}
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

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {editingUser ? t("pages.customers.ویرایش_کاربر") : t("pages.customers.افزودن_کاربر")}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="first_name">{t("common.name")}</Label>
                <Input
                  id="first_name"
                  value={form.first_name}
                  onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="last_name">{t("pages.customers.نام_خانوادگی")}</Label>
                <Input
                  id="last_name"
                  value={form.last_name}
                  onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="email">{t("pages.customers.ایمیل")}</Label>
                <Input
                  id="email"
                  type="email"
                  dir="ltr"
                  value={form.email}
                  onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="phone">{t("pages.customers.شماره_موبایل")}</Label>
                <Input
                  id="phone"
                  dir="ltr"
                  value={form.webino_mobile_phone}
                  onChange={(e) => setForm((f) => ({ ...f, webino_mobile_phone: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="role">{t("pages.customers.نقش_کاربری")}</Label>
                <Select
                  value={form.role}
                  onValueChange={(v) =>
                    setForm((f) => ({
                      ...f,
                      role: v,
                      department: STAFF_ROLES.has(v) ? f.department : 0,
                      job_title: STAFF_ROLES.has(v) ? f.job_title : 0,
                    }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {roles.map((r) => (
                      <SelectItem key={r.slug} value={r.slug}>
                        {r.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="password">
                  {editingUser
                    ? t("pages.customers.رمز_اختیاری")
                    : t("pages.customers.رمز_الزامی")}
                </Label>
                <Input
                  id="password"
                  type="password"
                  dir="ltr"
                  value={form.password}
                  onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                  required={!editingUser}
                />
              </div>
              {isStaffRole && (
                <>
                  <div className="space-y-2">
                    <Label>{t("pages.staff.دپارتمان")}</Label>
                    <Select
                      value={form.department ? String(form.department) : SELECT_ALL_VALUE}
                      onValueChange={(v) =>
                        setForm((f) => ({
                          ...f,
                          department: v === SELECT_ALL_VALUE ? 0 : parseInt(v, 10),
                          job_title: 0,
                        }))
                      }
                    >
                      <SelectTrigger>
                        <SelectValue placeholder={t("pages.staff.انتخاب_دپارتمان")} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                        {departments.map((d) => (
                          <SelectItem key={d.id} value={String(d.id)}>
                            {d.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.customers.سمت")}</Label>
                    <Select
                      value={form.job_title ? String(form.job_title) : SELECT_ALL_VALUE}
                      onValueChange={(v) =>
                        setForm((f) => ({
                          ...f,
                          job_title: v === SELECT_ALL_VALUE ? 0 : parseInt(v, 10),
                        }))
                      }
                    >
                      <SelectTrigger>
                        <SelectValue placeholder={t("pages.customers.انتخاب_سمت")} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
                        {filteredJobTitles.map((jt) => (
                          <SelectItem key={jt.id} value={String(jt.id)}>
                            {jt.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </>
              )}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                {t("common.cancel")}
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting && <Loader2 className={cn("h-4 w-4 animate-spin shrink-0", isRtl ? "ms-2" : "me-2")} />}
                {editingUser ? t("common.save") : t("pages.customers.ایجاد_کاربر")}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {messagingMode === "single" && messagingUser && (
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
      {messagingMode === "bulk" && (
        <MessagingDialog
          mode="bulk"
          open={messagingOpen}
          onOpenChange={setMessagingOpen}
          roles={roles}
          onSuccess={(msg) => setSuccess(msg)}
          onError={setError}
        />
      )}

      <Customer360Sheet
        customerId={customer360Id}
        open={customer360Id !== null}
        onOpenChange={(open) => !open && setCustomer360Id(null)}
        onEdit={(id) => {
          setCustomer360Id(null)
          const u = users.find((x) => x.id === id)
          if (u) openEdit(u)
        }}
      />

      <PmConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title={t("common.delete")}
        description={t("pages.customers.confirm.deleteUser")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        loading={submitting}
        isRtl={isRtl}
        onConfirm={async () => {
          if (deleteTarget) await handleDelete(deleteTarget)
        }}
      />
    </div>
  )
}
