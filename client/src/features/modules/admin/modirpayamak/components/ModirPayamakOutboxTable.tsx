import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { edgeField, type EdgeRow } from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { ModirPayamakStatusBadge } from "./ModirPayamakStatusBadge"

type Props = {
  items: EdgeRow[]
  onRowClick?: (row: EdgeRow) => void
}

export function ModirPayamakOutboxTable({ items, onRowClick }: Props) {
  const { t, formatDateTime } = useLocale()

  const formatDate = (row: EdgeRow) => {
    const raw = edgeField(row, "created_at", "sent_at", "date", "time")
    if (raw === "—") return raw
    return formatDateTime(raw.replace(/-/g, "/").slice(0, 19))
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t("pages.modirpayamak.colRecipient")}</TableHead>
          <TableHead>{t("pages.modirpayamak.message")}</TableHead>
          <TableHead>{t("pages.modirpayamak.status")}</TableHead>
          <TableHead>{t("pages.modirpayamak.colDate")}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.map((row, i) => {
          const id = edgeField(row, "id", "messages_outbox_id", "outbox_id")
          const recipient = edgeField(row, "recipient", "to", "mobile", "phone", "number")
          const message = edgeField(row, "message", "text", "body", "content")
          const status = edgeField(row, "status", "state", "delivery_status")
          return (
            <TableRow
              key={id !== "—" ? id : i}
              className={onRowClick ? "cursor-pointer hover:bg-muted/50" : undefined}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
            >
              <TableCell dir="ltr" className="font-mono text-sm">
                {recipient}
              </TableCell>
              <TableCell className="max-w-xs truncate" title={message}>
                {message}
              </TableCell>
              <TableCell>
                <ModirPayamakStatusBadge status={status} />
              </TableCell>
              <TableCell className="text-muted-foreground text-sm">{formatDate(row)}</TableCell>
            </TableRow>
          )
        })}
      </TableBody>
    </Table>
  )
}
