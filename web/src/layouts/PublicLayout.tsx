import { Outlet } from 'react-router'
import { t } from '../i18n/index.ts'
import { useLocale } from '../i18n/useLocale.ts'

export function PublicLayout() {
  useLocale()

  return (
    <div className="flex min-h-screen flex-col">
      <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col px-4 py-10 sm:px-6">
        <Outlet />
      </main>
      <footer className="px-4 py-6 text-center text-xs text-neutral-500">{t('publicStatus.poweredBy')}</footer>
    </div>
  )
}
