import { useMutation, useQuery } from '@tanstack/react-query'
import { useState, type ChangeEvent, type FormEvent } from 'react'
import { Link } from 'react-router'
import { register } from '../../api/auth.ts'
import { getAppConfig } from '../../api/dashboard.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { t } from '../../i18n/index.ts'
import { useAuth } from './AuthContext.ts'

export function RegisterPage() {
  const { setUser } = useAuth()
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const { data: config } = useQuery({ queryKey: queryKeys.config, queryFn: getAppConfig, staleTime: Infinity })

  const mutation = useMutation({
    mutationFn: register,
    onSuccess: (user) => setUser(user),
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const update = (field: keyof typeof form) => (event: ChangeEvent<HTMLInputElement>) =>
    setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate(form)
    }
  }

  if (config?.registration_enabled === false) {
    return (
      <div className="flex flex-col gap-6">
        <h1 className="text-2xl font-semibold text-neutral-900">{t('auth.register.title')}</h1>
        <Alert tone="info">{t('auth.register.closed')}</Alert>
        <Link to="/login" className="self-start font-medium text-primary-700 hover:underline">
          {t('auth.register.login')}
        </Link>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('auth.register.title')}</h1>

      {generalError && <Alert tone="error">{generalError}</Alert>}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <TextField label={t('auth.name')} name="name" autoComplete="name" required value={form.name} onChange={update('name')} error={errors.name} />
        <TextField
          label={t('auth.email')}
          type="email"
          name="email"
          autoComplete="email"
          required
          value={form.email}
          onChange={update('email')}
          error={errors.email}
        />
        <TextField
          label={t('auth.password')}
          type="password"
          name="password"
          autoComplete="new-password"
          required
          hint={t('auth.register.passwordHint')}
          value={form.password}
          onChange={update('password')}
          error={errors.password}
        />
        <TextField
          label={t('auth.passwordConfirmation')}
          type="password"
          name="password_confirmation"
          autoComplete="new-password"
          required
          value={form.password_confirmation}
          onChange={update('password_confirmation')}
        />
        <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('auth.register.submitting')}>
          {t('auth.register.submit')}
        </Button>
      </form>

      <p className="text-neutral-600">
        {t('auth.register.hasAccount')}{' '}
        <Link to="/login" className="font-medium text-primary-700 hover:underline">
          {t('auth.register.login')}
        </Link>
      </p>
    </div>
  )
}
