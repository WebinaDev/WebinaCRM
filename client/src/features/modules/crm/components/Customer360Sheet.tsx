import { DetailSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useState } from "react"
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { getCustomer360, type Customer360Data } from "@/api/customers"
import { useLocale } from "@/hooks/use-locale"
import { Pencil } from "lucide-react"
import { Link } from "react-router-dom"

type Props = {
  customerId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onEdit?: (id: number) => void
}

export function Customer360Sheet({ customerId, open, onOpenChange, onEdit }: Props) {
  const { t, isRtl, formatNumber, formatDisplayDate } = useLocale()
  const [data, setData] = useState<Customer360Data | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!customerId) return
    setLoading(true)
    setError(null)
    try {
      const res = await getCustomer360(customerId)
      if (res.success && res.data) {
        setData(res.data)
      } else {
        setError(res.message ?? t("pages.customers.خطا_در_بارگذاری_کاربران"))
      }
    } catch {
      setError(t("pages.customers.خطا_در_بارگذاری_کاربران"))
    } finally {
      setLoading(false)
    }
  }, [customerId, t])

  useEffect(() => {
    if (open && customerId) {
      void load()
    } else if (!open) {
      setData(null)
      setError(null)
    }
  }, [open, customerId, load])

  const c = data?.customer

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side={isRtl ? "left" : "right"}
        className="w-full sm:max-w-xl overflow-y-auto"
        dir={isRtl ? "rtl" : "ltr"}
      >
        <SheetHeader>
          <SheetTitle>{t("pages.customers.customer_360")}</SheetTitle>
        </SheetHeader>
        {loading && <DetailSkeleton compact showPageHeader={false} />}
        {error && <p className="text-sm text-destructive">{error}</p>}
        {c && data && !loading && (
          <div className="space-y-6 pb-8">
            <div className="flex items-center gap-4">
              <Avatar className="h-16 w-16">
                <AvatarImage src={c.avatar_url} alt="" />
                <AvatarFallback>{c.display_name.slice(0, 2)}</AvatarFallback>
              </Avatar>
              <div className="flex-1 min-w-0">
                <h2 className="font-semibold text-lg truncate">{c.display_name}</h2>
                <p className="text-sm text-muted-foreground truncate" dir="ltr">{c.email}</p>
                {c.phone && <p className="text-sm" dir="ltr">{c.phone}</p>}
              </div>
              {onEdit && (
                <Button size="sm" variant="outline" onClick={() => onEdit(c.id)}>
                  <Pencil className="h-4 w-4" />
                </Button>
              )}
            </div>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div className="rounded-lg border p-3">
                <p className="text-muted-foreground">{t("pages.customers.stats_projects")}</p>
                <p className="text-xl font-semibold">{data.stats.projects_count}</p>
              </div>
              <div className="rounded-lg border p-3">
                <p className="text-muted-foreground">{t("pages.customers.stats_contracts")}</p>
                <p className="text-xl font-semibold">{data.stats.contracts_count}</p>
              </div>
              <div className="rounded-lg border p-3">
                <p className="text-muted-foreground">{t("pages.customers.stats_tickets")}</p>
                <p className="text-xl font-semibold">{data.stats.tickets_count}</p>
              </div>
              <div className="rounded-lg border p-3">
                <p className="text-muted-foreground">{t("pages.customers.stats_revenue")}</p>
                <p className="text-xl font-semibold" dir="ltr">
                  {formatNumber(data.stats.total_revenue)}
                </p>
              </div>
            </div>
            <Tabs defaultValue="overview">
              <TabsList className="w-full">
                <TabsTrigger value="overview" className="flex-1">{t("pages.customers.tab_overview")}</TabsTrigger>
                <TabsTrigger value="projects" className="flex-1">{t("pages.customers.tab_projects")}</TabsTrigger>
                <TabsTrigger value="contracts" className="flex-1">{t("pages.customers.tab_contracts")}</TabsTrigger>
                <TabsTrigger value="tickets" className="flex-1">{t("pages.customers.tab_tickets")}</TabsTrigger>
              </TabsList>
              <TabsContent value="overview" className="text-sm space-y-2 pt-3">
                <p>
                  <span className="text-muted-foreground">{t("pages.customers.member_since")}: </span>
                  {formatDisplayDate(c.registered.slice(0, 10))}
                </p>
              </TabsContent>
              <TabsContent value="projects" className="pt-3 space-y-2">
                {data.projects.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{t("common.empty")}</p>
                ) : (
                  data.projects.map((p) => (
                    <Link
                      key={p.id}
                      to={`/projects/${p.id}`}
                      className="block rounded border px-3 py-2 hover:bg-muted text-sm"
                    >
                      <span className="font-medium">{p.title}</span>
                      <span className="text-muted-foreground ms-2">{formatDisplayDate(p.date)}</span>
                    </Link>
                  ))
                )}
              </TabsContent>
              <TabsContent value="contracts" className="pt-3 space-y-2">
                {data.contracts.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{t("common.empty")}</p>
                ) : (
                  data.contracts.map((item) => (
                    <div key={item.id} className="rounded border px-3 py-2 text-sm flex justify-between gap-2">
                      <span className="font-medium">{item.title}</span>
                      <span dir="ltr" className="text-muted-foreground shrink-0">
                        {formatNumber(item.amount)}
                      </span>
                    </div>
                  ))
                )}
              </TabsContent>
              <TabsContent value="tickets" className="pt-3 space-y-2">
                {data.tickets.length === 0 ? (
                  <p className="text-muted-foreground text-sm">{t("common.empty")}</p>
                ) : (
                  data.tickets.map((item) => (
                    <Link
                      key={item.id}
                      to={`/tickets?ticket_id=${item.id}`}
                      className="block rounded border px-3 py-2 hover:bg-muted text-sm"
                    >
                      {item.title}
                    </Link>
                  ))
                )}
              </TabsContent>
            </Tabs>
          </div>
        )}
      </SheetContent>
    </Sheet>
  )
}
