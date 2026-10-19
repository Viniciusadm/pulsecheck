import { useQuery } from '@tanstack/react-query'
import { Link, Navigate, useNavigate, useParams } from 'react-router'
import { listNotificationChannels } from '../../api/notificationChannels.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { NotificationChannel } from '../../api/types.ts'
import { EmptyState } from '../../components/ui/EmptyState.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { canManageResources } from '../../lib/permissions.ts'
import { useAuth } from '../auth/AuthContext.ts'
import { NotificationChannelForm } from './NotificationChannelForm.tsx'

export function NotificationChannelCreatePage() {
  const { user } = useAuth()

  if (!canManageResources(user)) {
    return <Navigate to="/channels" replace />
  }

  return <ChannelFormLayout />
}

export function NotificationChannelEditPage() {
  const { user } = useAuth()
  const channelId = Number(useParams().id)
  const channels = useQuery({ queryKey: queryKeys.notificationChannels, queryFn: listNotificationChannels })
  const channel = channels.data?.find((candidate) => candidate.id === channelId)

  if (!canManageResources(user)) {
    return <Navigate to="/channels" replace />
  }

  if (channels.isError) {
    return <ErrorState onRetry={() => void channels.refetch()} />
  }

  if (channels.isPending) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-96" />
      </div>
    )
  }

  if (!channel) {
    return (
      <EmptyState
        title={t('settings.channels.page.notFound')}
        action={
          <Link to="/channels" className="font-medium text-primary-700 hover:underline">
            {t('settings.channels.page.back')}
          </Link>
        }
      />
    )
  }

  return <ChannelFormLayout key={channel.id} channel={channel} />
}

function ChannelFormLayout({ channel }: { channel?: NotificationChannel }) {
  const navigate = useNavigate()

  return (
    <div className="flex max-w-6xl flex-col gap-6">
      <div className="flex flex-col gap-4">
        <Link to="/channels" className="self-start text-sm font-medium text-primary-700 hover:underline">
          {t('settings.channels.page.back')}
        </Link>
        <h1 className="text-2xl font-semibold text-neutral-900">
          {channel ? t('settings.channels.form.editTitle', { name: channel.name }) : t('settings.channels.form.createTitle')}
        </h1>
      </div>
      <NotificationChannelForm
        channel={channel}
        layout="page"
        onSaved={(saved) => navigate(channel ? '/channels' : `/channels/${saved.id}`, { replace: !channel })}
        onCancel={() => navigate('/channels')}
      />
    </div>
  )
}
