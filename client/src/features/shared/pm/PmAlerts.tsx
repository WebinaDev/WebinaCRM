import { Alert, AlertDescription } from "@/components/ui/alert"

type Props = {
  error?: string | null
  success?: string | null
  onDismissError?: () => void
  onDismissSuccess?: () => void
}

export function PmAlerts({ error, success }: Props) {
  return (
    <>
      {error ? (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      ) : null}
      {success ? (
        <Alert>
          <AlertDescription>{success}</AlertDescription>
        </Alert>
      ) : null}
    </>
  )
}
