import { useId, useState, type InputHTMLAttributes } from 'react'
import { t } from '../../i18n/index.ts'
import { EyeIcon, EyeOffIcon } from './icons.tsx'
import { inputClassName } from './inputClassName.ts'

type PasswordFieldProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: string
  error?: string
  hint?: string
}

export function PasswordField({ label, error, hint, id, className = '', ...props }: PasswordFieldProps) {
  const generatedId = useId()
  const [isVisible, setIsVisible] = useState(false)
  const inputId = id ?? generatedId
  const hintId = hint ? `${inputId}-hint` : undefined
  const errorId = error ? `${inputId}-error` : undefined

  return (
    <div className={`flex flex-col gap-1.5 ${className}`}>
      <label htmlFor={inputId} className="text-sm font-medium text-neutral-800">
        {label}
      </label>
      <div className="relative flex flex-col">
        <input
          id={inputId}
          type={isVisible ? 'text' : 'password'}
          aria-invalid={error ? true : undefined}
          aria-describedby={[hintId, errorId].filter(Boolean).join(' ') || undefined}
          className={`${inputClassName(error)} pr-10`}
          {...props}
        />
        <button
          type="button"
          aria-label={isVisible ? t('auth.hidePassword') : t('auth.showPassword')}
          aria-pressed={isVisible}
          onClick={() => setIsVisible((current) => !current)}
          className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-neutral-500 hover:text-neutral-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-200"
        >
          {isVisible ? <EyeOffIcon className="size-4" /> : <EyeIcon className="size-4" />}
        </button>
      </div>
      {hint && !error && (
        <p id={hintId} className="text-xs text-neutral-500">
          {hint}
        </p>
      )}
      {error && (
        <p id={errorId} className="text-xs font-medium text-status-down">
          {error}
        </p>
      )}
    </div>
  )
}
