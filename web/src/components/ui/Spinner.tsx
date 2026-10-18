import { t } from '../../i18n/index.ts'

type SpinnerProps = {
  label?: string
  className?: string
}

export function Spinner({ label = t('common.loading'), className = '' }: SpinnerProps) {
  return (
    <span role="status" className={`inline-flex items-center gap-2 ${className}`}>
      <span
        aria-hidden="true"
        className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
      />
      <span className="sr-only">{label}</span>
    </span>
  )
}
