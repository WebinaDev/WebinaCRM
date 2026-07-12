import { useCallback, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import {
  edgeCreateUser,
  edgeField,
  edgeGetUser,
  edgeListUsers,
  edgeUpdateUser,
  type EdgeRow,
} from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { useModirPayamakEdge } from "./hooks/useModirPayamakEdge"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { Eye, Pencil, Plus, UserCog } from "lucide-react"

export function ModirPayamakUsersPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const loader = useCallback(() => edgeListUsers(), [])
  const { items, raw, loading, reload } = useModirPayamakEdge(loader)

  const [dialogOpen, setDialogOpen] = useState(false)
  const [editId, setEditId] = useState<string | null>(null)
  const [form, setForm] = useState({ username: "", password: "", email: "", mobile: "" })
  const [saving, setSaving] = useState(false)
  const [detailOpen, setDetailOpen] = useState(false)
  const [detailRow, setDetailRow] = useState<EdgeRow | null>(null)

  const userId = (row: EdgeRow) => edgeField(row, "id", "user_id", "username")

  const openCreate = () => {
    setEditId(null)
    setForm({ username: "", password: "", email: "", mobile: "" })
    setDialogOpen(true)
  }

  const openEdit = (row: EdgeRow) => {
    setEditId(userId(row))
    setForm({
      username: edgeField(row, "username", "name"),
      password: "",
      email: edgeField(row, "email"),
      mobile: edgeField(row, "mobile", "phone"),
    })
    setDialogOpen(true)
  }

  const openDetail = async (row: EdgeRow) => {
    const id = userId(row)
    if (id === "—") {
      setDetailRow(row)
      setDetailOpen(true)
      return
    }
    const res = await edgeGetUser(id)
    setDetailRow(res.item ?? row)
    setDetailOpen(true)
  }

  const save = async () => {
    setSaving(true)
    const body: Record<string, unknown> = {
      username: form.username.trim(),
      email: form.email.trim(),
      mobile: form.mobile.trim(),
    }
    if (form.password.trim()) body.password = form.password.trim()
    const res = editId ? await edgeUpdateUser(editId, body) : await edgeCreateUser(body)
    setSaving(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setDialogOpen(false)
      void reload()
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.usersTitle")}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={openCreate}>
          <Plus className="me-2 h-4 w-4" />
          {t("pages.modirpayamak.addUser")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.usersTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={5} />
          </CardContent>
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState icon={UserCog} message={t("pages.modirpayamak.usersEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.username")}</TableHead>
                  <TableHead>{t("pages.consultations.ایمیل")}</TableHead>
                  <TableHead>{t("pages.accounting.persons.موبایل")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => (
                  <TableRow key={userId(row) !== "—" ? userId(row) : i}>
                    <TableCell>{edgeField(row, "username", "name")}</TableCell>
                    <TableCell dir="ltr">{edgeField(row, "email")}</TableCell>
                    <TableCell dir="ltr">{edgeField(row, "mobile", "phone")}</TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={edgeField(row, "status", "state")} />
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button type="button" variant="ghost" size="icon" onClick={() => void openDetail(row)}>
                          <Eye className="h-4 w-4" />
                        </Button>
                        <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(row)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editId ? t("common.edit") : t("pages.modirpayamak.addUser")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.username")}</Label>
              <Input value={form.username} onChange={(e) => setForm((f) => ({ ...f, username: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.password")}</Label>
              <Input type="password" value={form.password} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.consultations.ایمیل")}</Label>
              <Input dir="ltr" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.persons.موبایل")}</Label>
              <Input dir="ltr" value={form.mobile} onChange={(e) => setForm((f) => ({ ...f, mobile: e.target.value }))} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{t("common.cancel")}</Button>
            <Button disabled={saving} onClick={() => void save()}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent side={isRtl ? "left" : "right"} className="sm:max-w-md">
          <SheetHeader>
            <SheetTitle>{edgeField(detailRow, "username", "name")}</SheetTitle>
          </SheetHeader>
          {detailRow ? (
            <dl className="mt-4 space-y-2 text-sm">
              <div>
                <dt className="text-muted-foreground">{t("pages.consultations.ایمیل")}</dt>
                <dd dir="ltr">{edgeField(detailRow, "email")}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">{t("pages.accounting.persons.موبایل")}</dt>
                <dd dir="ltr">{edgeField(detailRow, "mobile", "phone")}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">{t("pages.modirpayamak.status")}</dt>
                <dd><ModirPayamakStatusBadge status={edgeField(detailRow, "status")} /></dd>
              </div>
            </dl>
          ) : null}
        </SheetContent>
      </Sheet>

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
