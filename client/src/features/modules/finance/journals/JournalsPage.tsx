import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useState, useEffect } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { SELECT_ALL_VALUE } from "@/lib/constants"
import {
  accountingJournalList,
  accountingJournalPost,
  accountingJournalDelete,
} from "@/api/accounting"
import type { JournalEntry } from "@/api/accounting"
import { DatePicker } from "@/components/ui/date-picker"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { FiscalYearSelect } from "@/features/modules/finance/components/FiscalYearSelect"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { JournalEntryDialog } from "@/features/modules/finance/journals/JournalEntryDialog"
import { Loader2, Plus, Pencil, Trash2, Eye, CheckCircle } from "lucide-react"
import { Link } from "react-router-dom"

const PER_PAGE = 15

export function JournalsPage() {
  const { t, isRtl, formatDate } = useLocale()
  const { layoutProps, applyResponse, setError } = useAccountingFeedback()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const [items, setItems] = useState<JournalEntry[]>([])
  const [fiscalYearId, setFiscalYearId] = useState(0)
  const [fiscalYearReady, setFiscalYearReady] = useState(false)
  const [dateFrom, setDateFrom] = useState("")
  const [dateTo, setDateTo] = useState("")
  const [statusFilter, setStatusFilter] = useState("")
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [dialogEntryId, setDialogEntryId] = useState<number | null>(null)
  const [dialogReadonly, setDialogReadonly] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [postId, setPostId] = useState<number | null>(null)

  const load = async () => {
    if (!fiscalYearId) {
      setLoading(false)
      return
    }
    setLoading(true)
    const res = await accountingJournalList({
      fiscal_year_id: fiscalYearId,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      status: statusFilter || undefined,
      page: currentPage,
      per_page: PER_PAGE,
    })
    if (res.success && res.data) {
      setItems(res.data.items ?? [])
      setTotalPages(Math.max(1, Math.ceil((res.data.total ?? 0) / PER_PAGE)))
    }
    setLoading(false)
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fiscalYearId, currentPage, dateFrom, dateTo, statusFilter])

  useEffect(() => {
    resetPage()
  }, [fiscalYearId, dateFrom, dateTo, statusFilter, resetPage])

  const openCreate = () => {
    setDialogEntryId(null)
    setDialogReadonly(false)
    setDialogOpen(true)
  }

  const openEdit = (id: number) => {
    setDialogEntryId(id)
    setDialogReadonly(false)
    setDialogOpen(true)
  }

  const openView = (id: number) => {
    setDialogEntryId(id)
    setDialogReadonly(true)
    setDialogOpen(true)
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.journals.عنوان_صفحه")}
      description={t("pages.accounting.journals.ثبت_و_مشاهده_اسناد_سند_حسابداری")}
      {...layoutProps}
      actions={
        <Button onClick={openCreate} disabled={!fiscalYearId}>
          <Plus className="h-4 w-4 ml-2" />
          {t("pages.accounting.journals.new")}
        </Button>
      }
    >
      <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
        <FiscalYearSelect
          value={fiscalYearId}
          onChange={setFiscalYearId}
          onReady={() => setFiscalYearReady(true)}
        />
        <div className="space-y-1">
          <Label className="text-xs">{t("pages.accounting.accountingReports.از_تاریخ")}</Label>
          <DatePicker value={dateFrom} onChange={setDateFrom} />
        </div>
        <div className="space-y-1">
          <Label className="text-xs">{t("pages.accounting.accountingReports.تا_تاریخ")}</Label>
          <DatePicker value={dateTo} onChange={setDateTo} />
        </div>
        <Select
          value={statusFilter || SELECT_ALL_VALUE}
          onValueChange={(v) => setStatusFilter(v === SELECT_ALL_VALUE ? "" : v)}
        >
          <SelectTrigger className="w-[140px]">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SELECT_ALL_VALUE}>{t("pages.accounting.journals.allStatus")}</SelectItem>
            <SelectItem value="draft">{t("pages.accounting.warehouseAudit.پیشنویس")}</SelectItem>
            <SelectItem value="posted">{t("pages.accounting.journals.ثبتشده")}</SelectItem>
          </SelectContent>
        </Select>
      </PmFilterBar>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("pages.accounting.journals.لیست_اسناد")}</CardTitle>
        </CardHeader>
        <CardContent>
          {!fiscalYearReady ? (
            <div className="flex items-center gap-2 text-muted-foreground py-4">
              <Loader2 className="h-4 w-4 animate-spin" />
              {t("common.loading")}
            </div>
          ) : !fiscalYearId ? (
            <div className="space-y-3">
              <PmEmptyState
                message={t("pages.accounting.fiscalYear.سال_مالی_تعریف_نشده_از_طریق_API_یا_تنظیم")}
              />
              <div className="flex justify-center">
                <Button variant="outline" asChild>
                  <Link to="/finance/fiscal-year">{t("nav.erp.finance.fiscalYear")}</Link>
                </Button>
              </div>
            </div>
          ) : loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <PmEmptyState message={t("pages.accounting.journals.سندی_یافت_نشد")} />
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("pages.accounting.journals.شماره_سند")}</TableHead>
                    <TableHead>{t("common.date")}</TableHead>
                    <TableHead>{t("pages.accounting.checks.شرح")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead className="w-[140px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell className="font-mono">{row.voucher_no}</TableCell>
                      <TableCell>{formatDate(row.voucher_date)}</TableCell>
                      <TableCell>{row.description || "—"}</TableCell>
                      <TableCell>
                        <Badge variant={row.status === "posted" ? "default" : "secondary"}>
                          {row.status === "posted"
                            ? t("pages.accounting.journals.ثبتشده")
                            : t("pages.accounting.warehouseAudit.پیشنویس")}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon" onClick={() => openView(row.id)}>
                            <Eye className="h-4 w-4" />
                          </Button>
                          {row.status === "draft" && (
                            <>
                              <Button variant="ghost" size="icon" onClick={() => openEdit(row.id)}>
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button variant="ghost" size="icon" onClick={() => setPostId(row.id)}>
                                <CheckCircle className="h-4 w-4 text-green-600" />
                              </Button>
                              <Button variant="ghost" size="icon" onClick={() => setDeleteId(row.id)}>
                                <Trash2 className="h-4 w-4 text-destructive" />
                              </Button>
                            </>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <PmPagination
                page={currentPage}
                totalPages={totalPages}
                onPageChange={setCurrentPage}
                prevLabel={t("common.prevPage")}
                nextLabel={t("common.nextPage")}
                isRtl={isRtl}
              />
            </>
          )}
        </CardContent>
      </Card>

      <JournalEntryDialog
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        entryId={dialogEntryId}
        readonly={dialogReadonly}
        defaultFiscalYearId={fiscalYearId}
        onSaved={() => {
          applyResponse({ success: true }, { successMessage: t("pages.accounting.journals.saved") })
          void load()
        }}
        onError={(msg) => setError(msg)}
      />

      <PmConfirmDialog
        open={!!deleteId}
        onOpenChange={() => setDeleteId(null)}
        title={t("pages.accounting.journals.deleteTitle")}
        description={t("pages.accounting.journals.deleteConfirm")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingJournalDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.journals.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />

      <PmConfirmDialog
        open={!!postId}
        onOpenChange={() => setPostId(null)}
        title={t("pages.accounting.journals.postTitle")}
        description={t("pages.accounting.journals.postConfirm")}
        confirmLabel={t("common.confirm")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!postId) return
          const res = await accountingJournalPost(postId)
          if (applyResponse(res, { successMessage: t("pages.accounting.journals.posted") })) {
            setPostId(null)
            void load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
