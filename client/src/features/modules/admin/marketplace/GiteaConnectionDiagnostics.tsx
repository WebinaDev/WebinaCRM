import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import type { GiteaConnectionDiagnosticsResult } from "@/api/marketplace"
import { useLocale } from "@/hooks/use-locale"
import { translateMarketplaceMessage } from "./marketplace-messages"
import { cn } from "@/lib/utils"

const STEP_ORDER = ["version", "auth", "owner", "owner_repos"] as const

const HINT_KEYS: Record<string, string> = {
  ssl_wrong_version: "testHintSsl",
  token_missing: "testHintToken",
  unauthorized: "testHintUnauthorized",
  org_not_found: "testHintOrg",
  owner_not_found: "testHintOwnerNotFound",
  owner_is_user: "testHintOwnerUser",
  config_incomplete: "testHintConfig",
}

const STEP_HINT_KEYS: Record<string, string> = {
  ssl_wrong_version: "testStepHintSsl",
  unauthorized: "testStepHintUnauthorized",
  not_found: "testStepHintNotFound",
  network_error: "testStepHintNetwork",
  http_error: "testStepHintHttp",
  ok: "testStepHintOk",
}

type Props = {
  result: GiteaConnectionDiagnosticsResult | null
  loading?: boolean
}

export function GiteaConnectionDiagnostics({ result, loading }: Props) {
  const { t } = useLocale()
  const g = (key: string, opts?: Record<string, string | number>) =>
    t(`pages.marketplace.gitea.${key}`, opts)

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{g("testDiagnostics")}</CardTitle>
          <CardDescription>{g("testDiagnosticsDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">{g("testRunning")}</CardContent>
      </Card>
    )
  }

  if (!result) return null

  const diag = result.diag ?? {}
  const steps = result.steps ?? {}

  const hintLabel = (hint: string) => {
    if (!hint) return "—"
    const key = STEP_HINT_KEYS[hint]
    return key ? g(key) : hint
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{g("testDiagnostics")}</CardTitle>
        <CardDescription>{g("testDiagnosticsDesc")}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <p
          className={cn(
            result.ok ? "text-emerald-600 dark:text-emerald-400" : "text-destructive",
          )}
        >
          {result.ok
            ? result.user
              ? g("testOkUser", { user: result.user })
              : g("testOk")
            : translateMarketplaceMessage(t, result.message, "pages.marketplace.gitea.testFail")}
        </p>

        {result.hints && result.hints.length > 0 ? (
          <div className="space-y-2">
            {result.hints.map((hint) => {
              const key = HINT_KEYS[hint]
              return (
                <div
                  key={hint}
                  className="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs"
                >
                  {key ? g(key) : hint}
                </div>
              )
            })}
          </div>
        ) : null}

        {diag.resolved_url_sample || diag.ip_scheme || diag.owner_kind ? (
          <div className="grid gap-1 text-xs text-muted-foreground">
            {diag.owner_kind ? (
              <p>
                {g("ownerKind")}:{" "}
                <span className="font-mono text-foreground" dir="ltr">
                  {String(diag.owner_kind)}
                </span>
              </p>
            ) : null}
            {diag.ip_scheme ? (
              <p>
                {g("ipScheme")}:{" "}
                <span className="font-mono text-foreground" dir="ltr">
                  {String(diag.ip_scheme)}
                  {diag.resolved_scheme ? ` → ${String(diag.resolved_scheme)}` : ""}
                </span>
              </p>
            ) : null}
            {diag.resolved_url_sample ? (
              <p className="break-all font-mono text-foreground" dir="ltr">
                {String(diag.resolved_url_sample)}
              </p>
            ) : null}
          </div>
        ) : null}

        {Object.keys(steps).length > 0 ? (
          <div className="overflow-x-auto rounded-md border border-border">
            <table className="w-full min-w-[20rem] border-collapse text-xs [&_td]:border-b [&_td]:border-border [&_th]:border-b [&_th]:border-border">
              <thead>
                <tr className="bg-muted/40">
                  <th className="p-2 text-start font-medium">{g("testProbeName")}</th>
                  <th className="p-2 text-start font-medium">{g("testProbeHttp")}</th>
                  <th className="p-2 text-start font-medium">{g("testProbeHint")}</th>
                  <th className="p-2 text-start font-medium">{g("testProbeMsg")}</th>
                </tr>
              </thead>
              <tbody>
                {STEP_ORDER.map((key) => {
                  const row = steps[key]
                  if (!row) return null
                  return (
                    <tr key={key}>
                      <td className="p-2">{row.label || key}</td>
                      <td className="p-2 font-mono tabular-nums" dir="ltr">
                        {row.http ? row.http : row.curl_error ? "—" : "—"}
                        {row.ms != null ? ` (${row.ms}ms)` : ""}
                      </td>
                      <td className="p-2">
                        <Badge variant={row.ok ? "default" : "destructive"} className="font-normal">
                          {hintLabel(String(row.hint ?? ""))}
                        </Badge>
                      </td>
                      <td
                        className="max-w-[14rem] truncate p-2 text-muted-foreground"
                        title={String(row.message ?? row.curl_error ?? "")}
                      >
                        {String(row.message ?? row.curl_error ?? "")}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        ) : null}

        <details className="text-xs">
          <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
            {g("testRawJson")}
          </summary>
          <pre
            className="mt-2 max-h-48 overflow-auto rounded-md border border-border bg-muted/40 p-2"
            dir="ltr"
          >
            {JSON.stringify(result, null, 2)}
          </pre>
        </details>
      </CardContent>
    </Card>
  )
}
