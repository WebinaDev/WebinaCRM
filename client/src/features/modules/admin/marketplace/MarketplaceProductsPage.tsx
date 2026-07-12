import { CardGridSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Checkbox } from "@/components/ui/checkbox"
import {
  deleteMarketplaceModule,
  getMarketplaceModules,
  saveMarketplaceModule,
  type MarketplaceModule,
} from "@/api/marketplace"
import { marketplaceError } from "./marketplace-messages"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { MarketplaceProductCard } from "./MarketplaceProductCard"
import { Package, Plus } from "lucide-react"

export function MarketplaceProductsPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const [modules, setModules] = useState<MarketplaceModule[]>([])
  const [loading, setLoading] = useState(true)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [showCore, setShowCore] = useState(false)
  const [statusBusyId, setStatusBusyId] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    const res = await getMarketplaceModules(showCore)
    if (res.success && res.data) {
      setModules(res.data.modules ?? [])
    } else {
      setError(marketplaceError(res, t("pages.marketplace.api.modulesLoadError")))
      setModules([])
    }
    setLoading(false)
  }, [setError, t, showCore])

  useEffect(() => {
    void load()
  }, [load])

  const handleStatusToggle = async (m: MarketplaceModule, active: boolean) => {
    if (m.is_core) return
    setStatusBusyId(m.id)
    setError(null)
    const fd = new FormData()
    fd.set("id", String(m.id))
    fd.set("slug", m.slug)
    fd.set("status", active ? "active" : "inactive")
    const res = await saveMarketplaceModule(fd)
    setStatusBusyId(null)
    if (res.success) {
      void load()
    } else {
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
  }

  const confirmDelete = async () => {
    if (deleteId == null) return
    setDeleting(true)
    const res = await deleteMarketplaceModule(deleteId)
    setDeleting(false)
    if (res.success) {
      setDeleteId(null)
      void load()
    } else {
      setError(marketplaceError(res, t("pages.marketplace.deleteError")))
    }
  }

  const sortedModules = modules
    .slice()
    .sort((a, b) => Number(Boolean(b.is_core)) - Number(Boolean(a.is_core)))

  return (
    <CrmPageLayout
      title={t("pages.marketplace.productsTitle")}
      description={t("pages.marketplace.productsDesc")}
      actions={
        <Button asChild>
          <Link to="/marketplace/modules/new">
            <Plus className="me-2 h-4 w-4" />
            {t("pages.marketplace.addProduct")}
          </Link>
        </Button>
      }
      {...layoutProps}
    >
      <div className="flex items-center gap-2">
        <Checkbox
          checked={showCore}
          onCheckedChange={(v) => setShowCore(Boolean(v))}
          id="mp-show-core"
        />
        <Label htmlFor="mp-show-core">{t("pages.marketplace.showCoreProducts")}</Label>
      </div>

      {loading ? (
        <CardGridSkeleton cards={8} showPageHeader={false} />
      ) : sortedModules.length === 0 ? (
        <PmEmptyState icon={Package} message={t("pages.marketplace.productsDesc")} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {sortedModules.map((m) => (
            <MarketplaceProductCard
              key={m.id}
              module={m}
              statusBusy={statusBusyId === m.id}
              onStatusToggle={(active) => void handleStatusToggle(m, active)}
              onDelete={() => setDeleteId(m.id)}
            />
          ))}
        </div>
      )}

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.marketplace.confirm.deleteProduct")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  )
}
