import { useEffect, useRef, useState } from "react"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { MediaPickerDialog } from "@/components/media/MediaPickerDialog"
import { apiUploadFile } from "@/lib/api"
import { toastApiError } from "@/lib/apiError"
import { useLocale } from "@/hooks/use-locale"
import { ImageIcon, Upload } from "lucide-react"

type Props = {
  iconUrl: string
  onChange: (value: string) => void
  resetKey?: string | number
}

function resolvePreviewUrl(iconUrl: string): string | undefined {
  const v = iconUrl.trim()
  if (!v) return undefined
  if (/^https?:\/\//i.test(v) || v.startsWith("/")) return v
  return undefined
}

export function ModuleIconField({ iconUrl, onChange, resetKey }: Props) {
  const { t } = useLocale()
  const fileRef = useRef<HTMLInputElement>(null)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [previewOverride, setPreviewOverride] = useState<string | null>(null)
  const preview = previewOverride ?? resolvePreviewUrl(iconUrl)

  useEffect(() => {
    setPreviewOverride(null)
  }, [resetKey])

  const handleUpload = async (file: File) => {
    setUploading(true)
    try {
      const res = await apiUploadFile("content/media", file)
      if (res.id > 0 && res.url) {
        setPreviewOverride(res.url)
        onChange(String(res.id))
      }
    } catch (err) {
      toastApiError(t, err)
    } finally {
      setUploading(false)
    }
  }

  const handleRemove = () => {
    setPreviewOverride(null)
    onChange("")
  }

  return (
    <div className="space-y-3">
      <Label>{t("pages.marketplace.moduleIcon")}</Label>
      <div className="border-border flex flex-col items-center gap-3 rounded-lg border p-4 sm:flex-row sm:items-start">
        {preview ? (
          <img
            src={preview}
            alt=""
            className="h-20 w-20 shrink-0 rounded-lg object-contain"
          />
        ) : (
          <div className="bg-muted flex h-20 w-20 shrink-0 items-center justify-center rounded-lg">
            <ImageIcon className="text-muted-foreground h-10 w-10" />
          </div>
        )}
        <div className="flex flex-wrap justify-center gap-2 sm:justify-start">
          <Button type="button" size="sm" variant="outline" onClick={() => setPickerOpen(true)}>
            {t("pages.marketplace.selectFromMedia")}
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={uploading}
            onClick={() => fileRef.current?.click()}
          >
            <Upload className="me-2 h-4 w-4" />
            {uploading ? t("pages.marketplace.uploading") : t("pages.marketplace.uploadIcon")}
          </Button>
          {iconUrl ? (
            <Button type="button" size="sm" variant="ghost" onClick={handleRemove}>
              {t("pages.marketplace.removeIcon")}
            </Button>
          ) : null}
        </div>
      </div>
      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0]
          if (file) void handleUpload(file)
          e.target.value = ""
        }}
      />
      <MediaPickerDialog
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        onSelect={(item) => {
          setPreviewOverride(item.url)
          onChange(String(item.id))
        }}
      />
    </div>
  )
}
