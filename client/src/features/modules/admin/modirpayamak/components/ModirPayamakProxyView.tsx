import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useEffect, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { modirpayamakProxy } from "@/api/modirpayamak"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"

type Props = {
  title: string
  description?: string
  method: string
  path: string
  body?: Record<string, unknown>
  query?: Record<string, unknown>
}

export function ModirPayamakProxyView({ title, description, method, path, body, query }: Props) {
  const { t } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const [data, setData] = useState<unknown>(null)
  const [loading, setLoading] = useState(true)

  const bodyKey = JSON.stringify(body ?? null)
  const queryKey = JSON.stringify(query ?? null)

  useEffect(() => {
    void (async () => {
      setLoading(true)
      setError(null)
      const res = await modirpayamakProxy(method, path, body, query)
      if (res.success) {
        setData(res.data?.data ?? res.data)
      } else {
        setData(null)
        setError(getAjaxMessage(res) ?? t("pages.modirpayamak.loadError"))
      }
      setLoading(false)
    })()
  }, [method, path, bodyKey, queryKey, setError, t])

  return (
    <CrmPageLayout title={title} description={description} {...layoutProps}>
      <Card>
        <CardHeader>
          <CardTitle className="font-mono text-sm">
            {method} {path}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : (
            <pre className="max-h-[70vh] overflow-auto rounded bg-muted p-3 text-xs">
              {JSON.stringify(data, null, 2)}
            </pre>
          )}
        </CardContent>
      </Card>
    </CrmPageLayout>
  )
}
