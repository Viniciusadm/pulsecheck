import { useMutation } from '@tanstack/react-query'
import { useState, type ComponentType, type SVGProps } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router'
import { logout } from '../api/auth.ts'
import { BellIcon, CloseIcon, DashboardIcon, LogOutIcon, MenuIcon, MonitorsIcon, PulseIcon, SettingsIcon } from '../components/ui/icons.tsx'
import { useAuth } from '../features/auth/AuthContext.ts'
import { instanceName, useAppConfig } from '../features/config/ConfigContext.ts'
import { useMonitorRealtime } from '../features/monitors/useMonitorRealtime.ts'
import { t, type MessageKey } from '../i18n/index.ts'
import { isAdmin } from '../lib/permissions.ts'

type NavigationItem = {
  to: string
  label: MessageKey
  icon: ComponentType<SVGProps<SVGSVGElement>>
  isAdminOnly?: boolean
}

const navigation: NavigationItem[] = [
  { to: '/dashboard', label: 'nav.dashboard', icon: DashboardIcon },
  { to: '/monitors', label: 'nav.monitors', icon: MonitorsIcon },
  { to: '/channels', label: 'nav.channels', icon: BellIcon },
  { to: '/settings', label: 'nav.settings', icon: SettingsIcon, isAdminOnly: true },
]

export function AppLayout() {
  const [isMenuOpen, setIsMenuOpen] = useState(false)
  useMonitorRealtime()

  return (
    <div className="min-h-screen lg:pl-72">
      <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-border bg-surface px-4 lg:hidden">
        <Logo />
        <button
          type="button"
          onClick={() => setIsMenuOpen((isOpen) => !isOpen)}
          aria-expanded={isMenuOpen}
          aria-controls="mobile-navigation"
          aria-label={isMenuOpen ? t('nav.closeMenu') : t('nav.openMenu')}
          className="rounded-md p-2 text-lg text-neutral-700 hover:bg-neutral-100"
        >
          {isMenuOpen ? <CloseIcon /> : <MenuIcon />}
        </button>
      </header>

      {isMenuOpen && (
        <div id="mobile-navigation" className="sticky top-14 z-20 border-b border-border bg-surface px-3 py-3 lg:hidden">
          <Navigation onNavigate={() => setIsMenuOpen(false)} />
          <AccountPanel />
        </div>
      )}

      <aside className="fixed inset-y-0 left-0 hidden w-72 flex-col border-r border-border bg-surface px-3 py-5 lg:flex">
        <div className="mb-8 px-3">
          <Logo />
        </div>
        <Navigation />
        <div className="mt-auto">
          <AccountPanel />
        </div>
      </aside>

      <main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-8">
        <Outlet />
      </main>
    </div>
  )
}

function Logo() {
  const config = useAppConfig()

  return (
    <span className="inline-flex min-w-0 items-center gap-2 text-base font-bold text-primary-ink">
      <PulseIcon className="size-5 shrink-0 text-primary-500" />
      <span className="truncate">{instanceName(config, t('app.name'))}</span>
    </span>
  )
}

function Navigation({ onNavigate }: { onNavigate?: () => void }) {
  const { user } = useAuth()
  const items = navigation.filter((item) => !item.isAdminOnly || isAdmin(user))

  return (
    <nav aria-label={t('nav.mainNavigation')}>
      <ul className="flex flex-col gap-0.5">
        {items.map(({ to, label, icon: Icon }) => (
          <li key={to}>
            <NavLink
              to={to}
              onClick={onNavigate}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-md border-l-[3px] px-3 py-2 font-medium ${
                  isActive
                    ? 'border-primary-500 bg-primary-50 text-primary-800'
                    : 'border-transparent text-neutral-700 hover:bg-neutral-100'
                }`
              }
            >
              <Icon className="text-lg" />
              {t(label)}
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  )
}

function AccountPanel() {
  const { user, setUser } = useAuth()
  const navigate = useNavigate()
  const logoutMutation = useMutation({
    mutationFn: logout,
    onSettled: () => {
      setUser(null)
      navigate('/login', { replace: true })
    },
  })

  return (
    <div className="mt-3 flex items-center justify-between gap-2 border-t border-border px-3 pt-3">
      <div className="min-w-0">
        <p className="truncate font-medium text-neutral-900">{user?.name}</p>
        <p className="truncate text-xs text-neutral-500" title={user?.email}>{user?.email}</p>
      </div>
      <button
        type="button"
        onClick={() => logoutMutation.mutate()}
        disabled={logoutMutation.isPending}
        aria-label={t('nav.logOut')}
        title={t('nav.logOut')}
        className="shrink-0 rounded-md p-2 text-lg text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 disabled:opacity-60"
      >
        <LogOutIcon />
      </button>
    </div>
  )
}
