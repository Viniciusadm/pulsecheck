import { lazy, Suspense, type ReactNode } from 'react'

export const ChannelsPage = lazy(() => import('../features/channels/ChannelsPage.tsx').then((module) => ({ default: module.ChannelsPage })))
export const NotificationChannelCreatePage = lazy(() =>
  import('../features/channels/NotificationChannelPage.tsx').then((module) => ({ default: module.NotificationChannelCreatePage })),
)
export const NotificationChannelEditPage = lazy(() =>
  import('../features/channels/NotificationChannelPage.tsx').then((module) => ({ default: module.NotificationChannelEditPage })),
)
export const ForgotPasswordPage = lazy(() => import('../features/auth/ForgotPasswordPage.tsx').then((module) => ({ default: module.ForgotPasswordPage })))
export const LoginPage = lazy(() => import('../features/auth/LoginPage.tsx').then((module) => ({ default: module.LoginPage })))
export const MaintenancePage = lazy(() => import('../features/maintenance/MaintenancePage.tsx').then((module) => ({ default: module.MaintenancePage })))
export const PublicStatusPage = lazy(() => import('../features/statusPages/PublicStatusPage.tsx').then((module) => ({ default: module.PublicStatusPage })))
export const StatusPagesPage = lazy(() => import('../features/statusPages/StatusPagesPage.tsx').then((module) => ({ default: module.StatusPagesPage })))
export const ResetPasswordPage = lazy(() => import('../features/auth/ResetPasswordPage.tsx').then((module) => ({ default: module.ResetPasswordPage })))
export const SettingsPage = lazy(() => import('../features/settings/SettingsPage.tsx').then((module) => ({ default: module.SettingsPage })))
export const TeamsPage = lazy(() => import('../features/teams/TeamsPage.tsx').then((module) => ({ default: module.TeamsPage })))
export const TeamCreatePage = lazy(() => import('../features/teams/TeamFormPage.tsx').then((module) => ({ default: module.TeamCreatePage })))
export const TeamEditPage = lazy(() => import('../features/teams/TeamFormPage.tsx').then((module) => ({ default: module.TeamEditPage })))
export const UsersPage = lazy(() => import('../features/users/UsersPage.tsx').then((module) => ({ default: module.UsersPage })))
export const SetupPage = lazy(() => import('../features/setup/SetupPage.tsx').then((module) => ({ default: module.SetupPage })))

export function LazyPage({ children }: { children: ReactNode }) {
  return <Suspense fallback={null}>{children}</Suspense>
}
