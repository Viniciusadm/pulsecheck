import { useState, type FormEvent } from 'react'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { PasswordField } from '../../components/ui/PasswordField.tsx'
import { t } from '../../i18n/index.ts'

type PasswordPromptDialogProps = {
  title: string
  description: string
  confirmLabel: string
  pendingLabel: string
  isPending: boolean
  error?: string
  isDanger?: boolean
  onConfirm: (password: string) => void
  onClose: () => void
}

export function PasswordPromptDialog({ title, description, confirmLabel, pendingLabel, isPending, error, isDanger = false, onConfirm, onClose }: PasswordPromptDialogProps) {
  const [password, setPassword] = useState('')

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!isPending && password !== '') {
      onConfirm(password)
    }
  }

  return (
    <Dialog isOpen title={title} onClose={onClose} isBusy={isPending}>
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <p>{description}</p>
        <PasswordField
          label={t('account.currentPassword')}
          name="current_password"
          autoComplete="current-password"
          data-autofocus
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          error={error}
        />
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={isPending}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" variant={isDanger ? 'danger' : 'primary'} isLoading={isPending} loadingLabel={pendingLabel} disabled={password === ''}>
            {confirmLabel}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
