import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type Props = {
  page: number
  totalPages: number
  onPageChange: (page: number) => void
  prevLabel: string
  nextLabel: string
  isRtl?: boolean
}

export function PmPagination({
  page,
  totalPages,
  onPageChange,
  prevLabel,
  nextLabel,
  isRtl,
}: Props) {
  if (totalPages <= 1) return null
  return (
    <div className={cn("flex justify-center gap-2", isRtl && "flex-row-reverse")}>
      <Button
        variant="outline"
        size="sm"
        disabled={page <= 1}
        onClick={() => onPageChange(Math.max(1, page - 1))}
      >
        {prevLabel}
      </Button>
      <span className="flex items-center px-2 text-sm text-muted-foreground">
        {page} / {totalPages}
      </span>
      <Button
        variant="outline"
        size="sm"
        disabled={page >= totalPages}
        onClick={() => onPageChange(Math.min(totalPages, page + 1))}
      >
        {nextLabel}
      </Button>
    </div>
  )
}
