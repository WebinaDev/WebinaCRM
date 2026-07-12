import { useCallback, useEffect, useMemo, useState } from "react"
import { useNavigate, useSearchParams } from "react-router-dom"
import { Card, CardContent } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  listSubscriptions,
  listProducts,
  convertSubscriptionToContract,
  updateProductTaskTemplate,
  type Subscription,
  type Product,
  type TaskTemplate,
} from "@/api/services"
import { useLocale } from "@/hooks/use-locale"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { RefreshCw, ShoppingBag } from "lucide-react"
import { SubscriptionsTab } from "./SubscriptionsTab"
import { ProductsTab } from "./ProductsTab"
import { ProductTaskTemplateDialog } from "./ProductTaskTemplateDialog"

export function ServicesPage() {
  const { t, isRtl } = useLocale()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const tabFromUrl = searchParams.get("tab")
  const [activeTab, setActiveTab] = useState(
    tabFromUrl === "products" || tabFromUrl === "subscriptions" ? tabFromUrl : "subscriptions",
  )
  const [subscriptions, setSubscriptions] = useState<Subscription[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [taskTemplates, setTaskTemplates] = useState<TaskTemplate[]>([])
  const [wcSubscriptionsActive, setWcSubscriptionsActive] = useState(false)
  const [wcActive, setWcActive] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [convertingId, setConvertingId] = useState<number | null>(null)
  const [editProduct, setEditProduct] = useState<Product | null>(null)
  const [editForm, setEditForm] = useState({
    task_template_id: null as number | null,
    service_task_type: "onetime",
  })
  const [submitting, setSubmitting] = useState(false)

  const serviceTypes = useMemo(
    () => [
      { value: "onetime", label: t("pages.services.یکبار_مثل_سایت") },
      { value: "daily", label: t("pages.services.روزانه_مثل_اینستاگرام") },
      { value: "weekly", label: t("pages.services.هفتگی") },
      { value: "monthly", label: t("pages.services.ماهانه") },
    ],
    [t],
  )

  const loadSubscriptions = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await listSubscriptions()
      if (res.success && res.data) {
        setSubscriptions(res.data.subscriptions ?? [])
        setWcSubscriptionsActive(res.data.wc_subscriptions_active ?? false)
      } else {
        setError(res.message ?? t("pages.services.خطا_در_بارگذاری_اشتراکها"))
      }
    } catch {
      setError(t("pages.services.خطا_در_بارگذاری_اشتراکها"))
    } finally {
      setLoading(false)
    }
  }, [t])

  const loadProducts = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await listProducts()
      if (res.success && res.data) {
        setProducts(res.data.products ?? [])
        setTaskTemplates(res.data.task_templates ?? [])
        setWcActive(res.data.wc_active ?? false)
      } else {
        setError(res.message ?? t("pages.services.خطا_در_بارگذاری_محصولات"))
      }
    } catch {
      setError(t("pages.services.خطا_در_بارگذاری_محصولات"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    if (tabFromUrl === "products" || tabFromUrl === "subscriptions") {
      setActiveTab(tabFromUrl)
    }
  }, [tabFromUrl])

  const handleTabChange = (tab: string) => {
    setActiveTab(tab)
    setSearchParams(tab === "subscriptions" ? {} : { tab }, { replace: true })
  }

  useEffect(() => {
    if (activeTab === "subscriptions") {
      void loadSubscriptions()
    } else {
      void loadProducts()
    }
  }, [activeTab, loadSubscriptions, loadProducts])

  const handleConvert = async (sub: Subscription) => {
    if (sub.already_converted) return
    setConvertingId(sub.id)
    setError(null)
    try {
      const res = await convertSubscriptionToContract(sub.id)
      const contractId = res.data?.contract_id
      if (res.success && contractId) {
        navigate(`/contracts?contract_id=${contractId}`)
        return
      }
      if (!res.success && contractId) {
        navigate(`/contracts?contract_id=${contractId}`)
        return
      }
      setError(res.message ?? t("pages.services.خطا_در_تبدیل_اشتراک"))
    } catch {
      setError(t("pages.services.خطا_در_تبدیل_اشتراک"))
    } finally {
      setConvertingId(null)
    }
  }

  const openEditProduct = (p: Product) => {
    setEditProduct(p)
    setEditForm({
      task_template_id: p.task_template_id ?? null,
      service_task_type: p.service_task_type || "onetime",
    })
  }

  const handleSaveProductTaskTemplate = async () => {
    if (!editProduct) return
    setSubmitting(true)
    try {
      const res = await updateProductTaskTemplate({
        product_id: editProduct.id,
        task_template_id: editForm.task_template_id,
        service_task_type: editForm.service_task_type,
      })
      if (res.success) {
        setEditProduct(null)
        void loadProducts()
      } else {
        setError(res.message ?? t("common.errors.saveFailed"))
      }
    } catch {
      setError(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader title={t("pages.services.عنوان_صفحه")} isRtl={isRtl} />

      <Card>
        <CardContent className="pt-6">
          <Tabs value={activeTab} onValueChange={handleTabChange}>
            <TabsList>
              <TabsTrigger value="subscriptions" className="gap-2">
                <RefreshCw className="h-4 w-4" />
                {t("pages.services.تب_اشتراکها")}
              </TabsTrigger>
              <TabsTrigger value="products" className="gap-2">
                <ShoppingBag className="h-4 w-4" />
                {t("pages.services.تب_محصولات")}
              </TabsTrigger>
            </TabsList>

            <TabsContent value="subscriptions" className="mt-4">
              <SubscriptionsTab
                loading={loading}
                error={error}
                wcActive={wcSubscriptionsActive}
                subscriptions={subscriptions}
                convertingId={convertingId}
                onConvert={handleConvert}
              />
            </TabsContent>

            <TabsContent value="products" className="mt-4">
              <ProductsTab
                loading={loading}
                error={error}
                wcActive={wcActive}
                products={products}
                serviceTypes={serviceTypes}
                onEdit={openEditProduct}
              />
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>

      <ProductTaskTemplateDialog
        open={!!editProduct}
        product={editProduct}
        taskTemplates={taskTemplates}
        serviceTypes={serviceTypes}
        form={editForm}
        setForm={setEditForm}
        submitting={submitting}
        onOpenChange={(open) => !open && setEditProduct(null)}
        onSave={handleSaveProductTaskTemplate}
      />
    </div>
  )
}
