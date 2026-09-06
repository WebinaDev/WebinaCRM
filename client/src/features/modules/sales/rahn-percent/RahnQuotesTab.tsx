import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"
import { deleteRahnQuote, listRahnQuotes, type RahnQuote } from "@/api/rahn"
import { getAjaxMessage } from "@/api/client"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { useLocale } from "@/hooks/use-locale"
import { Copy, Trash2, RefreshCw } from "lucide-react"

export function RahnQuotesTab() {
  const { t, formatNumber } = useLocale()
  const [quotes, setQuotes] = useState<RahnQuote[]>([])
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await listRahnQuotes({ paged: 1 })
      if (res.success && res.data?.quotes) {
        setQuotes(res.data.quotes)
      }
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const copy = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url)
      toast.success(t("pages.rahn.linkCopied"))
    } catch {
      toast.message(url)
    }
  }

  const remove = async (id: number) => {
    const res = await deleteRahnQuote(id)
    if (!res.success) {
      toast.error(getAjaxMessage(res) || t("pages.rahn.saveError"))
      return
    }
    toast.success(res.data?.message || t("pages.rahn.deleted"))
    void load()
  }

  if (loading) {
    return <p className="text-sm text-muted-foreground">{t("common.loading")}</p>
  }

  if (quotes.length === 0) {
    return <p className="text-sm text-muted-foreground">{t("pages.rahn.noQuotes")}</p>
  }

  return (
    <div className="space-y-3">
      <div className="flex justify-end">
        <Button type="button" size="sm" variant="outline" onClick={() => void load()}>
          <RefreshCw className="h-4 w-4" />
          {t("common.refresh")}
        </Button>
      </div>
      {quotes.map((q) => (
        <Card key={q.id}>
          <CardContent className="flex flex-wrap items-center gap-3 p-4">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="font-medium">{q.title}</span>
                <Badge variant="secondary">{q.status}</Badge>
              </div>
              <div className="text-sm text-muted-foreground mt-1">
                {formatNumber(Math.round(q.F))} {t("pages.rahn.toman")} +{" "}
                {formatNumber(Math.round(q.p_percent * 100) / 100)}%
              </div>
              <div className="text-xs text-muted-foreground break-all dir-ltr text-left mt-1">
                {q.share_url}
              </div>
            </div>
            <Button type="button" size="sm" variant="outline" onClick={() => void copy(q.share_url)}>
              <Copy className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              size="sm"
              variant="ghost"
              className="text-destructive"
              onClick={() => void remove(q.id)}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
