import { useCallback, useEffect, useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Loader2, Download, RefreshCw } from "lucide-react"
import { getCrmCoreUpdateStatus, runCrmCoreUpdate, type CoreUpdateStatus } from "@/api/core-update"
import { useLocale } from "@/hooks/use-locale"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"

type Props = {
  onError: (s: string | null) => void
  onMessage: (s: string | null) => void
}

export function CrmCoreUpdateTab({ onError, onMessage }: Props) {
  const { t, isRtl } = useLocale()
  const [status, setStatus] = useState<CoreUpdateStatus | null>(null)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [updating, setUpdating] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)

  const loadStatus = useCallback(
    async (refresh = false) => {
      if (refresh) setRefreshing(true)
      else setLoading(true)
      onError(null)
      try {
        const res = await getCrmCoreUpdateStatus(refresh)
        if (res.success && res.data) {
          setStatus(res.data)
        } else {
          onError(res.message ?? t("coreUpdate.loadFailed"))
        }
      } catch {
        onError(t("coreUpdate.loadFailed"))
      } finally {
        if (refresh) setRefreshing(false)
        else setLoading(false)
      }
    },
    [onError, t],
  )

  useEffect(() => {
    void loadStatus(false)
  }, [loadStatus])

  const runUpdate = async () => {
    if (!status?.update_available || !status.package_available) return
    setUpdating(true)
    onError(null)
    onMessage(null)
    try {
      const res = await runCrmCoreUpdate(status.latest_version)
      if (res.success && res.data) {
        onMessage(t("coreUpdate.success", { version: res.data.version }))
        if (res.data.reload_required) {
          window.setTimeout(() => window.location.reload(), 900)
        } else {
          await loadStatus(true)
        }
      } else {
        onError(res.message ?? t("coreUpdate.updateFailed"))
      }
    } catch {
      onError(t("coreUpdate.updateFailed"))
    } finally {
      setUpdating(false)
      setConfirmOpen(false)
    }
  }

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle>{t("coreUpdate.title")}</CardTitle>
          <CardDescription>{t("coreUpdate.description")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-7 w-7 animate-spin text-muted-foreground" />
            </div>
          ) : (
            <>
              <div className="grid gap-3 rounded-md border p-3 text-sm sm:grid-cols-2">
                <div>
                  <span className="text-muted-foreground">{t("coreUpdate.current")}:</span>{" "}
                  <strong>{status?.version ?? "-"}</strong>
                </div>
                <div>
                  <span className="text-muted-foreground">{t("coreUpdate.latest")}:</span>{" "}
                  <strong>{status?.latest_version ?? "-"}</strong>
                </div>
              </div>
              {status?.update_available ? (
                <p className="text-sm text-emerald-600 dark:text-emerald-400">
                  {t("coreUpdate.available")}
                </p>
              ) : (
                <p className="text-muted-foreground text-sm">{t("coreUpdate.upToDate")}</p>
              )}
              {status?.update_available && !status?.package_available ? (
                <p className="text-amber-600 text-sm dark:text-amber-400">
                  {t("coreUpdate.packageMissing")}
                </p>
              ) : null}
              {status?.release_notes ? (
                <pre className="bg-muted max-h-40 overflow-auto rounded-md p-3 text-xs whitespace-pre-wrap">
                  {status.release_notes}
                </pre>
              ) : null}
              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  variant="outline"
                  disabled={refreshing || updating}
                  onClick={() => void loadStatus(true)}
                >
                  {refreshing ? (
                    <Loader2 className="me-2 h-4 w-4 animate-spin" />
                  ) : (
                    <RefreshCw className="me-2 h-4 w-4" />
                  )}
                  {t("coreUpdate.checkAgain")}
                </Button>
                <Button
                  type="button"
                  disabled={updating || !status?.update_available || !status?.package_available}
                  onClick={() => setConfirmOpen(true)}
                >
                  {updating ? (
                    <Loader2 className="me-2 h-4 w-4 animate-spin" />
                  ) : (
                    <Download className="me-2 h-4 w-4" />
                  )}
                  {t("coreUpdate.install")}
                </Button>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <PmConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t("coreUpdate.install")}
        description={t("coreUpdate.confirm")}
        confirmLabel={t("coreUpdate.install")}
        cancelLabel={t("common.cancel")}
        onConfirm={runUpdate}
        loading={updating}
        isRtl={isRtl}
      />
    </>
  )
}
