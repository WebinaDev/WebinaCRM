import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
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
  accountingPersonsList,
  accountingPersonGet,
  accountingPersonSave,
  accountingPersonDelete,
  accountingPersonCategories,
  accountingPersonCategorySave,
  accountingPersonCategoryDelete,
  accountingPriceLists,
} from "@/api/accounting"
import type { Person, PersonCategory, PriceList } from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { CategoryManagerSheet } from "@/features/modules/finance/components/CategoryManagerSheet"
import { PmFilterBar } from "@/features/shared/pm/PmFilterBar"
import { PmPagination } from "@/features/shared/pm/PmPagination"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { usePmPagination } from "@/features/shared/pm/usePmPagination"
import { useDebouncedValue } from "@/features/shared/pm/useDebouncedValue"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { FolderTree, Loader2, Plus, Pencil, Trash2 } from "lucide-react"

const PER_PAGE = 15

export function PersonsPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const { currentPage, setCurrentPage, totalPages, setTotalPages, resetPage } = usePmPagination()
  const PERSON_TYPES = [
    { value: "customer", label: t("pages.accounting.persons.مشتری") },
    { value: "supplier", label: t("pages.accounting.persons.تأمینکننده") },
    { value: "both", label: t("pages.accounting.persons.هر_دو") },
  ]

  const [items, setItems] = useState<Person[]>([])
  const [categories, setCategories] = useState<PersonCategory[]>([])
  const [priceLists, setPriceLists] = useState<PriceList[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState("")
  const debouncedSearch = useDebouncedValue(search)
  const [personTypeFilter, setPersonTypeFilter] = useState("")
  const [categoryFilter, setCategoryFilter] = useState("")
  const [dialogOpen, setDialogOpen] = useState(false)
  const [categorySheetOpen, setCategorySheetOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [form, setForm] = useState<Partial<Person> & { name: string }>({
    name: "",
    person_type: "both",
    is_active: 1,
  })

  const load = useCallback(async () => {
    setLoading(true)
    const params: Record<string, string | number> = { page: currentPage, per_page: PER_PAGE }
    if (debouncedSearch.trim()) params.search = debouncedSearch.trim()
    if (personTypeFilter) params.person_type = personTypeFilter
    if (categoryFilter) params.category_id = Number(categoryFilter)
    const res = await accountingPersonsList(params)
    if (res.success && res.data) {
      setItems(res.data.items ?? [])
      setTotalPages(Math.max(1, Math.ceil((res.data.total ?? 0) / PER_PAGE)))
    }
    setLoading(false)
  }, [currentPage, debouncedSearch, personTypeFilter, categoryFilter, setTotalPages])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    resetPage()
  }, [debouncedSearch, personTypeFilter, categoryFilter, resetPage])

  useEffect(() => {
    accountingPersonCategories().then((res) => {
      if (res.success && res.data?.items) setCategories(res.data.items)
    })
    accountingPriceLists().then((res) => {
      if (res.success && res.data?.items) setPriceLists(res.data.items)
    })
  }, [])

  const openCreate = () => {
    setEditingId(null)
    setForm({ name: "", code: "", person_type: "both", is_active: 1 })
    setDialogOpen(true)
  }

  const openEdit = async (id: number) => {
    const res = await accountingPersonGet(id)
    if (res.success && res.data?.person) {
      setForm(res.data.person)
      setEditingId(id)
      setDialogOpen(true)
    }
  }

  const handleSave = async () => {
    if (!form.name?.trim()) return
    setSaving(true)
    const res = await accountingPersonSave({ ...form, name: form.name.trim(), id: editingId ?? undefined })
    setSaving(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
      setDialogOpen(false)
      void load()
    }
  }

  return (
    <AccountingPageLayout
      title={t("pages.accounting.persons.عنوان_صفحه")}
      description={t("pages.accounting.persons.مدیریت_مشتریان_و_تأمینکنندگان")}
      {...layoutProps}
      actions={
        <>
          <Button variant="outline" onClick={() => setCategorySheetOpen(true)}>
            <FolderTree className="h-4 w-4 ml-2" />
            {t("pages.accounting.products.tabCategories")}
          </Button>
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4 ml-2" />
            {t("pages.accounting.persons.شخص_جدید")}
          </Button>
        </>
      }
    >
      <PmFilterBar applyLabel={t("common.search")} onApply={() => resetPage()} isRtl={isRtl}>
        <Input
          placeholder={t("pages.accounting.persons.جستجو_نام،_کد،_موبایل،_شناسه_ملی")}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
        <Select
          value={personTypeFilter || SELECT_ALL_VALUE}
          onValueChange={(v) => setPersonTypeFilter(v === SELECT_ALL_VALUE ? "" : v)}
        >
          <SelectTrigger className="w-[140px]">
            <SelectValue placeholder={t("pages.accounting.cashAccounts.نوع")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
            {PERSON_TYPES.map((pt) => (
              <SelectItem key={pt.value} value={pt.value}>
                {pt.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select
          value={categoryFilter || SELECT_ALL_VALUE}
          onValueChange={(v) => setCategoryFilter(v === SELECT_ALL_VALUE ? "" : v)}
        >
          <SelectTrigger className="w-[160px]">
            <SelectValue placeholder={t("pages.accounting.persons.دسته")} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SELECT_ALL_VALUE}>{t("common.all")}</SelectItem>
            {categories.map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>
                {c.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </PmFilterBar>

      <Card>
        <CardContent className="pt-6">
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : items.length === 0 ? (
            <p className="text-muted-foreground">{t("pages.accounting.persons.شخصی_یافت_نشد")}</p>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t("common.name")}</TableHead>
                    <TableHead>{t("pages.accounting.accountingReports.کد")}</TableHead>
                    <TableHead>{t("pages.accounting.persons.موبایل_تلفن")}</TableHead>
                    <TableHead>{t("pages.accounting.cashAccounts.نوع")}</TableHead>
                    <TableHead>{t("common.status")}</TableHead>
                    <TableHead className="w-[100px]">{t("common.actions")}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell className="font-medium">{row.name}</TableCell>
                      <TableCell>{row.code || "—"}</TableCell>
                      <TableCell>{row.mobile || row.phone || "—"}</TableCell>
                      <TableCell>
                        {PERSON_TYPES.find((pt) => pt.value === row.person_type)?.label ?? row.person_type}
                      </TableCell>
                      <TableCell>
                        {row.is_active ? (
                          <Badge>{t("common.active")}</Badge>
                        ) : (
                          <Badge variant="secondary">{t("common.inactive")}</Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="ghost" size="icon" onClick={() => void openEdit(row.id)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="icon" onClick={() => setDeleteId(row.id)}>
                            <Trash2 className="h-4 w-4 text-destructive" />
                          </Button>
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

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto" dir={isRtl ? "rtl" : "ltr"}>
          <DialogHeader>
            <DialogTitle>
              {editingId ? t("pages.accounting.persons.ویرایش_شخص") : t("pages.accounting.persons.شخص_جدید")}
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("common.name")}</Label>
                <Input value={form.name ?? ""} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.accountingReports.کد")}</Label>
                <Input value={form.code ?? ""} onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.persons.موبایل")}</Label>
                <Input value={form.mobile ?? ""} onChange={(e) => setForm((f) => ({ ...f, mobile: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.persons.تلفن")}</Label>
                <Input value={form.phone ?? ""} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.persons.شناسه_ملی")}</Label>
                <Input
                  value={form.national_id ?? ""}
                  onChange={(e) => setForm((f) => ({ ...f, national_id: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.persons.کد_اقتصادی")}</Label>
                <Input
                  value={form.economic_code ?? ""}
                  onChange={(e) => setForm((f) => ({ ...f, economic_code: e.target.value }))}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.persons.آدرس")}</Label>
              <Input value={form.address ?? ""} onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.persons.نوع_شخص")}</Label>
                <Select
                  value={form.person_type ?? "both"}
                  onValueChange={(v) => setForm((f) => ({ ...f, person_type: v as Person["person_type"] }))}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {PERSON_TYPES.map((pt) => (
                      <SelectItem key={pt.value} value={pt.value}>
                        {pt.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.persons.دسته")}</Label>
                <Select
                  value={form.category_id ? String(form.category_id) : "0"}
                  onValueChange={(v) => setForm((f) => ({ ...f, category_id: v === "0" ? null : Number(v) }))}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">{t("pages.accounting.persons.بدون_دسته")}</SelectItem>
                    {categories.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t("pages.accounting.persons.creditLimit")}</Label>
                <Input
                  type="number"
                  value={form.credit_limit ?? ""}
                  onChange={(e) =>
                    setForm((f) => ({
                      ...f,
                      credit_limit: e.target.value ? Number(e.target.value) : null,
                    }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label>{t("pages.accounting.products.tabPriceLists")}</Label>
                <Select
                  value={form.default_price_list_id ? String(form.default_price_list_id) : "0"}
                  onValueChange={(v) =>
                    setForm((f) => ({
                      ...f,
                      default_price_list_id: v === "0" ? null : Number(v),
                    }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">—</SelectItem>
                    {priceLists.map((pl) => (
                      <SelectItem key={pl.id} value={String(pl.id)}>
                        {pl.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.accounting.persons.note")}</Label>
              <Input value={form.note ?? ""} onChange={(e) => setForm((f) => ({ ...f, note: e.target.value }))} />
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={!!form.is_active}
                onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.checked ? 1 : 0 }))}
              />
              {t("common.active")}
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              {t("common.cancel")}
            </Button>
            <Button onClick={() => void handleSave()} disabled={saving || !form.name?.trim()}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {t("common.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <CategoryManagerSheet
        open={categorySheetOpen}
        onOpenChange={setCategorySheetOpen}
        title={t("pages.accounting.products.tabCategories")}
        loadCategories={accountingPersonCategories}
        saveCategory={accountingPersonCategorySave}
        deleteCategory={accountingPersonCategoryDelete}
      />

      <PmConfirmDialog
        open={deleteId !== null}
        onOpenChange={() => setDeleteId(null)}
        title={t("pages.accounting.persons.حذف_شخص")}
        description={t("pages.accounting.persons.آیا_از_حذف_این_شخص_اطمینان_دارید؟")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (!deleteId) return
          const res = await accountingPersonDelete(deleteId)
          if (applyResponse(res, { successMessage: t("pages.accounting.shared.deleted") })) {
            setDeleteId(null)
            void load()
          }
        }}
      />
    </AccountingPageLayout>
  )
}
