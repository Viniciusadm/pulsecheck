import { t } from '../../i18n/index.ts'
import { ApiTokensSection } from './ApiTokensSection.tsx'
import { AppearanceSection } from './AppearanceSection.tsx'
import { PasswordSection } from './PasswordSection.tsx'
import { ProfileSection } from './ProfileSection.tsx'
import { TwoFactorSection } from './TwoFactorSection.tsx'

export function AccountPage() {
  return (
    <div className="flex max-w-3xl flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold text-neutral-900">{t('account.title')}</h1>
        <p className="text-neutral-600">{t('account.description')}</p>
      </div>
      <ProfileSection />
      <AppearanceSection />
      <PasswordSection />
      <TwoFactorSection />
      <ApiTokensSection />
    </div>
  )
}
