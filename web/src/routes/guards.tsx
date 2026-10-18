import { Navigate, Outlet, useLocation } from 'react-router'
import { Spinner } from '../components/ui/Spinner.tsx'
import { useAuth } from '../features/auth/AuthContext.ts'

function FullPageSpinner() {
  return (
    <div className="flex min-h-screen items-center justify-center text-primary-600">
      <Spinner />
    </div>
  )
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
