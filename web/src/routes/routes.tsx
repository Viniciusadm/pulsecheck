import { Navigate, type RouteObject } from 'react-router'
import { DashboardPage } from '../features/dashboard/DashboardPage.tsx'
import { MonitorDetailPage } from '../features/monitors/MonitorDetailPage.tsx'
import { MonitorCreatePage, MonitorEditPage } from '../features/monitors/MonitorFormPage.tsx'
import { MonitorsPage } from '../features/monitors/MonitorsPage.tsx'
import { AppLayout } from '../layouts/AppLayout.tsx'
import { AuthLayout } from '../layouts/AuthLayout.tsx'
import { PublicLayout } from '../layouts/PublicLayout.tsx'
import {
  ChannelsPage,
  ForgotPasswordPage,
  LazyPage,
  LoginPage,
  MaintenancePage,
  NotificationChannelCreatePage,
  NotificationChannelEditPage,
  PublicStatusPage,
  ResetPasswordPage,
  SettingsPage,
  SetupPage,
  StatusPagesPage,
  TeamCreatePage,
  TeamEditPage,
  TeamsPage,
  UsersPage,
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
        element: <PublicLayout />,
        children: [{ path: '/status/:slug', element: <LazyPage><PublicStatusPage /></LazyPage> }],
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
              { path: '/monitors/new', element: <MonitorCreatePage /> },
              { path: '/monitors/:id', element: <MonitorDetailPage /> },
              { path: '/monitors/:id/edit', element: <MonitorEditPage /> },
              { path: '/channels', element: <LazyPage><ChannelsPage /></LazyPage> },
              { path: '/channels/new', element: <LazyPage><NotificationChannelCreatePage /></LazyPage> },
              { path: '/channels/:id', element: <LazyPage><NotificationChannelEditPage /></LazyPage> },
              { path: '/maintenance', element: <LazyPage><MaintenancePage /></LazyPage> },
              { path: '/status-pages', element: <LazyPage><StatusPagesPage /></LazyPage> },
              { path: '/teams', element: <LazyPage><TeamsPage /></LazyPage> },
              { path: '/teams/new', element: <LazyPage><TeamCreatePage /></LazyPage> },
              { path: '/teams/:id', element: <LazyPage><TeamEditPage /></LazyPage> },
              { path: '/users', element: <LazyPage><UsersPage /></LazyPage> },
              { path: '/settings', element: <LazyPage><SettingsPage /></LazyPage> },
            ],
          },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
