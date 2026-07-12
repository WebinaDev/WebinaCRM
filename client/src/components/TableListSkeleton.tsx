import { Skeleton } from '@/components/ui/skeleton'

type Props = {
  rows?: number
  columns?: number
  withAvatarColumn?: boolean
  withHeader?: boolean
}

export function TableListSkeleton({
  rows = 6,
  columns = 4,
  withAvatarColumn = false,
  withHeader = true,
}: Props) {
  return (
    <div className="space-y-3 p-4" aria-busy="true">
      {withHeader ? (
        <div className="flex gap-2 border-b pb-3">
          {withAvatarColumn ? <Skeleton className="h-9 w-10 shrink-0" /> : null}
          {Array.from({ length: columns }).map((_, j) => (
            <Skeleton key={j} className="h-9 flex-1" />
          ))}
        </div>
      ) : null}
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="flex gap-2">
          {withAvatarColumn ? <Skeleton className="h-10 w-10 shrink-0 rounded-full" /> : null}
          {Array.from({ length: columns }).map((_, j) => (
            <Skeleton key={j} className="h-8 flex-1" />
          ))}
        </div>
      ))}
    </div>
  )
}
