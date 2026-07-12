import { useState } from "react"
import { useLocale } from "@/hooks/use-locale"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
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
import { ScrollText } from "lucide-react"
import type { BaleLogRow } from "./types"
import { getBaleUserLogs } from "./api"

type Props = {
  logs: BaleLogRow[]
  onRefresh: () => void
  formatDateTime: (value: string) => string
  t: (key: string) => string
}

export function BaleLogsTab({ logs, onRefresh, formatDateTime, t }: Props) {
  const { isRtl } = useLocale()
  const [chatIdFilter, setChatIdFilter] = useState("")
  const [chatLogs, setChatLogs] = useState<{
    events: Array<Record<string, string>>
    logs: Array<Record<string, string>>
  } | null>(null)

  const fetchUserLogs = async () => {
    if (!chatIdFilter.trim()) return
    const res = await getBaleUserLogs(chatIdFilter.trim())
    if (res.ok && res.data) setChatLogs(res.data)
  }

  return (
    <div className="space-y-4 text-start" dir={isRtl ? "rtl" : "ltr"}>
      <Card>
        <CardHeader>
          <CardTitle>{t("pages.balebusiness.لاگ_هر_کاربر_بر_اساس_chatid")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex gap-2 text-start">
            <Input
              dir="ltr"
              value={chatIdFilter}
              onChange={(e) => setChatIdFilter(e.target.value)}
              placeholder={t("pages.balebusiness.chatid_کاربر")}
            />
            <Button type="button" onClick={() => void fetchUserLogs()}>
              {t("pages.balebusiness.fetch")}
            </Button>
          </div>
          {chatLogs ? (
            <div className="text-xs rounded-md bg-muted p-3 max-h-64 overflow-auto">
              <pre>{JSON.stringify(chatLogs, null, 2)}</pre>
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ScrollText className="h-5 w-5" />
            {t("pages.balebusiness.recent_logs")}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-16">#</TableHead>
                <TableHead>{t("pages.balebusiness.زمان")}</TableHead>
                <TableHead>{t("pages.accounting.chartOfAccounts.سطح")}</TableHead>
                <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                <TableHead>{t("pages.balebusiness.col_context")}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logs.map((row) => (
                <TableRow key={row.id}>
                  <TableCell>{row.id}</TableCell>
                  <TableCell className="whitespace-nowrap">
                    {formatDateTime(row.created_at)}
                  </TableCell>
                  <TableCell>{row.level}</TableCell>
                  <TableCell>{row.log_type}</TableCell>
                  <TableCell className="max-w-md truncate text-xs font-mono">{row.context}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {logs.length === 0 ? (
            <p className="text-sm text-muted-foreground py-4">
              {t("pages.balebusiness.لاگی_ثبت_نشده_است")}
            </p>
          ) : null}
          <Button type="button" variant="outline" className="mt-4" onClick={onRefresh}>
            {t("pages.balebusiness.refresh")}
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
