import { Outlet } from 'react-router'
import { PulseIcon } from '../components/ui/icons.tsx'
import { instanceName, useAppConfig } from '../features/config/ConfigContext.ts'
import { t } from '../i18n/index.ts'
import { useLocale } from '../i18n/useLocale.ts'

const TICK_COUNT = 36
const FAILED_TICKS = new Set([23, 24])

function CheckTicks() {
  return (
    <div aria-hidden="true" className="flex h-16 items-end gap-1">
      {Array.from({ length: TICK_COUNT }, (_, index) => (
        <span
          key={index}
          className={`w-1.5 origin-bottom rounded-sm ${FAILED_TICKS.has(index) ? 'h-6 bg-red-400' : 'h-16 bg-green-400'}`}
          style={{ animation: `tick-in 320ms ease-out ${index * 18}ms both` }}
        />
      ))}
    </div>
  )
}

export function AuthLayout() {
  useLocale()

  return (
    <div className="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <aside className="hidden flex-col justify-between bg-primary-ink px-12 py-12 text-white lg:flex">
        <Logo className="text-white" />
        <div className="flex max-w-md flex-col gap-6">
          <CheckTicks />
          <p className="text-2xl font-semibold leading-snug">{t('auth.tagline')}</p>
        </div>
      </aside>
      <main className="flex flex-col px-4 py-10 sm:px-8 lg:justify-center lg:px-16">
        <Logo className="mb-10 text-primary-ink lg:hidden" />
        <div className="w-full max-w-sm">
          <Outlet />
        </div>
      </main>
    </div>
  )
}

function Logo({ className = '' }: { className?: string }) {
  const config = useAppConfig()

  return (
    <span className={`inline-flex items-center gap-2 text-lg font-bold ${className}`}>
      <PulseIcon className="size-6 text-primary-500" />
      {instanceName(config, t('app.name'))}
    </span>
  )
}
