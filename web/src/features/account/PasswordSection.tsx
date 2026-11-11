import { useMutation } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { updatePassword } from '../../api/account.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { PasswordField } from '../../components/ui/PasswordField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { AccountSection } from './AccountSection.tsx'

const EMPTY_FORM = { current_password: '', password: '', password_confirmation: '' }

export function PasswordSection() {
  const { showToast } = useToast()
  const [form, setForm] = useState(EMPTY_FORM)

  const mutation = useMutation({
    mutationFn: updatePassword,
    onSuccess: () => {
      setForm(EMPTY_FORM)
      showToast(t('account.password.saved'))
    },
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate(form)
    }
  }

  return (
    <AccountSection id="password-heading" title={t('account.password.heading')} description={t('account.password.description')}>
      <form onSubmit={handleSubmit} noValidate className="flex max-w-md flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <PasswordField
          label={t('account.currentPassword')}
          name="current_password"
          autoComplete="current-password"
          required
          value={form.current_password}
          onChange={(event) => setForm({ ...form, current_password: event.target.value })}
          error={errors.current_password}
        />
        <PasswordField
          label={t('account.password.new')}
          name="password"
          autoComplete="new-password"
          required
          hint={t('auth.passwordHint')}
          value={form.password}
          onChange={(event) => setForm({ ...form, password: event.target.value })}
          error={errors.password}
        />
        <PasswordField
          label={t('auth.passwordConfirmation')}
          name="password_confirmation"
          autoComplete="new-password"
          required
          value={form.password_confirmation}
          onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })}
        />
        <div>
          <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('account.saving')}>
            {t('account.password.submit')}
          </Button>
        </div>
      </form>
    </AccountSection>
  )
}
