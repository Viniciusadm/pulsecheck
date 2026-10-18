import { Navigate, Outlet, useLocation } from 'react-router'
import { FullPageSpinner } from '../components/ui/FullPageSpinner.tsx'
import { useAuth } from '../features/auth/AuthContext.ts'
import { useAppConfig } from '../features/config/ConfigContext.ts'

export function RequireSetup() {
  const { setup_required: isSetupRequired } = useAppConfig()

  if (isSetupRequired) {
    return <Navigate to="/setup" replace />
  }

  return <Outlet />
}

export function RedirectIfSetupDone() {
  const { setup_required: isSetupRequired } = useAppConfig()

  if (!isSetupRequired) {
    return <Navigate to="/login" replace />
  }

  return <Outlet />
}

export function RequireAuth() {
  const { user, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <FullPageSpinner />
  }

  if (user === null) {
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }

  return <Outlet />
}

export function RedirectIfAuthenticated() {
  const { user, isLoading } = useAuth()
  const from = (useLocation().state as { from?: string } | null)?.from

  if (isLoading) {
    return <FullPageSpinner />
  }

  if (user !== null) {
    return <Navigate to={from ?? '/dashboard'} replace />
  }

  return <Outlet />
}
