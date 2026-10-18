import { Link } from 'react-router'
import { t } from '../i18n/index.ts'

export function NotFoundPage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-3 px-4">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('notFound.title')}</h1>
      <p className="text-neutral-600">{t('notFound.description')}</p>
      <Link to="/dashboard" className="font-medium text-primary-700 hover:underline">
        {t('notFound.goHome')}
      </Link>
    </main>
  )
}
