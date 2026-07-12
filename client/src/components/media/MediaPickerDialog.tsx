import { useQuery } from "@tanstack/react-query"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useLocale } from "@/hooks/use-locale"

type MediaItem = {
  id: number
  title: string
  url: string | null
  mime: string
}

type MediaPickerDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSelect: (item: { id: number; url: string }) => void
}

export function MediaPickerDialog({ open, onOpenChange, onSelect }: MediaPickerDialogProps) {
  const { t } = useLocale()
  const q = useQuery({
    queryKey: ["media", "picker"],
    queryFn: () => apiFetch<{ items: MediaItem[] }>("content/media?page=1&per_page=60"),
    enabled: open,
  })

  const items = (q.data?.items ?? []).filter((item) => item.mime.startsWith("image/"))

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-hidden sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t("pages.marketplace.mediaPickerTitle")}</DialogTitle>
          <DialogDescription className="sr-only">
            {t("pages.marketplace.mediaPickerTitle")}
          </DialogDescription>
        </DialogHeader>
        <div className="max-h-[55vh] overflow-y-auto">
          {q.isLoading ? (
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
              {Array.from({ length: 8 }).map((_, i) => (
                <Skeleton key={i} className="aspect-square rounded-md" />
              ))}
            </div>
          ) : items.length === 0 ? (
            <p className="text-muted-foreground py-8 text-center text-sm">
              {t("pages.marketplace.mediaEmpty")}
            </p>
          ) : (
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
              {items.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  className="group border-border bg-muted/30 hover:border-primary hover:ring-primary/30 overflow-hidden rounded-md border transition hover:ring-2"
                  onClick={() => {
                    if (item.url) {
                      onSelect({ id: item.id, url: item.url })
                      onOpenChange(false)
                    }
                  }}
                >
                  {item.url ? (
                    <img
                      src={item.url}
                      alt={item.title}
                      className="aspect-square w-full object-cover transition group-hover:scale-105"
                    />
                  ) : (
                    <div className="text-muted-foreground flex aspect-square items-center justify-center text-xs">
                      {item.title}
                    </div>
                  )}
                </button>
              ))}
            </div>
          )}
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t("common.cancel")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
