import type { ComponentType, SVGProps } from 'react'
import { MonitorsIcon, MoonIcon, SunIcon } from '../../components/ui/icons.tsx'
import { t } from '../../i18n/index.ts'
import { setThemePreference, THEME_PREFERENCES, useThemePreference, type ThemePreference } from '../../lib/theme.ts'
import { AccountSection } from './AccountSection.tsx'

const themeIcons: Record<ThemePreference, ComponentType<SVGProps<SVGSVGElement>>> = {
  system: MonitorsIcon,
  light: SunIcon,
  dark: MoonIcon,
}

export function AppearanceSection() {
  const preference = useThemePreference()

  return (
    <AccountSection id="appearance-heading" title={t('account.appearance.heading')} description={t('account.appearance.description')}>
      <div role="radiogroup" aria-labelledby="appearance-heading" className="grid max-w-md grid-cols-3 gap-2">
        {THEME_PREFERENCES.map((option) => {
          const Icon = themeIcons[option]
          const isSelected = option === preference

          return (
            <button
              key={option}
              type="button"
              role="radio"
              aria-checked={isSelected}
              onClick={() => setThemePreference(option)}
              className={`flex flex-col items-center gap-2 rounded-lg border px-3 py-4 text-sm font-medium transition-colors ${
                isSelected ? 'border-primary-500 bg-primary-50 text-primary-800' : 'border-neutral-300 text-neutral-700 hover:bg-neutral-50'
              }`}
            >
              <Icon className="text-xl" />
              {t(`account.appearance.${option}`)}
            </button>
          )
        })}
      </div>
    </AccountSection>
  )
}
