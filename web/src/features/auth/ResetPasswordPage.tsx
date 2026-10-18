import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { resetPassword } from '../../api/auth.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { t } from '../../i18n/index.ts'

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')

  const mutation = useMutation({
    mutationFn: resetPassword,
    onSuccess: () => navigate('/login', { replace: true, state: { notice: t('auth.resetPassword.success') } }),
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  if (token === '' || email === '') {
    return (
      <div className="flex flex-col gap-6">
        <h1 className="text-2xl font-semibold text-neutral-900">{t('auth.resetPassword.title')}</h1>
        <Alert tone="error">{t('auth.resetPassword.missingToken')}</Alert>
        <Link to="/forgot-password" className="self-start font-medium text-primary-700 hover:underline">
          {t('auth.resetPassword.requestNewLink')}
        </Link>
      </div>
    )
  }

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate({ token, email, password, password_confirmation: passwordConfirmation })
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('auth.resetPassword.title')}</h1>

      {generalError && <Alert tone="error">{generalError}</Alert>}
      {errors.email && (
        <Alert tone="error">
          {errors.email}{' '}
          <Link to="/forgot-password" className="font-medium underline">
            {t('auth.resetPassword.requestNewLink')}
          </Link>
        </Alert>
      )}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <TextField label={t('auth.email')} type="email" name="email" autoComplete="username" value={email} readOnly />
        <TextField
          label={t('auth.password')}
          type="password"
          name="password"
          autoComplete="new-password"
          required
          hint={t('auth.register.passwordHint')}
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={errors.password}
        />
        <TextField
          label={t('auth.passwordConfirmation')}
          type="password"
          name="password_confirmation"
          autoComplete="new-password"
          required
          value={passwordConfirmation}
          onChange={(event) => setPasswordConfirmation(event.target.value)}
        />
        <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('auth.resetPassword.submitting')}>
          {t('auth.resetPassword.submit')}
        </Button>
      </form>
    </div>
  )
}
