import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useEffect, useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import {
  addLicense,
  cancelLicense,
  deleteLicense,
  getLicenses,
  renewLicense,
  updateLicense,
  type License,
} from "@/api/licenses"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { LicenseAddDialog } from "./components/LicenseAddDialog"
import { LicenseEditDialog } from "./components/LicenseEditDialog"
import { LicenseRenewDialog } from "./components/LicenseRenewDialog"
import { LicenseCard } from "./components/LicenseCard"
import { defaultLicenseAddForm, type LicenseEditFormState } from "./types"
import { Plus, Key } from "lucide-react"

export function LicensesPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, applyResponse, setError, setSuccess } = useCrmFeedback()
  const [licenses, setLicenses] = useState<License[]>([])
  const [loading, setLoading] = useState(true)
  const [cancelId, setCancelId] = useState<number | null>(null)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [addOpen, setAddOpen] = useState(false)
  const [renewOpen, setRenewOpen] = useState(false)
  const [renewId, setRenewId] = useState<number | null>(null)
  const [renewDate, setRenewDate] = useState("")
  const [editOpen, setEditOpen] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [editForm, setEditForm] = useState<LicenseEditFormState>({
    project_name: "",
    domain: "",
    expiry_date: "",
    logo_url: "",
    status: "active",
  })
  const [submitting, setSubmitting] = useState(false)
  const [addForm, setAddForm] = useState(defaultLicenseAddForm)

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getLicenses()
      if (res.success && res.data) {
        const d = res.data as { licenses: License[] }
        setLicenses(d.licenses ?? [])
      } else {
        setError(res.message ?? t("pages.licenses.خطا_در_بارگذاری_لایسنسها"))
      }
    } catch {
      setError(t("pages.licenses.خطا_در_بارگذاری_لایسنسها"))
    } finally {
      setLoading(false)
    }
  }

  const runCancel = async (id: number) => {
    setSubmitting(true)
    const res = await cancelLicense(id)
    setSubmitting(false)
    if (applyResponse(res)) void load()
  }

  const runDelete = async (id: number) => {
    setSubmitting(true)
    const res = await deleteLicense(id)
    setSubmitting(false)
    if (applyResponse(res, { successMessage: t("pages.accounting.shared.deleted") })) void load()
  }

  useEffect(() => {
    void load()
  }, [])

  const handleAdd = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!addForm.project_name.trim() || !addForm.domain.trim()) {
      setError(t("pages.licenses.نام_مجموعه_و_دامنه_الزامی_هستند"))
      return
    }
    setSubmitting(true)
    setError(null)
    setSuccess(null)
    try {
      const res = await addLicense({
        project_name: addForm.project_name.trim(),
        domain: addForm.domain.trim(),
        start_date: addForm.start_date || undefined,
        expiry_date: addForm.expiry_date || undefined,
        logo_url: addForm.logo_url.trim() || undefined,
        status: addForm.status,
      })
      if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
        setAddOpen(false)
        setAddForm(defaultLicenseAddForm())
        void load()
      }
    } catch {
      setError(t("pages.licenses.خطا_در_افزودن_لایسنس"))
    } finally {
      setSubmitting(false)
    }
  }

  const handleRenew = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!renewId || !renewDate) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await renewLicense(renewId, renewDate)
      if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
        setRenewOpen(false)
        setRenewId(null)
        setRenewDate("")
        void load()
      }
    } catch {
      setError(t("pages.licenses.خطا_در_تمدید_لایسنس"))
    } finally {
      setSubmitting(false)
    }
  }

  const openRenew = (id: number) => {
    setRenewId(id)
    setRenewDate(new Date().toISOString().slice(0, 10))
    setRenewOpen(true)
  }

  const openEdit = (license: License) => {
    setEditId(license.id)
    setEditForm({
      project_name: license.project_name,
      domain: license.domain,
      expiry_date: license.expiry_date ?? "",
      logo_url: license.logo_url ?? "",
      status: license.status,
    })
    setEditOpen(true)
  }

  const handleEdit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!editId) return
    setSubmitting(true)
    setError(null)
    try {
      const res = await updateLicense({
        id: editId,
        project_name: editForm.project_name.trim(),
        domain: editForm.domain.trim(),
        expiry_date: editForm.expiry_date || undefined,
        logo_url: editForm.logo_url.trim() || undefined,
        status: editForm.status,
      })
      if (applyResponse(res, { successMessage: t("pages.accounting.shared.saved") })) {
        setEditOpen(false)
        setEditId(null)
        void load()
      }
    } catch {
      setError(t("pages.licenses.خطا_در_ویرایش_لایسنس"))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <CrmPageLayout
      title={t("pages.licenses.مدیریت_لایسنسها")}
      {...layoutProps}
      actions={
        <Button onClick={() => setAddOpen(true)}>
          <Plus className={cn("h-4 w-4 shrink-0", isRtl ? "ms-2" : "me-2")} />
          {t("pages.licenses.افزودن_لایسنس_جدید")}
        </Button>
      }
    >
      {loading ? (
        <Card>
          <CardContent className="p-0">

            <TableListSkeleton rows={8} columns={5} />

          </CardContent>
        </Card>
      ) : licenses.length === 0 ? (
        <PmEmptyState icon={Key} message={t("pages.licenses.هنوز_لایسنسای_ثبت_نشده_است")} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {licenses.map((license) => (
            <LicenseCard
              key={license.id}
              license={license}
              onEdit={openEdit}
              onRenew={openRenew}
              onCancel={setCancelId}
              onDelete={setDeleteId}
            />
          ))}
        </div>
      )}

      <LicenseAddDialog
        open={addOpen}
        onOpenChange={setAddOpen}
        form={addForm}
        onFormChange={setAddForm}
        onSubmit={handleAdd}
        submitting={submitting}
      />

      <LicenseRenewDialog
        open={renewOpen}
        onOpenChange={setRenewOpen}
        renewDate={renewDate}
        onRenewDateChange={setRenewDate}
        onSubmit={handleRenew}
        submitting={submitting}
      />

      <LicenseEditDialog
        open={editOpen}
        onOpenChange={setEditOpen}
        form={editForm}
        onFormChange={setEditForm}
        onSubmit={handleEdit}
        submitting={submitting}
      />

      <PmConfirmDialog
        open={cancelId !== null}
        onOpenChange={() => setCancelId(null)}
        title={t("pages.licenses.لغو")}
        description={t("pages.licenses.confirm.cancel")}
        confirmLabel={t("pages.licenses.لغو")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (cancelId) await runCancel(cancelId)
          setCancelId(null)
        }}
      />
      <PmConfirmDialog
        open={deleteId !== null}
        onOpenChange={() => setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.licenses.confirm.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        isRtl={isRtl}
        onConfirm={async () => {
          if (deleteId) await runDelete(deleteId)
          setDeleteId(null)
        }}
      />
    </CrmPageLayout>
  )
}
