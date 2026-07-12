import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  accountingProductCategories,
  accountingProductCategorySave,
  accountingProductCategoryDelete,
} from "@/api/accounting"
import { useLocale } from "@/hooks/use-locale"
import { AccountingPageLayout } from "@/features/shared/layout/AccountingPageLayout"
import { useAccountingFeedback } from "@/features/modules/finance/hooks/useAccountingFeedback"
import { CategoryManagerSheet } from "@/features/modules/finance/components/CategoryManagerSheet"
import { UnitsManagerDialog } from "@/features/modules/finance/components/UnitsManagerDialog"
import { PriceListsTab } from "@/features/modules/finance/components/PriceListsTab"
import { ProductsListTab } from "./ProductsListTab"
import { FolderTree, Ruler } from "lucide-react"

export function ProductsPage() {
  const { t } = useLocale()
  const { layoutProps, applyResponse } = useAccountingFeedback()
  const [categorySheetOpen, setCategorySheetOpen] = useState(false)
  const [unitsOpen, setUnitsOpen] = useState(false)

  return (
    <AccountingPageLayout
      title={t("pages.accounting.products.عنوان_صفحه")}
      description={t("pages.accounting.products.مدیریت_کالاها_و_خدمات_برای_فاکتور_و_انبا")}
      {...layoutProps}
    >
      <Tabs defaultValue="list" className="space-y-4">
        <TabsList>
          <TabsTrigger value="list">{t("pages.accounting.products.tabList")}</TabsTrigger>
          <TabsTrigger value="price-lists">{t("pages.accounting.products.tabPriceLists")}</TabsTrigger>
        </TabsList>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" onClick={() => setCategorySheetOpen(true)} className="gap-2">
            <FolderTree className="h-4 w-4" />
            {t("pages.accounting.products.tabCategories")}
          </Button>
          <Button variant="outline" size="sm" onClick={() => setUnitsOpen(true)} className="gap-2">
            <Ruler className="h-4 w-4" />
            {t("pages.accounting.products.tabUnits")}
          </Button>
        </div>
        <TabsContent value="list">
          <ProductsListTab applyResponse={applyResponse} />
        </TabsContent>
        <TabsContent value="price-lists">
          <PriceListsTab />
        </TabsContent>
      </Tabs>

      <CategoryManagerSheet
        open={categorySheetOpen}
        onOpenChange={setCategorySheetOpen}
        title={t("pages.accounting.products.tabCategories")}
        loadCategories={accountingProductCategories}
        saveCategory={accountingProductCategorySave}
        deleteCategory={accountingProductCategoryDelete}
      />
      <UnitsManagerDialog open={unitsOpen} onOpenChange={setUnitsOpen} />
    </AccountingPageLayout>
  )
}