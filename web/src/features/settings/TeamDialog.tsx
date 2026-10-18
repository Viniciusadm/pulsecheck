import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { createTeam, updateTeam } from '../../api/teams.ts'
import type { Team, TeamInput } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'

type TeamDialogProps = {
  team?: Team
  onClose: () => void
}

export function TeamDialog({ team, onClose }: TeamDialogProps) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [name, setName] = useState(team?.name ?? '')

  const mutation = useMutation({
    mutationFn: (input: TeamInput) => (team ? updateTeam(team.id, input) : createTeam(input)),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.teams })
      showToast(team ? t('settings.teams.form.saved') : t('settings.teams.form.created'))
      onClose()
    },
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    mutation.mutate({ name: name.trim() })
  }

  return (
    <Dialog
      isOpen
      title={team ? t('settings.teams.form.editTitle', { name: team.name }) : t('settings.teams.form.createTitle')}
      onClose={onClose}
      isBusy={mutation.isPending}
    >
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField
          label={t('settings.teams.form.name')}
          name="name"
          autoComplete="off"
          required
          maxLength={100}
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={errors.name}
        />
        <div className="mt-2 flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            isLoading={mutation.isPending}
            loadingLabel={team ? t('settings.teams.form.saving') : t('settings.teams.form.creating')}
          >
            {team ? t('settings.teams.form.save') : t('settings.teams.form.create')}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
