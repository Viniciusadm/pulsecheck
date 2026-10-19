import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router'
import { generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { deleteTeam, listTeams } from '../../api/teams.ts'
import type { Team } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { isAdmin } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'

export function TeamsPage() {
  const { user } = useAuth()
  const teams = useQuery({ queryKey: queryKeys.teams, queryFn: listTeams, enabled: isAdmin(user) })
  const navigate = useNavigate()

  if (!isAdmin(user)) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex max-w-prose flex-col gap-1">
          <h1 className="text-2xl font-semibold text-neutral-900">{t('settings.teams.heading')}</h1>
          <p className="text-neutral-600">{t('settings.teams.description')}</p>
        </div>
        {teams.data && teams.data.length > 0 && (
          <Button onClick={() => navigate('/teams/new')}>{t('settings.teams.add')}</Button>
        )}
      </div>
      <Card className="flex flex-col gap-6 px-5 py-6 sm:px-6">
        {teams.isPending && <Skeleton className="h-32" />}
        {teams.isError && <ErrorState onRetry={() => void teams.refetch()} />}
        {teams.data?.length === 0 && (
          <EmptyState
            title={t('settings.teams.emptyTitle')}
            description={t('settings.teams.emptyDescription')}
            action={<Button onClick={() => navigate('/teams/new')}>{t('settings.teams.add')}</Button>}
          />
        )}
        {teams.data && teams.data.length > 0 && (
          <ul className="divide-y divide-border rounded-lg border border-border">
            {teams.data.map((team) => (
              <TeamRow key={team.id} team={team} onEdit={() => navigate(`/teams/${team.id}`)} />
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}

function TeamRow({ team, onEdit }: { team: Team; onEdit: () => void }) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)

  const deleteMutation = useMutation({
    mutationFn: () => deleteTeam(team.id),
    onSuccess: () => {
      setIsConfirmingDelete(false)
      void queryClient.invalidateQueries({ queryKey: queryKeys.teams })
      void queryClient.invalidateQueries({ queryKey: queryKeys.users })
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
      showToast(t('settings.teams.deleted'))
    },
    onError: (error) => {
      setIsConfirmingDelete(false)
      showToast(generalErrorMessage(error) ?? t('common.genericError'), 'error')
    },
  })

  return (
    <li className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 flex-col gap-1">
        <Link to={`/teams/${team.id}`} className="truncate font-semibold text-neutral-900 hover:underline">
          {team.name}
        </Link>
        <p className="text-xs text-neutral-500">
          {t('settings.teams.usage', { users: team.users_count ?? 0, monitors: team.monitors_count ?? 0 })}
        </p>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onClick={onEdit} aria-label={`${t('settings.teams.manage')}: ${team.name}`}>
          {t('settings.teams.manage')}
        </Button>
        <Button
          variant="ghost"
          className="text-status-down"
          onClick={() => setIsConfirmingDelete(true)}
          aria-label={`${t('settings.teams.delete')}: ${team.name}`}
        >
          {t('settings.teams.delete')}
        </Button>
      </div>
      <ConfirmDialog
        isOpen={isConfirmingDelete}
        title={t('settings.teams.deleteTitle', { name: team.name })}
        description={t('settings.teams.deleteDescription')}
        confirmLabel={t('settings.teams.deleteConfirm')}
        pendingLabel={t('settings.teams.deleting')}
        isPending={deleteMutation.isPending}
        onConfirm={() => deleteMutation.mutate()}
        onClose={() => setIsConfirmingDelete(false)}
      />
    </li>
  )
}
