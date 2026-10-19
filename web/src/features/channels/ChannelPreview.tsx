import { useMutation, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { getAlertPreview, sendNotificationChannelTest } from '../../api/notificationChannels.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { AlertSeverity, MessageFormat, NotificationChannel, NotificationChannelType, NotificationEvent } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { notificationEventOrder } from './channelTypes.ts'
import { deliveryMessage } from './deliveryMessage.ts'

const severityBorder: Record<AlertSeverity, string> = {
  critical: 'border-l-status-down',
  warning: 'border-l-status-degraded',
  resolved: 'border-l-status-up',
  info: 'border-l-primary-500',
}

type ChannelPreviewProps = {
  channel?: NotificationChannel
  type: NotificationChannelType
  events: NotificationEvent[]
  messageFormat: MessageFormat
  options: Record<string, string | boolean>
}

function mentionLabel(type: NotificationChannelType, options: Record<string, string | boolean>): string | null {
  if (type === 'slack' && typeof options.mention === 'string' && options.mention !== '') {
    return `@${options.mention}`
  }

  if (type === 'discord' && typeof options.mention_role_id === 'string' && options.mention_role_id.trim() !== '') {
    return `@&${options.mention_role_id.trim()}`
  }

  return null
}

export function ChannelPreview({ channel, type, events, messageFormat, options }: ChannelPreviewProps) {
  const { showToast } = useToast()
  const [event, setEvent] = useState<NotificationEvent>(events[0] ?? 'down')
  const preview = useQuery({
    queryKey: queryKeys.alertPreview(event, messageFormat),
    queryFn: () => getAlertPreview(event, messageFormat),
    staleTime: 60_000,
  })
  const testMutation = useMutation({
    mutationFn: () => sendNotificationChannelTest(channel?.id ?? 0),
    onSuccess: (message) => showToast(message),
    onError: (error) => showToast(deliveryMessage(error), 'error'),
  })
  const mention = preview.data?.severity === 'critical' ? mentionLabel(type, options) : null
  const isSubscribed = events.includes(event)
  const notSubscribedNote = t('settings.channels.page.previewNotSubscribed')
  const notes = [
    isSubscribed ? null : notSubscribedNote,
    type === 'telegram' && options.silent === true ? t('settings.channels.page.previewSilent') : null,
    type === 'webhook' ? t('settings.channels.page.previewWebhook') : null,
    type === 'email' ? t('settings.channels.page.previewEmailSubject') : null,
  ].filter((note) => note !== null)

  return (
    <Card className="flex flex-col gap-4 px-4 py-5 sm:px-5">
      <h2 className="text-base font-semibold text-neutral-900">{t('settings.channels.page.preview')}</h2>
      <SelectField
        id="preview-event"
        label={t('settings.channels.page.previewEvent')}
        value={event}
        onChange={(change) => setEvent(notificationEventOrder.find((option) => option === change.target.value) ?? event)}
      >
        {notificationEventOrder.map((option) => (
          <option key={option} value={option}>
            {t(`settings.channels.events.${option}`)}
          </option>
        ))}
      </SelectField>

      {preview.isPending && <Skeleton className="h-28" />}
      {preview.isError && <p className="text-sm text-status-down">{t('settings.channels.page.previewError')}</p>}
      {preview.data && (
        <div
          data-testid="alert-preview"
          className={`flex flex-col gap-2 rounded-md border border-l-4 border-border bg-neutral-50 px-3 py-3 text-sm ${severityBorder[preview.data.severity]} ${isSubscribed ? '' : 'opacity-50'}`}
        >
          {mention && <span className="self-start rounded bg-primary-50 px-1.5 font-medium text-primary-700">{mention}</span>}
          <p className="font-semibold break-words text-neutral-900">{preview.data.title}</p>
          {preview.data.summary && <p className="text-neutral-700">{preview.data.summary}</p>}
          {preview.data.fields.length > 0 && (
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1">
              {preview.data.fields.map((field) => (
                <div key={field.label} className="contents">
                  <dt className="text-neutral-500">{field.label}</dt>
                  <dd className="break-words text-neutral-800">{field.value}</dd>
                </div>
              ))}
            </dl>
          )}
        </div>
      )}

      {notes.length > 0 && (
        <ul className="flex flex-col gap-1 text-xs text-neutral-500">
          {notes.map((note) => (
            <li key={note} className={note === notSubscribedNote ? 'font-medium text-status-degraded' : undefined}>
              {note}
            </li>
          ))}
        </ul>
      )}

      <div className="flex flex-col gap-1.5 border-t border-border pt-4">
        <Button
          variant="secondary"
          onClick={() => testMutation.mutate()}
          disabled={!channel}
          isLoading={testMutation.isPending}
          loadingLabel={t('settings.channels.testing')}
        >
          {t('settings.channels.test')}
        </Button>
        <p className="text-xs text-neutral-500">{channel ? t('settings.channels.page.testHint') : t('settings.channels.page.testUnsaved')}</p>
      </div>
    </Card>
  )
}
