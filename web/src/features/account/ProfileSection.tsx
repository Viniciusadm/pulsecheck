import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { updateProfile } from '../../api/account.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { PasswordField } from '../../components/ui/PasswordField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { AccountSection } from './AccountSection.tsx'

export function ProfileSection() {
  const { user, setUser } = useAuth()
  const { showToast } = useToast()
  const [name, setName] = useState(user?.name ?? '')
  const [email, setEmail] = useState(user?.email ?? '')
  const [currentPassword, setCurrentPassword] = useState('')
  const changesEmail = email.trim().toLowerCase() !== (user?.email ?? '')

  const mutation = useMutation({
    mutationFn: updateProfile,
    onSuccess: (updated) => {
      setUser(updated)
      setCurrentPassword('')
      showToast(t('account.profile.saved'))
    },
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate({ name: name.trim(), email: email.trim(), ...(changesEmail ? { current_password: currentPassword } : {}) })
    }
  }

  return (
    <AccountSection id="profile-heading" title={t('account.profile.heading')} description={t('account.profile.description')}>
      <form onSubmit={handleSubmit} noValidate className="flex max-w-md flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField label={t('auth.name')} name="name" autoComplete="name" required maxLength={255} value={name} onChange={(event) => setName(event.target.value)} error={errors.name} />
        <TextField label={t('auth.email')} type="email" name="email" autoComplete="email" required value={email} onChange={(event) => setEmail(event.target.value)} error={errors.email} />
        {changesEmail && (
          <PasswordField
            label={t('account.currentPassword')}
            name="current_password"
            autoComplete="current-password"
            hint={t('account.profile.passwordToChangeEmail')}
            value={currentPassword}
            onChange={(event) => setCurrentPassword(event.target.value)}
            error={errors.current_password}
          />
        )}
        <div>
          <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('account.saving')}>
            {t('account.save')}
          </Button>
        </div>
      </form>
    </AccountSection>
  )
}
