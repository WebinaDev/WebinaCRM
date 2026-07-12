import { ChevronsUpDown, LogOut, User } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length >= 2) return (parts[0]![0]! + parts[1]![0]!).toUpperCase()
  if (parts[0]?.length) return parts[0].slice(0, 2).toUpperCase()
  return '?'
}

export function NavUser({
  user,
  logoutLabel,
  onLogout,
}: {
  user: {
    name: string
    email: string
    avatar?: string
  }
  logoutLabel: string
  onLogout: () => void
}) {
  const { isMobile } = useSidebar()
  const { i18n, t } = useTranslation()
  const av = user.avatar?.trim()
  const dir = i18n.dir()
  const isRtl = dir === 'rtl'

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <Avatar className="h-8 w-8 rounded-lg">
                {av ? <AvatarImage src={av} alt={user.name} /> : null}
                <AvatarFallback className="rounded-lg">{initials(user.name)}</AvatarFallback>
              </Avatar>
              <div className={`grid flex-1 text-sm leading-tight ${isRtl ? 'text-right' : 'text-left'}`}>
                <span className="truncate font-semibold">{user.name}</span>
                <span className="truncate text-xs text-sidebar-foreground/70">{user.email}</span>
              </div>
              <ChevronsUpDown className="ms-auto size-4" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
            side={isMobile ? 'bottom' : isRtl ? 'left' : 'right'}
            align="end"
            sideOffset={4}
          >
            <div dir={dir}>
              <DropdownMenuLabel className="p-0 font-normal">
                <div className={`flex items-center gap-2 px-1 py-1.5 text-sm ${isRtl ? 'text-right' : 'text-left'}`}>
                  <Avatar className="h-8 w-8 rounded-lg">
                    {av ? <AvatarImage src={av} alt={user.name} /> : null}
                    <AvatarFallback className="rounded-lg">{initials(user.name)}</AvatarFallback>
                  </Avatar>
                  <div className="grid flex-1 text-sm leading-tight">
                    <span className="truncate font-semibold">{user.name}</span>
                    <span className="truncate text-xs text-muted-foreground">{user.email}</span>
                  </div>
                </div>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem asChild>
                <Link to="/profile">
                  <User />
                  {t('layout.profile')}
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem
                onSelect={(e) => {
                  e.preventDefault()
                  onLogout()
                }}
              >
                <LogOut />
                {logoutLabel}
              </DropdownMenuItem>
            </div>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
