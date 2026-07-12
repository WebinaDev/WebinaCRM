import { useCallback, useEffect, useState } from "react"
import type { EdgeListResult, EdgeMeta } from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { mapModirPayamakError } from "../modirpayamak-errors"

type Loader = () => Promise<EdgeListResult & { error?: string | null }>

type Options = {
  enabled?: boolean
  deps?: unknown[]
}

export function useModirPayamakEdge(loader: Loader, options: Options = {}) {
  const { t } = useLocale()
  const { enabled = true, deps = [] } = options
  const [items, setItems] = useState<EdgeListResult["items"]>([])
  const [meta, setMeta] = useState<EdgeMeta>({})
  const [raw, setRaw] = useState<unknown>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const reload = useCallback(async () => {
    if (!enabled) {
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    try {
      const res = await loader()
      if (res.error) {
        setItems([])
        setMeta({})
        setRaw(null)
        setError(mapModirPayamakError(res.error, t))
      } else {
        setItems(res.items)
        setMeta(res.meta)
        setRaw(res.raw)
      }
    } catch {
      setItems([])
      setMeta({})
      setRaw(null)
      setError(t("pages.modirpayamak.loadError"))
    } finally {
      setLoading(false)
    }
  }, [enabled, loader, t])

  useEffect(() => {
    void reload()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [reload, enabled, ...deps])

  return { items, meta, raw, loading, error, setError, reload }
}
