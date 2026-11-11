import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Navigate } from 'react-router'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { User, UserRole } from '../../api/types.ts'
import { deleteUser, listUsers, resetUserTwoFactor } from '../../api/users.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { isAdmin } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { UserDialog } from './UserDialog.tsx'

type DialogState = { mode: 'closed' } | { mode: 'create' } | { mode: 'edit'; user: User }

const roleBadgeClassName: Record<UserRole, string> = {
  admin: 'bg-primary-50 text-primary-700',
  manager: 'bg-status-up-soft text-status-up',
  member: 'bg-neutral-100 text-neutral-700',
}

function deleteErrorMessage(error: unknown): string {
  const errors = fieldErrors(error)

  return errors.user ?? errors.role ?? generalErrorMessage(error) ?? t('common.genericError')
}

export function UsersPage() {
  const { user } = useAuth()
  const users = useQuery({ queryKey: queryKeys.users, queryFn: listUsers, enabled: isAdmin(user) })
  const [dialog, setDialog] = useState<DialogState>({ mode: 'closed' })

  if (!isAdmin(user)) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <section aria-labelledby="users-heading" className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex max-w-prose flex-col gap-1">
          <h1 id="users-heading" className="page-title">{t('settings.users.heading')}</h1>
          <p className="text-neutral-600">{t('settings.users.description')}</p>
        </div>
        <Button onClick={() => setDialog({ mode: 'create' })}>{t('settings.users.add')}</Button>
      </div>
      <Card className="flex flex-col gap-6 px-5 py-6 sm:px-6">
        {users.isPending && <Skeleton className="h-32" />}
        {users.isError && <ErrorState onRetry={() => void users.refetch()} />}
        {users.data && users.data.length > 0 && (
          <ul className="divide-y divide-border rounded-lg border border-border">
            {users.data.map((user) => (
              <UserRow key={user.id} user={user} onEdit={() => setDialog({ mode: 'edit', user })} />
            ))}
          </ul>
        )}
      </Card>
      {dialog.mode !== 'closed' && (
        <UserDialog user={dialog.mode === 'edit' ? dialog.user : undefined} onClose={() => setDialog({ mode: 'closed' })} />
      )}
    </section>
  )
}

function UserRow({ user, onEdit }: { user: User; onEdit: () => void }) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const { user: currentUser } = useAuth()
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)
  const [isConfirmingReset, setIsConfirmingReset] = useState(false)
  const isCurrentUser = currentUser?.id === user.id

  const deleteMutation = useMutation({
    mutationFn: () => deleteUser(user.id),
    onSuccess: () => {
      setIsConfirmingDelete(false)
      void queryClient.invalidateQueries({ queryKey: queryKeys.users })
      void queryClient.invalidateQueries({ queryKey: queryKeys.teams })
      showToast(t('settings.users.deleted'))
    },
    onError: (error) => {
      setIsConfirmingDelete(false)
      showToast(deleteErrorMessage(error), 'error')
    },
  })

  const resetMutation = useMutation({
    mutationFn: () => resetUserTwoFactor(user.id),
    onSuccess: () => {
      setIsConfirmingReset(false)
      void queryClient.invalidateQueries({ queryKey: queryKeys.users })
      showToast(t('settings.users.twoFactorReset'))
    },
    onError: (error) => {
      setIsConfirmingReset(false)
      showToast(generalErrorMessage(error) ?? t('common.genericError'), 'error')
    },
  })

  return (
    <li className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="truncate font-semibold text-neutral-900">{user.name}</span>
          <Badge className={roleBadgeClassName[user.role]}>
            {t(`settings.users.roles.${user.role}`)}
          </Badge>
          {user.two_factor_enabled && <Badge className="bg-status-up-soft text-status-up">{t('settings.users.twoFactor')}</Badge>}
          {isCurrentUser && <Badge className="bg-neutral-100 text-neutral-700">{t('settings.users.you')}</Badge>}
        </div>
        <p className="truncate text-xs text-neutral-500" title={user.email}>{user.email}</p>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onClick={onEdit} aria-label={`${t('settings.users.edit')}: ${user.name}`}>
          {t('settings.users.edit')}
        </Button>
        {!isCurrentUser && user.two_factor_enabled && (
          <Button variant="ghost" onClick={() => setIsConfirmingReset(true)} aria-label={`${t('settings.users.resetTwoFactor')}: ${user.name}`}>
            {t('settings.users.resetTwoFactor')}
          </Button>
        )}
        {!isCurrentUser && (
          <Button
            variant="ghost"
            className="text-status-down"
            onClick={() => setIsConfirmingDelete(true)}
            aria-label={`${t('settings.users.delete')}: ${user.name}`}
          >
            {t('settings.users.delete')}
          </Button>
        )}
      </div>
      <ConfirmDialog
        isOpen={isConfirmingDelete}
        title={t('settings.users.deleteTitle', { name: user.name })}
        description={t('settings.users.deleteDescription')}
        confirmLabel={t('settings.users.deleteConfirm')}
        pendingLabel={t('settings.users.deleting')}
        isPending={deleteMutation.isPending}
        onConfirm={() => deleteMutation.mutate()}
        onClose={() => setIsConfirmingDelete(false)}
      />
      <ConfirmDialog
        isOpen={isConfirmingReset}
        title={t('settings.users.resetTwoFactorTitle', { name: user.name })}
        description={t('settings.users.resetTwoFactorDescription')}
        confirmLabel={t('settings.users.resetTwoFactor')}
        pendingLabel={t('settings.users.resettingTwoFactor')}
        isPending={resetMutation.isPending}
        onConfirm={() => resetMutation.mutate()}
        onClose={() => setIsConfirmingReset(false)}
      />
    </li>
  )
}
