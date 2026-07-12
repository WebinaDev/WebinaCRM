import { type LucideIcon } from 'lucide-react'
import { NavLink } from 'react-router-dom'

import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'

function isInternalPath(url: string): boolean {
  return url.startsWith('/') && !url.startsWith('//')
}

export function NavProjects({
  projects,
  groupLabel,
}: {
  projects: {
    name: string
    url: string
    icon: LucideIcon
  }[]
  groupLabel: string
}) {
  if (!projects.length) return null

  return (
    <SidebarGroup className="group-data-[collapsible=icon]:hidden">
      <SidebarGroupLabel>{groupLabel}</SidebarGroupLabel>
      <SidebarMenu>
        {projects.map((item) => (
          <SidebarMenuItem key={item.name}>
            <SidebarMenuButton asChild>
              {isInternalPath(item.url) ? (
                <NavLink to={item.url}>
                  <item.icon />
                  <span>{item.name}</span>
                </NavLink>
              ) : (
                <a href={item.url}>
                  <item.icon />
                  <span>{item.name}</span>
                </a>
              )}
            </SidebarMenuButton>
          </SidebarMenuItem>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  )
}
