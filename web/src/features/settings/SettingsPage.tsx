import { t } from '../../i18n/index.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { ChannelsSection } from './ChannelsSection.tsx'
import { InstanceSection } from './InstanceSection.tsx'
import { UsersSection } from './UsersSection.tsx'

export function SettingsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('settings.title')}</h1>
      <ChannelsSection />
      {isAdmin && <InstanceSection />}
      {isAdmin && <UsersSection />}
    </div>
  )
}
