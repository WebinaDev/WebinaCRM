import { useCallback, useEffect, useState } from "react"
import { getModirPayamakDashboard } from "@/api/modirpayamak"

export function useModirPayamakConfigured() {
  const [configured, setConfigured] = useState<boolean | null>(null)
  const [loading, setLoading] = useState(true)

  const reload = useCallback(async () => {
    setLoading(true)
    const res = await getModirPayamakDashboard()
    setConfigured(Boolean(res.success && res.data?.stats?.configured))
    setLoading(false)
  }, [])

  useEffect(() => {
    void reload()
  }, [reload])

  return { configured, loading, reload }
}
