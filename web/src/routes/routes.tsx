import { Navigate, type RouteObject } from 'react-router'
import { ForgotPasswordPage } from '../features/auth/ForgotPasswordPage.tsx'
import { LoginPage } from '../features/auth/LoginPage.tsx'
import { RegisterPage } from '../features/auth/RegisterPage.tsx'
import { ResetPasswordPage } from '../features/auth/ResetPasswordPage.tsx'
import { DashboardPage } from '../features/dashboard/DashboardPage.tsx'
import { MonitorDetailPage } from '../features/monitors/MonitorDetailPage.tsx'
import { MonitorsPage } from '../features/monitors/MonitorsPage.tsx'
import { SettingsPage } from '../features/settings/SettingsPage.tsx'
import { AppLayout } from '../layouts/AppLayout.tsx'
import { AuthLayout } from '../layouts/AuthLayout.tsx'
import { RedirectIfAuthenticated, RequireAuth } from './guards.tsx'
import { NotFoundPage } from './NotFoundPage.tsx'

export const routes: RouteObject[] = [
  {
    element: <AuthLayout />,
    children: [
      {
        element: <RedirectIfAuthenticated />,
        children: [
          { path: '/login', element: <LoginPage /> },
          { path: '/register', element: <RegisterPage /> },
          { path: '/forgot-password', element: <ForgotPasswordPage /> },
        ],
      },
      { path: '/reset-password', element: <ResetPasswordPage /> },
    ],
  },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <Navigate to="/dashboard" replace /> },
          { path: '/dashboard', element: <DashboardPage /> },
          { path: '/monitors', element: <MonitorsPage /> },
          { path: '/monitors/:id', element: <MonitorDetailPage /> },
          { path: '/settings', element: <SettingsPage /> },
        ],
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
]
