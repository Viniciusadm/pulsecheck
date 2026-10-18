import { lazy, Suspense, type ReactNode } from 'react'

export const ChannelsPage = lazy(() => import('../features/channels/ChannelsPage.tsx').then((module) => ({ default: module.ChannelsPage })))
export const ForgotPasswordPage = lazy(() => import('../features/auth/ForgotPasswordPage.tsx').then((module) => ({ default: module.ForgotPasswordPage })))
export const LoginPage = lazy(() => import('../features/auth/LoginPage.tsx').then((module) => ({ default: module.LoginPage })))
export const ResetPasswordPage = lazy(() => import('../features/auth/ResetPasswordPage.tsx').then((module) => ({ default: module.ResetPasswordPage })))
export const SettingsPage = lazy(() => import('../features/settings/SettingsPage.tsx').then((module) => ({ default: module.SettingsPage })))
export const SetupPage = lazy(() => import('../features/setup/SetupPage.tsx').then((module) => ({ default: module.SetupPage })))

export function LazyPage({ children }: { children: ReactNode }) {
  return <Suspense fallback={null}>{children}</Suspense>
}
