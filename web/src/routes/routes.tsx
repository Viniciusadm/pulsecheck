import { Navigate, type RouteObject } from 'react-router'
import { DashboardPage } from '../features/dashboard/DashboardPage.tsx'
import { MonitorDetailPage } from '../features/monitors/MonitorDetailPage.tsx'
import { MonitorsPage } from '../features/monitors/MonitorsPage.tsx'
import { AppLayout } from '../layouts/AppLayout.tsx'
import { AuthLayout } from '../layouts/AuthLayout.tsx'
import {
  ChannelsPage,
  ForgotPasswordPage,
  LazyPage,
  LoginPage,
  MaintenancePage,
  ResetPasswordPage,
  SettingsPage,
  SetupPage,
} from './lazyPages.tsx'
import { RedirectIfAuthenticated, RedirectIfSetupDone, RequireAuth, RequireSetup } from './guards.tsx'
import { NotFoundPage } from './NotFoundPage.tsx'

export const routes: RouteObject[] = [
  {
    element: <RedirectIfSetupDone />,
    children: [
      {
        element: <AuthLayout />,
        children: [{ path: '/setup', element: <LazyPage><SetupPage /></LazyPage> }],
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
              { path: '/login', element: <LazyPage><LoginPage /></LazyPage> },
              { path: '/forgot-password', element: <LazyPage><ForgotPasswordPage /></LazyPage> },
            ],
          },
          { path: '/reset-password', element: <LazyPage><ResetPasswordPage /></LazyPage> },
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
              { path: '/channels', element: <LazyPage><ChannelsPage /></LazyPage> },
              { path: '/maintenance', element: <LazyPage><MaintenancePage /></LazyPage> },
              { path: '/settings', element: <LazyPage><SettingsPage /></LazyPage> },
            ],
          },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
