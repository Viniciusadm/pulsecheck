import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router'
import { listAllMonitors } from '../../api/allMonitors.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { createTeam, getTeam, updateTeam } from '../../api/teams.ts'
import type { Team, TeamInput } from '../../api/types.ts'
import { listUsers } from '../../api/users.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { FormSection } from '../../components/ui/FormSection.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { isAdmin } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { MemberList } from './MemberList.tsx'

export function TeamCreatePage() {
  const { user } = useAuth()

  if (!isAdmin(user)) {
    return <Navigate to="/dashboard" replace />
  }

  return <TeamFormLayout />
}

export function TeamEditPage() {
  const { user } = useAuth()
  const teamId = Number(useParams().id)
  const team = useQuery({ queryKey: queryKeys.team(teamId), queryFn: () => getTeam(teamId), enabled: isAdmin(user), retry: false })

  if (!isAdmin(user)) {
    return <Navigate to="/dashboard" replace />
  }

  if (team.isPending) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-96" />
      </div>
    )
  }

  if (team.isError) {
    return (
      <EmptyState
        title={t('settings.teams.page.notFound')}
        action={
          <Link to="/teams" className="font-medium text-primary-700 hover:underline">
            {t('settings.teams.page.back')}
          </Link>
        }
      />
    )
  }

  return <TeamFormLayout key={team.data.id} team={team.data} />
}

function TeamFormLayout({ team }: { team?: Team }) {
  const navigate = useNavigate()

  return (
    <div className="flex max-w-4xl flex-col gap-6">
      <div className="flex flex-col gap-4">
        <Link to="/teams" className="self-start text-sm font-medium text-primary-700 hover:underline">
          {t('settings.teams.page.back')}
        </Link>
        <h1 className="text-2xl font-semibold text-neutral-900">
          {team ? t('settings.teams.form.editTitle', { name: team.name }) : t('settings.teams.form.createTitle')}
        </h1>
      </div>
      <TeamForm
        team={team}
        onSaved={(saved) => navigate(team ? '/teams' : `/teams/${saved.id}`, { replace: !team })}
        onCancel={() => navigate('/teams')}
      />
    </div>
  )
}

type TeamFormProps = {
  team?: Team
  onSaved: (team: Team) => void
  onCancel: () => void
}

function TeamForm({ team, onSaved, onCancel }: TeamFormProps) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const users = useQuery({ queryKey: queryKeys.users, queryFn: listUsers })
  const monitors = useQuery({ queryKey: queryKeys.allMonitors, queryFn: listAllMonitors })
  const [name, setName] = useState(team?.name ?? '')
  const [userIds, setUserIds] = useState<number[]>(team?.user_ids ?? [])
  const [monitorIds, setMonitorIds] = useState<number[]>(team?.monitor_ids ?? [])

  const mutation = useMutation({
    mutationFn: (input: TeamInput) => (team ? updateTeam(team.id, input) : createTeam(input)),
    onSuccess: (saved) => {
      queryClient.setQueryData(queryKeys.team(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: queryKeys.teams })
      void queryClient.invalidateQueries({ queryKey: queryKeys.users })
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
      showToast(team ? t('settings.teams.form.saved') : t('settings.teams.form.created'))
      onSaved(saved)
    },
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null
  const memberCandidates = (users.data ?? []).filter((candidate) => candidate.role !== 'admin')
  const orphanedMonitors = (monitors.data ?? []).filter(
    (monitor) => team?.monitor_ids?.includes(monitor.id) && !monitorIds.includes(monitor.id) && monitor.team_ids.every((id) => id === team.id),
  )

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (mutation.isPending) {
      return
    }

    mutation.mutate({ name: name.trim(), user_ids: userIds, monitor_ids: monitorIds })
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
      {generalError && <Alert tone="error">{generalError}</Alert>}

      <FormSection title={t('settings.teams.form.details')}>
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
      </FormSection>

      <FormSection title={t('settings.teams.form.members')} description={t('settings.teams.form.membersHint')}>
        {users.isPending && <Skeleton className="h-20" />}
        {users.isError && <ErrorState onRetry={() => void users.refetch()} />}
        {users.data && (
          <MemberList
            items={memberCandidates.map((candidate) => ({ id: candidate.id, name: candidate.name, detail: candidate.email }))}
            selectedIds={userIds}
            onChange={setUserIds}
            addLabel={t('settings.teams.form.addMember')}
            emptyLabel={t('settings.teams.form.noMembers')}
            noneLeftLabel={t('settings.teams.form.noMembersLeft')}
            error={errors.user_ids ?? Object.entries(errors).find(([key]) => key.startsWith('user_ids.'))?.[1]}
          />
        )}
      </FormSection>

      <FormSection title={t('settings.teams.form.monitors')} description={t('settings.teams.form.monitorsHint')}>
        {monitors.isPending && <Skeleton className="h-20" />}
        {monitors.isError && <ErrorState onRetry={() => void monitors.refetch()} />}
        {monitors.data && (
          <MemberList
            items={monitors.data.map((monitor) => ({ id: monitor.id, name: monitor.name }))}
            selectedIds={monitorIds}
            onChange={setMonitorIds}
            addLabel={t('settings.teams.form.addMonitor')}
            emptyLabel={t('settings.teams.form.noMonitors')}
            noneLeftLabel={t('settings.teams.form.noMonitorsLeft')}
            error={errors.monitor_ids ?? Object.entries(errors).find(([key]) => key.startsWith('monitor_ids.'))?.[1]}
          />
        )}
        {orphanedMonitors.length > 0 && (
          <Alert>{t('settings.teams.form.orphanedMonitors', { names: orphanedMonitors.map((monitor) => monitor.name).join(', ') })}</Alert>
        )}
      </FormSection>

      <div className="flex flex-wrap justify-end gap-2">
        <Button variant="secondary" onClick={onCancel} disabled={mutation.isPending}>
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
  )
}
