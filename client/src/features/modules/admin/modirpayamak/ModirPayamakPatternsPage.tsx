import { useCallback, useState } from "react"
import { Link, useNavigate } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
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
  edgeDeletePattern,
  edgeField,
  edgeGetPattern,
  edgeListPatterns,
  edgeSavePattern,
  type EdgeRow,
} from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { useModirPayamakEdge } from "./hooks/useModirPayamakEdge"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { ModirPayamakStatusBadge } from "./components/ModirPayamakStatusBadge"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { Code, Eye, Pencil, Plus, Send, Trash2 } from "lucide-react"

export function ModirPayamakPatternsPage() {
  const { t, isRtl, formatDateTime } = useLocale()
  const navigate = useNavigate()
  const { layoutProps, setError, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const [page, setPage] = useState(1)
  const loader = useCallback(() => edgeListPatterns(page, 20), [page])
  const { items, raw, loading, error, reload } = useModirPayamakEdge(loader, { deps: [page] })

  const [detailOpen, setDetailOpen] = useState(false)
  const [detailRow, setDetailRow] = useState<EdgeRow | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [editCode, setEditCode] = useState<string | null>(null)
  const [form, setForm] = useState({ code: "", title: "", message: "" })
  const [saving, setSaving] = useState(false)
  const [deleteCode, setDeleteCode] = useState<string | null>(null)
  const [deleting, setDeleting] = useState(false)

  const openDetail = async (row: EdgeRow) => {
    const code = edgeField(row, "code", "pattern_code", "id")
    if (code === "—") {
      setDetailRow(row)
      setDetailOpen(true)
      return
    }
    const res = await edgeGetPattern(code)
    setDetailRow(res.item ?? row)
    setDetailOpen(true)
  }

  const openCreate = () => {
    setEditCode(null)
    setForm({ code: "", title: "", message: "" })
    setEditOpen(true)
  }

  const openEdit = (row: EdgeRow) => {
    setEditCode(edgeField(row, "code", "pattern_code", "id"))
    setForm({
      code: edgeField(row, "code", "pattern_code", "id"),
      title: edgeField(row, "title", "name", "description"),
      message: edgeField(row, "message", "text", "body", "pattern"),
    })
    setEditOpen(true)
  }

  const save = async () => {
    setSaving(true)
    setError(null)
    const body = {
      code: form.code.trim(),
      title: form.title.trim(),
      message: form.message.trim(),
      pattern: form.message.trim(),
    }
    const res = await edgeSavePattern(editCode, body)
    setSaving(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setEditOpen(false)
      void reload()
    }
  }

  const confirmDelete = async () => {
    if (!deleteCode) return
    setDeleting(true)
    const res = await edgeDeletePattern(deleteCode)
    setDeleting(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.deleted") })) {
      setDeleteCode(null)
      void reload()
    }
  }

  const useInSend = (row: EdgeRow) => {
    const code = edgeField(row, "code", "pattern_code", "id")
    navigate(`/admin/integrations/modirpayamak/send?pattern=${encodeURIComponent(code)}&mode=pattern`)
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.patternsTitle")}
      {...layoutProps}
      error={error ?? layoutProps.error}
      actions={
        <Button size="sm" onClick={openCreate}>
          <Plus className="me-2 h-4 w-4" />
          {t("pages.modirpayamak.addPattern")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.patternsTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={5} />
          </CardContent>
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState icon={Code} message={t("pages.modirpayamak.patternsEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.patternCode")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.name")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.colDate")}</TableHead>
                  <TableHead className="w-[140px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => {
                  const code = edgeField(row, "code", "pattern_code", "id")
                  return (
                    <TableRow key={code !== "—" ? code : i}>
                      <TableCell className="font-mono">{code}</TableCell>
                      <TableCell className="max-w-xs truncate">
                        {edgeField(row, "title", "name", "description")}
                      </TableCell>
                      <TableCell>
                        <ModirPayamakStatusBadge status={edgeField(row, "status", "state")} />
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {formatDateTime(edgeField(row, "created_at", "updated_at").replace(/-/g, "/").slice(0, 16))}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button type="button" variant="ghost" size="icon" onClick={() => void openDetail(row)}>
                            <Eye className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(row)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="ghost" size="icon" onClick={() => useInSend(row)}>
                            <Send className="h-4 w-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-destructive"
                            onClick={() => setDeleteCode(code !== "—" ? code : null)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <PmPagination
        page={page}
        totalPages={Math.max(1, page + (items.length >= 20 ? 1 : 0))}
        onPageChange={setPage}
        prevLabel={t("common.prevPage")}
        nextLabel={t("common.nextPage")}
        isRtl={isRtl}
      />

      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent side={isRtl ? "left" : "right"} className="overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>{edgeField(detailRow, "code", "pattern_code")}</SheetTitle>
          </SheetHeader>
          {detailRow ? (
            <div className="mt-4 space-y-3 text-sm">
              <p>
                <span className="text-muted-foreground">{t("pages.modirpayamak.name")}: </span>
                {edgeField(detailRow, "title", "name")}
              </p>
              <p>
                <span className="text-muted-foreground">{t("pages.modirpayamak.status")}: </span>
                <ModirPayamakStatusBadge status={edgeField(detailRow, "status")} />
              </p>
              <div>
                <Label>{t("pages.modirpayamak.message")}</Label>
                <pre className="mt-1 whitespace-pre-wrap rounded bg-muted p-3 text-xs">
                  {edgeField(detailRow, "message", "text", "body", "pattern")}
                </pre>
              </div>
              <Button size="sm" asChild>
                <Link to={`/admin/integrations/modirpayamak/send?pattern=${encodeURIComponent(edgeField(detailRow, "code", "pattern_code"))}&mode=pattern`}>
                  {t("pages.modirpayamak.useInSend")}
                </Link>
              </Button>
            </div>
          ) : null}
        </SheetContent>
      </Sheet>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editCode ? t("common.edit") : t("pages.modirpayamak.addPattern")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.patternCode")}</Label>
              <Input value={form.code} onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} disabled={!!editCode} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.name")}</Label>
              <Input value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.message")}</Label>
              <Textarea rows={4} value={form.message} onChange={(e) => setForm((f) => ({ ...f, message: e.target.value }))} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditOpen(false)}>{t("common.cancel")}</Button>
            <Button onClick={() => void save()} disabled={saving}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteCode != null}
        onOpenChange={(open) => !open && setDeleteCode(null)}
        title={t("common.delete")}
        description={t("pages.modirpayamak.confirm.deletePattern")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
