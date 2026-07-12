import type { ComponentType } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { renderIcon } from "@/lib/react-icon"

type StatCardProps = {
  title: string
  value: string | number
  description?: string
  icon: ComponentType<{ className?: string }>
}

/** Header stat card (dashboard-style). */
export function StatCard({ title, value, description, icon }: StatCardProps) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        {renderIcon(icon, "h-4 w-4 text-muted-foreground")}
      </CardHeader>
      <CardContent>
        <div className="text-2xl font-bold">{value}</div>
        {description != null && (
          <p className="text-xs text-muted-foreground">{description}</p>
        )}
      </CardContent>
    </Card>
  )
}

type MetricStatCardProps = {
  label: string
  value: string
  icon: ComponentType<{ className?: string }>
  className?: string
}

/** Compact metric card with icon badge (reports-style). */
export function MetricStatCard({
  label,
  value,
  icon,
  className = "bg-primary/10 text-primary",
}: MetricStatCardProps) {
  return (
    <Card>
      <CardContent className="pt-6">
        <div className="flex items-center gap-3">
          <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${className}`}>
            {renderIcon(icon, "h-5 w-5")}
          </div>
          <div>
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-xl font-semibold">{value}</p>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
