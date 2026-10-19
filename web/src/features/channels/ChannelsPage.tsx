import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { deleteNotificationChannel, listNotificationChannels, sendNotificationChannelTest } from '../../api/notificationChannels.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { NotificationChannel } from '../../api/types.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { deliveryMessage } from './deliveryMessage.ts'

function monitorsUsage(count: number): string {
  if (count === 0) {
    return t('settings.channels.monitorsNone')
  }

  return count === 1 ? t('settings.channels.monitorsOne') : t('settings.channels.monitorsCount', { count })
}

export function ChannelsPage() {
  const { user } = useAuth()
  const canManage = canManageResources(user)
  const channels = useQuery({ queryKey: queryKeys.notificationChannels, queryFn: listNotificationChannels })
  const navigate = useNavigate()

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex max-w-prose flex-col gap-1">
          <h1 className="text-2xl font-semibold text-neutral-900">{t('settings.channels.heading')}</h1>
          <p className="text-neutral-600">{t('settings.channels.description')}</p>
        </div>
        {canManage && channels.data && channels.data.length > 0 && (
          <Button onClick={() => navigate('/channels/new')}>{t('settings.channels.add')}</Button>
        )}
      </div>
      <Card className="flex flex-col gap-6 px-5 py-6 sm:px-6">
        {channels.isPending && <Skeleton className="h-40" />}
        {channels.isError && <ErrorState onRetry={() => void channels.refetch()} />}
        {channels.data?.length === 0 && (
          <EmptyState
            title={t('settings.channels.emptyTitle')}
            description={t('settings.channels.emptyDescription')}
            action={canManage ? <Button onClick={() => navigate('/channels/new')}>{t('settings.channels.add')}</Button> : undefined}
          />
        )}
        {channels.data && channels.data.length > 0 && (
          <ul className="divide-y divide-border rounded-lg border border-border">
            {channels.data.map((channel) => (
              <ChannelRow key={channel.id} channel={channel} canManage={canManage} onEdit={() => navigate(`/channels/${channel.id}`)} />
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}

function ChannelRow({ channel, canManage, onEdit }: { channel: NotificationChannel; canManage: boolean; onEdit: () => void }) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [isConfirmingDelete, setIsConfirmingDelete] = useState(false)

  const testMutation = useMutation({
    mutationFn: () => sendNotificationChannelTest(channel.id),
    onSuccess: (message) => showToast(message),
    onError: (error) => showToast(deliveryMessage(error), 'error'),
  })
  const deleteMutation = useMutation({
    mutationFn: () => deleteNotificationChannel(channel.id),
    onSuccess: () => {
      setIsConfirmingDelete(false)
      void queryClient.invalidateQueries({ queryKey: queryKeys.notificationChannels })
      void queryClient.invalidateQueries({ queryKey: queryKeys.monitors })
      showToast(t('settings.channels.deleted'))
    },
    onError: (error) => {
      setIsConfirmingDelete(false)
      showToast(deliveryMessage(error), 'error')
    },
  })

  return (
    <li className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="truncate font-semibold text-neutral-900">{channel.name}</span>
          <Badge className="bg-neutral-100 text-neutral-700">{t(`settings.channels.types.${channel.type}`)}</Badge>
          {channel.is_default && <Badge className="bg-primary-50 text-primary-700">{t('settings.channels.default')}</Badge>}
          {!channel.enabled && <Badge className="bg-status-paused-soft text-status-paused">{t('settings.channels.disabled')}</Badge>}
        </div>
        <p className="text-xs text-neutral-500">{monitorsUsage(channel.monitors_count)}</p>
      </div>
      {canManage && (
        <div className="flex flex-wrap gap-2">
          <Button
            variant="secondary"
            onClick={() => testMutation.mutate()}
            isLoading={testMutation.isPending}
            loadingLabel={t('settings.channels.testing')}
            aria-label={`${t('settings.channels.test')}: ${channel.name}`}
          >
            {t('settings.channels.test')}
          </Button>
          <Button variant="secondary" onClick={onEdit} aria-label={`${t('settings.channels.edit')}: ${channel.name}`}>
            {t('settings.channels.edit')}
          </Button>
          <Button
            variant="ghost"
            className="text-status-down"
            onClick={() => setIsConfirmingDelete(true)}
            aria-label={`${t('settings.channels.delete')}: ${channel.name}`}
          >
            {t('settings.channels.delete')}
          </Button>
        </div>
      )}
      <ConfirmDialog
        isOpen={isConfirmingDelete}
        title={t('settings.channels.deleteTitle', { name: channel.name })}
        description={t('settings.channels.deleteDescription')}
        confirmLabel={t('settings.channels.deleteConfirm')}
        pendingLabel={t('settings.channels.deleting')}
        isPending={deleteMutation.isPending}
        onConfirm={() => deleteMutation.mutate()}
        onClose={() => setIsConfirmingDelete(false)}
      />
    </li>
  )
}
