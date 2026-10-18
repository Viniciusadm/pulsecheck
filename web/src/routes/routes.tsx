import { Navigate, type RouteObject } from 'react-router'
import { ChannelsPage } from '../features/channels/ChannelsPage.tsx'
import { ForgotPasswordPage } from '../features/auth/ForgotPasswordPage.tsx'
import { LoginPage } from '../features/auth/LoginPage.tsx'
import { ResetPasswordPage } from '../features/auth/ResetPasswordPage.tsx'
import { DashboardPage } from '../features/dashboard/DashboardPage.tsx'
import { MonitorDetailPage } from '../features/monitors/MonitorDetailPage.tsx'
import { MonitorsPage } from '../features/monitors/MonitorsPage.tsx'
import { SettingsPage } from '../features/settings/SettingsPage.tsx'
import { SetupPage } from '../features/setup/SetupPage.tsx'
import { AppLayout } from '../layouts/AppLayout.tsx'
import { AuthLayout } from '../layouts/AuthLayout.tsx'
import { RedirectIfAuthenticated, RedirectIfSetupDone, RequireAuth, RequireSetup } from './guards.tsx'
import { NotFoundPage } from './NotFoundPage.tsx'

export const routes: RouteObject[] = [
  {
    element: <RedirectIfSetupDone />,
    children: [
      {
        element: <AuthLayout />,
        children: [{ path: '/setup', element: <SetupPage /> }],
      },
    ],
  },
  {
    element: <RequireSetup />,
    children: [
      {
        element: <AuthLayout />,
        children: [
          {
            element: <RedirectIfAuthenticated />,
            children: [
              { path: '/login', element: <LoginPage /> },
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
              { path: '/channels', element: <ChannelsPage /> },
              { path: '/settings', element: <SettingsPage /> },
            ],
          },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
