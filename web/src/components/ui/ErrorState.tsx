import { t } from '../../i18n/index.ts'
import { Button } from './Button.tsx'

type ErrorStateProps = {
  message?: string
  onRetry?: () => void
}

export function ErrorState({ message = t('common.genericError'), onRetry }: ErrorStateProps) {
  return (
    <div role="alert" className="flex flex-col items-start gap-3 rounded-lg border border-status-down/30 bg-status-down-soft px-6 py-6">
      <p className="text-red-800">{message}</p>
      {onRetry && (
        <Button variant="secondary" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      )}
    </div>
  )
}
