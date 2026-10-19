import { Navigate } from 'react-router'
import { t } from '../../i18n/index.ts'
import { isAdmin } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { InstanceSection } from './InstanceSection.tsx'
import { MailSection } from './MailSection.tsx'
import { TeamsSection } from './TeamsSection.tsx'
import { UsersSection } from './UsersSection.tsx'

export function SettingsPage() {
  const { user } = useAuth()

  if (!isAdmin(user)) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('settings.title')}</h1>
      <InstanceSection />
      <MailSection />
      <TeamsSection />
      <UsersSection />
    </div>
  )
}
