import { useCallback, useState } from "react"
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
  edgeCreateTicket,
  edgeField,
  edgeGetTicket,
  edgeListTickets,
  edgeReplyTicket,
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
import { Headphones, Plus } from "lucide-react"

export function ModirPayamakTicketsPage() {
  const { t, isRtl, formatDateTime } = useLocale()
  const { layoutProps, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const loader = useCallback(() => edgeListTickets(), [])
  const { items, raw, loading, reload } = useModirPayamakEdge(loader)

  const [createOpen, setCreateOpen] = useState(false)
  const [subject, setSubject] = useState("")
  const [body, setBody] = useState("")
  const [creating, setCreating] = useState(false)

  const [detailOpen, setDetailOpen] = useState(false)
  const [detailId, setDetailId] = useState<number | null>(null)
  const [detail, setDetail] = useState<EdgeRow | null>(null)
  const [reply, setReply] = useState("")
  const [replying, setReplying] = useState(false)

  const ticketId = (row: EdgeRow) => Number(edgeField(row, "id", "ticket_id")) || 0

  const openTicket = async (row: EdgeRow) => {
    const id = ticketId(row)
    if (!id) return
    setDetailId(id)
    const res = await edgeGetTicket(id)
    setDetail(res.item ?? row)
    setDetailOpen(true)
  }

  const createTicket = async () => {
    setCreating(true)
    const res = await edgeCreateTicket({ subject: subject.trim(), body: body.trim(), message: body.trim() })
    setCreating(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setCreateOpen(false)
      setSubject("")
      setBody("")
      void reload()
    }
  }

  const sendReply = async () => {
    if (!detailId || !reply.trim()) return
    setReplying(true)
    const res = await edgeReplyTicket(detailId, { message: reply.trim(), body: reply.trim() })
    setReplying(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setReply("")
      const refreshed = await edgeGetTicket(detailId)
      setDetail(refreshed.item ?? detail)
    }
  }

  const messages = (detail?.messages ?? detail?.replies ?? detail?.thread) as unknown
  const messageList = Array.isArray(messages) ? (messages as EdgeRow[]) : []

  return (
    <CrmPageLayout
      title={t("pages.modirpayamak.ticketsTitle")}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={() => setCreateOpen(true)}>
          <Plus className="me-2 h-4 w-4" />
          {t("pages.modirpayamak.newTicket")}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.ticketsTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      {loading ? (
        <Card>
          <CardContent className="p-0">
            <TableListSkeleton rows={8} columns={4} />
          </CardContent>
        </Card>
      ) : items.length === 0 ? (
        <PmEmptyState icon={Headphones} message={t("pages.modirpayamak.ticketsEmpty")} />
      ) : (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.modirpayamak.ticketSubject")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.status")}</TableHead>
                  <TableHead>{t("pages.modirpayamak.colDate")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((row, i) => (
                  <TableRow key={ticketId(row) || i} className="cursor-pointer hover:bg-muted/50" onClick={() => void openTicket(row)}>
                    <TableCell>{edgeField(row, "subject", "title")}</TableCell>
                    <TableCell>
                      <ModirPayamakStatusBadge status={edgeField(row, "status", "state")} />
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {formatDateTime(edgeField(row, "updated_at", "created_at").replace(/-/g, "/").slice(0, 16))}
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
            <DialogTitle>{t("pages.modirpayamak.newTicket")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.ticketSubject")}</Label>
              <Input value={subject} onChange={(e) => setSubject(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.modirpayamak.message")}</Label>
              <Textarea rows={4} value={body} onChange={(e) => setBody(e.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCreateOpen(false)}>{t("common.cancel")}</Button>
            <Button disabled={creating} onClick={() => void createTicket()}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent side={isRtl ? "left" : "right"} className="flex flex-col sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>{edgeField(detail, "subject", "title")}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 overflow-y-auto space-y-3 py-4">
            {messageList.length > 0 ? (
              messageList.map((m, i) => (
                <div key={i} className="rounded-lg border bg-muted/40 p-3 text-sm">
                  <p className="whitespace-pre-wrap">{edgeField(m, "message", "body", "text")}</p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {formatDateTime(edgeField(m, "created_at", "date").replace(/-/g, "/").slice(0, 16))}
                  </p>
                </div>
              ))
            ) : (
              <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                {edgeField(detail, "body", "message", "description")}
              </p>
            )}
          </div>
          <div className="space-y-2 border-t pt-4">
            <Label>{t("pages.modirpayamak.reply")}</Label>
            <Textarea rows={3} value={reply} onChange={(e) => setReply(e.target.value)} />
            <Button disabled={replying} onClick={() => void sendReply()}>{t("pages.modirpayamak.sendReply")}</Button>
          </div>
        </SheetContent>
      </Sheet>

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
