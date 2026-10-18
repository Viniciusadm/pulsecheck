import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type ChangeEvent, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { User, UserInput, UserRole } from '../../api/types.ts'
import { createUser, updateUser } from '../../api/users.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { useAuth } from '../auth/AuthContext.ts'

const roles: UserRole[] = ['member', 'admin']

type UserDialogProps = {
  user?: User
  onClose: () => void
}

export function UserDialog({ user, onClose }: UserDialogProps) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const { user: currentUser, setUser } = useAuth()
  const [form, setForm] = useState({
    name: user?.name ?? '',
    email: user?.email ?? '',
    role: user?.role ?? ('member' as UserRole),
    password: '',
    password_confirmation: '',
  })

  const mutation = useMutation({
    mutationFn: (input: UserInput) => (user ? updateUser(user.id, input) : createUser(input)),
    onSuccess: (saved) => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.users })

      if (saved.id === currentUser?.id) {
        setUser(saved)
      }

      showToast(user ? t('settings.users.form.saved') : t('settings.users.form.created'))
      onClose()
    },
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const update = (field: 'name' | 'email' | 'password' | 'password_confirmation') => (event: ChangeEvent<HTMLInputElement>) =>
    setForm((current) => ({ ...current, [field]: event.target.value }))

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    const { password, password_confirmation, ...details } = form
    const input: UserInput = { ...details, name: details.name.trim(), email: details.email.trim() }

    if (!user || password !== '') {
      input.password = password
      input.password_confirmation = password_confirmation
    }

    mutation.mutate(input)
  }

  return (
    <Dialog
      isOpen
      title={user ? t('settings.users.form.editTitle', { name: user.name }) : t('settings.users.form.createTitle')}
      onClose={onClose}
    >
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField label={t('settings.users.form.name')} name="name" autoComplete="off" required value={form.name} onChange={update('name')} error={errors.name} />
        <TextField
          label={t('settings.users.form.email')}
          type="email"
          name="email"
          autoComplete="off"
          required
          value={form.email}
          onChange={update('email')}
          error={errors.email}
        />
        <SelectField
          label={t('settings.users.form.role')}
          name="role"
          value={form.role}
          onChange={(event) => setForm((current) => ({ ...current, role: event.target.value as UserRole }))}
          error={errors.role}
        >
          {roles.map((role) => (
            <option key={role} value={role}>
              {t(`settings.users.roles.${role}`)}
            </option>
          ))}
        </SelectField>
        <TextField
          label={t('settings.users.form.password')}
          type="password"
          name="password"
          autoComplete="new-password"
          required={!user}
          hint={user ? t('settings.users.form.passwordKeep') : t('auth.passwordHint')}
          value={form.password}
          onChange={update('password')}
          error={errors.password}
        />
        <TextField
          label={t('settings.users.form.passwordConfirmation')}
          type="password"
          name="password_confirmation"
          autoComplete="new-password"
          required={!user}
          value={form.password_confirmation}
          onChange={update('password_confirmation')}
          error={errors.password_confirmation}
        />
        <div className="mt-2 flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            isLoading={mutation.isPending}
            loadingLabel={user ? t('settings.users.form.saving') : t('settings.users.form.creating')}
          >
            {user ? t('settings.users.form.save') : t('settings.users.form.create')}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
