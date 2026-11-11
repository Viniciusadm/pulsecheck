import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useLocation } from 'react-router'
import { completeTwoFactorChallenge, login } from '../../api/auth.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { PasswordField } from '../../components/ui/PasswordField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { t } from '../../i18n/index.ts'
import { useAuth } from './AuthContext.ts'

type LocationState = { from?: string; notice?: string } | null

export function LoginPage() {
  const { setUser } = useAuth()
  const state = useLocation().state as LocationState
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [needsTwoFactor, setNeedsTwoFactor] = useState(false)

  const mutation = useMutation({
    mutationFn: login,
    onSuccess: (result) => {
      if (result.status === 'two-factor') {
        setNeedsTwoFactor(true)
        setPassword('')

        return
      }

      setUser(result.user)
    },
  })

  if (needsTwoFactor) {
    return <TwoFactorChallenge onCancel={() => setNeedsTwoFactor(false)} />
  }

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate({ email, password })
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <h1 className="page-title">{t('auth.login.title')}</h1>

      {state?.notice && <Alert tone="success">{state.notice}</Alert>}
      {generalError && <Alert tone="error">{generalError}</Alert>}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <TextField
          label={t('auth.email')}
          type="email"
          name="email"
          autoComplete="email"
          required
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          error={errors.email}
        />
        <PasswordField
          label={t('auth.password')}
          name="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={errors.password}
        />
        <Link to="/forgot-password" className="self-start text-sm font-medium text-primary-700 hover:underline">
          {t('auth.login.forgotPassword')}
        </Link>
        <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('auth.login.submitting')}>
          {t('auth.login.submit')}
        </Button>
      </form>
    </div>
  )
}

function TwoFactorChallenge({ onCancel }: { onCancel: () => void }) {
  const { setUser } = useAuth()
  const [isUsingRecoveryCode, setIsUsingRecoveryCode] = useState(false)
  const [value, setValue] = useState('')

  const mutation = useMutation({
    mutationFn: completeTwoFactorChallenge,
    onSuccess: (user) => setUser(user),
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate(isUsingRecoveryCode ? { recovery_code: value.trim() } : { code: value.replace(/\s+/g, '') })
    }
  }

  const toggleMode = () => {
    setIsUsingRecoveryCode((current) => !current)
    setValue('')
    mutation.reset()
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="page-title">{t('auth.twoFactor.title')}</h1>
        <p className="text-neutral-600">{isUsingRecoveryCode ? t('auth.twoFactor.recoveryDescription') : t('auth.twoFactor.description')}</p>
      </div>

      {generalError && <Alert tone="error">{generalError}</Alert>}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {isUsingRecoveryCode ? (
          <TextField
            key="recovery"
            label={t('auth.twoFactor.recoveryCode')}
            name="recovery_code"
            autoComplete="off"
            autoFocus
            required
            value={value}
            onChange={(event) => setValue(event.target.value)}
            error={errors.recovery_code ?? errors.code}
          />
        ) : (
          <TextField
            key="code"
            label={t('auth.twoFactor.code')}
            name="code"
            inputMode="numeric"
            autoComplete="one-time-code"
            autoFocus
            required
            maxLength={7}
            value={value}
            onChange={(event) => setValue(event.target.value)}
            error={errors.code}
          />
        )}
        <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('auth.twoFactor.verifying')}>
          {t('auth.twoFactor.verify')}
        </Button>
        <div className="flex flex-wrap justify-between gap-2">
          <button type="button" onClick={toggleMode} className="text-sm font-medium text-primary-700 hover:underline">
            {isUsingRecoveryCode ? t('auth.twoFactor.useCode') : t('auth.twoFactor.useRecoveryCode')}
          </button>
          <button type="button" onClick={onCancel} className="text-sm font-medium text-neutral-600 hover:underline">
            {t('auth.twoFactor.back')}
          </button>
        </div>
      </form>
    </div>
  )
}
