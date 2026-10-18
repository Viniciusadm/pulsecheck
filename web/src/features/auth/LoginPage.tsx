import { useMutation, useQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useLocation } from 'react-router'
import { login } from '../../api/auth.ts'
import { getAppConfig } from '../../api/dashboard.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { t } from '../../i18n/index.ts'
import { useAuth } from './AuthContext.ts'

type LocationState = { from?: string; notice?: string } | null

export function LoginPage() {
  const { setUser } = useAuth()
  const state = useLocation().state as LocationState
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const { data: config } = useQuery({ queryKey: queryKeys.config, queryFn: getAppConfig, staleTime: Infinity })

  const mutation = useMutation({
    mutationFn: login,
    onSuccess: (user) => setUser(user),
  })

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
      <h1 className="text-2xl font-semibold text-neutral-900">{t('auth.login.title')}</h1>

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
        <TextField
          label={t('auth.password')}
          type="password"
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

      {config?.registration_enabled !== false && (
        <p className="text-neutral-600">
          {t('auth.login.noAccount')}{' '}
          <Link to="/register" className="font-medium text-primary-700 hover:underline">
            {t('auth.login.register')}
          </Link>
        </p>
      )}
    </div>
  )
}
