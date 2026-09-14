import { useCallback, useEffect, useState } from "react"
import { useNavigate } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
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
  edgeCreateDraft,
  edgeDeleteDraft,
  edgeField,
  edgeListDraftGroups,
  edgeListDrafts,
  type EdgeRow,
} from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { mapModirPayamakError } from "./modirpayamak-errors"
import { DraftingCompass, Plus, Send, Trash2 } from "lucide-react"

export function ModirPayamakDraftsPage() {
  const { t, isRtl, formatDateTime } = useLocale()
  const navigate = useNavigate()
  const { layoutProps, setError, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()

  const [groups, setGroups] = useState<EdgeRow[]>([])
  const [groupId, setGroupId] = useState<string>("")
  const [items, setItems] = useState<EdgeRow[]>([])
  const [raw, setRaw] = useState<unknown>(null)
  const [loading, setLoading] = useState(true)

  const [createOpen, setCreateOpen] = useState(false)
  const [message, setMessage] = useState("")
  const [creating, setCreating] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)

  const draftId = (row: EdgeRow) => Number(edgeField(row, "id", "draft_id")) || 0
  const groupKey = (row: EdgeRow) => edgeField(row, "id", "draft_group_id")

  const loadGroups = useCallback(async () => {
    const res = await edgeListDraftGroups()
    if (res.error) {
      setGroups([])
      setError(mapModirPayamakError(res.error, t))
      return
    }
    setGroups(res.items)
    setRaw(res.raw)
    if (!groupId && res.items[0]) {
      setGroupId(groupKey(res.items[0]))
    }
  }, [groupId, setError, t])

  const loadDrafts = useCallback(async () => {
    if (!groupId || groupId === "—") {
      setItems([])
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    const res = await edgeListDrafts({ draft_group_id: groupId })
    if (res.error) {
      setItems([])
      setError(mapModirPayamakError(res.error, t))
    } else {
      setItems(res.items)
      setRaw(res.raw)
    }
    setLoading(false)
  }, [groupId, setError, t])

  useEffect(() => {
    void (async () => {
      setLoading(true)
      await loadGroups()
      setLoading(false)
    })()
  }, [loadGroups])

  useEffect(() => {
    void loadDrafts()
  }, [loadDrafts])

  const createDraft = async () => {
    if (!groupId || groupId === "—") return
    setCreating(true)
    const res = await edgeCreateDraft({
      draft_group_id: Number(groupId) || groupId,
      message: message.trim(),
    })
    setCreating(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setCreateOpen(false)
      setMessage("")
      void loadDrafts()
    }
  }

  const confirmDelete = async () => {
    if (!deleteId) return
    setDeleting(true)
    const res = await edgeDeleteDraft(deleteId)
    setDeleting(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.deleted") })) {
      setDeleteId(null)
      void loadDrafts()
    }
  }

  const useDraft = (row: EdgeRow) => {
    const text = edgeField(row, "message", "body", "text")
    navigate(`/admin/integrations/modirpayamak/send?message=${encodeURIComponent(text === "—" ? "" : text)}`)
  }

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.draftsTitle")}
      {...layoutProps}
      actions={
        <Button size="sm" disabled={!groupId || groupId === "—"} onClick={() => setCreateOpen(true)}>
          <Plus className="me-2 h-4 w-4" />
          {t("pages.modirpayamak.addDraft")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.draftsTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      <div className="mb-4 max-w-sm space-y-2">
        <Label>{t("pages.modirpayamak.draftGroup")}</Label>
        <Select value={groupId || undefined} onValueChange={setGroupId}>
          <SelectTrigger>
            <SelectValue placeholder={t("pages.modirpayamak.draftGroup")} />
          </SelectTrigger>
          <SelectContent>
            {groups.map((g, i) => {
              const id = groupKey(g)
              return (
                <SelectItem key={id || i} value={id}>
                  {edgeField(g, "title", "name", "id")}
                </SelectItem>
              )
            })}
          </SelectContent>
        </Select>
      </div>

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={3} />
          </CardContent>
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState icon={DraftingCompass} message={t("pages.modirpayamak.draftsEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.message")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.colDate")}</TableHead>
                  <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => (
                  <TableRow key={draftId(row) || i}>
                    <TableCell className="max-w-md truncate">
                      {edgeField(row, "message", "body", "text")}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {formatDateTime(edgeField(row, "created_at", "updated_at").replace(/-/g, "/").slice(0, 16))}
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button type="button" variant="ghost" size="icon" onClick={() => useDraft(row)}>
                          <Send className="h-4 w-4" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="text-destructive"
                          onClick={() => setDeleteId(draftId(row) || null)}
                        >
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
      )}

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("pages.modirpayamak.addDraft")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.message")}</Label>
              <Textarea rows={4} value={message} onChange={(e) => setMessage(e.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCreateOpen(false)}>{t("common.cancel")}</Button>
            <Button disabled={creating || !message.trim()} onClick={() => void createDraft()}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.modirpayamak.confirm.deleteDraft")}
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
