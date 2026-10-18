import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { requestPasswordReset } from '../../api/auth.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { t } from '../../i18n/index.ts'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const mutation = useMutation({ mutationFn: requestPasswordReset })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate(email)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold text-neutral-900">{t('auth.forgotPassword.title')}</h1>
        {!mutation.isSuccess && <p className="text-neutral-600">{t('auth.forgotPassword.description')}</p>}
      </div>

      {mutation.isSuccess ? (
        <Alert tone="success">{t('auth.forgotPassword.sent', { email: mutation.variables })}</Alert>
      ) : (
        <>
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
            <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('auth.forgotPassword.submitting')}>
              {t('auth.forgotPassword.submit')}
            </Button>
          </form>
        </>
      )}

      <Link to="/login" className="self-start font-medium text-primary-700 hover:underline">
        {t('auth.forgotPassword.backToLogin')}
      </Link>
    </div>
  )
}
