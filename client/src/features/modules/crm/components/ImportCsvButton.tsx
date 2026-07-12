import { useRef, useState } from "react"
import { Button } from "@/components/ui/button"
import { useLocale } from "@/hooks/use-locale"
import { Loader2, Upload } from "lucide-react"
import { cn } from "@/lib/utils"

type Props = {
  label: string
  disabled?: boolean
  onImport: (file: File) => Promise<void>
}

export function ImportCsvButton({ label, disabled, onImport }: Props) {
  const { isRtl } = useLocale()
  const inputRef = useRef<HTMLInputElement>(null)
  const [loading, setLoading] = useState(false)

  const handleFile = async (file: File | undefined) => {
    if (!file) return
    setLoading(true)
    try {
      await onImport(file)
    } finally {
      setLoading(false)
      if (inputRef.current) inputRef.current.value = ""
    }
  }

  return (
    <>
      <input
        ref={inputRef}
        type="file"
        accept=".csv,text/csv"
        className="hidden"
        onChange={(e) => void handleFile(e.target.files?.[0])}
      />
      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={disabled || loading}
        onClick={() => inputRef.current?.click()}
      >
        {loading ? (
          <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />
        ) : (
          <Upload className={cn("h-4 w-4", isRtl ? "ms-2" : "me-2")} />
        )}
        {label}
      </Button>
    </>
  )
}
