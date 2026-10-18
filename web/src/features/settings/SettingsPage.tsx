import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { useState, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import { getNotificationSettings, removeTelegram, sendTelegramTest, updateNotificationSettings } from '../../api/settings.ts'
import type { NotificationSettings, NotificationSettingsInput } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'

function telegramMessage(error: unknown): string {
  if (isAxiosError<{ message?: string }>(error) && error.response && [422, 503].includes(error.response.status)) {
    return error.response.data.message ?? t('common.genericError')
  }

  return generalErrorMessage(error) ?? t('common.genericError')
}

export function SettingsPage() {
  const settings = useQuery({ queryKey: queryKeys.notificationSettings, queryFn: getNotificationSettings })

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-neutral-900">{t('settings.title')}</h1>
      {settings.isPending && <Skeleton className="h-96 max-w-2xl" />}
      {settings.isError && <ErrorState onRetry={() => void settings.refetch()} />}
      {settings.data && <TelegramSettings key={JSON.stringify(settings.data)} settings={settings.data} />}
    </div>
  )
}

function TelegramSettings({ settings }: { settings: NotificationSettings }) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [token, setToken] = useState('')
  const [chatId, setChatId] = useState(settings.telegram_chat_id ?? '')
  const [isEnabled, setIsEnabled] = useState(settings.telegram_enabled)
  const [isConfirmingRemoval, setIsConfirmingRemoval] = useState(false)

  const storeSettings = (updated: NotificationSettings) => queryClient.setQueryData(queryKeys.notificationSettings, updated)

  const saveMutation = useMutation({
    mutationFn: updateNotificationSettings,
    onSuccess: (updated) => {
      storeSettings(updated)
      showToast(t('settings.telegram.saved'))
    },
  })
  const testMutation = useMutation({
    mutationFn: sendTelegramTest,
    onSuccess: (message) => showToast(message),
    onError: (error) => showToast(telegramMessage(error), 'error'),
  })
  const removeMutation = useMutation({
    mutationFn: removeTelegram,
    onSuccess: (updated) => {
      setIsConfirmingRemoval(false)
      storeSettings(updated)
      showToast(t('settings.telegram.removed'))
    },
    onError: (error) => {
      setIsConfirmingRemoval(false)
      showToast(telegramMessage(error), 'error')
    },
  })

  const errors = fieldErrors(saveMutation.error)
  const generalError = saveMutation.isError ? generalErrorMessage(saveMutation.error) : null
  const isConfigured = settings.has_telegram_token && settings.telegram_chat_id !== null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (saveMutation.isPending) {
      return
    }

    const input: NotificationSettingsInput = { telegram_enabled: isEnabled }

    if (token.trim() !== '') {
      input.telegram_bot_token = token.trim()
    }

    if (chatId.trim() !== '') {
      input.telegram_chat_id = chatId.trim()
    }

    saveMutation.mutate(input)
  }

  return (
    <Card className="flex max-w-2xl flex-col gap-6 px-5 py-6 sm:px-6">
      <div className="flex flex-col gap-1">
        <h2 className="text-lg font-semibold text-neutral-900">{t('settings.telegram.heading')}</h2>
        <p className="text-neutral-600">{t('settings.telegram.description')}</p>
      </div>

      <details className="rounded-md border border-border bg-neutral-50 px-4 py-3">
        <summary className="cursor-pointer font-medium text-neutral-800">{t('settings.telegram.howTo')}</summary>
        <ol className="mt-3 flex list-decimal flex-col gap-2 pl-5 text-neutral-700">
          <li>{t('settings.telegram.steps.createBot')}</li>
          <li>{t('settings.telegram.steps.startChat')}</li>
          <li>{t('settings.telegram.steps.findChatId')}</li>
        </ol>
      </details>

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField
          label={t('settings.telegram.token')}
          type="password"
          name="telegram_bot_token"
          autoComplete="off"
          placeholder={settings.has_telegram_token ? t('settings.telegram.tokenSaved') : '123456789:AA…'}
          value={token}
          onChange={(event) => setToken(event.target.value)}
          error={errors.telegram_bot_token}
        />
        <TextField
          label={t('settings.telegram.chatId')}
          name="telegram_chat_id"
          autoComplete="off"
          hint={t('settings.telegram.chatIdHint')}
          value={chatId}
          onChange={(event) => setChatId(event.target.value)}
          error={errors.telegram_chat_id}
        />
        <div className="flex flex-col gap-1">
          <label className="inline-flex items-center gap-2 font-medium text-neutral-800">
            <input
              type="checkbox"
              checked={isEnabled}
              onChange={(event) => setIsEnabled(event.target.checked)}
              className="size-4 accent-primary-600"
            />
            {t('settings.telegram.enabled')}
          </label>
          {errors.telegram_enabled && <p className="text-xs font-medium text-status-down">{errors.telegram_enabled}</p>}
        </div>

        <div className="flex flex-wrap gap-2 border-t border-border pt-4">
          <Button type="submit" isLoading={saveMutation.isPending} loadingLabel={t('settings.telegram.saving')}>
            {t('settings.telegram.save')}
          </Button>
          <Button
            variant="secondary"
            disabled={!isConfigured}
            onClick={() => testMutation.mutate()}
            isLoading={testMutation.isPending}
            loadingLabel={t('settings.telegram.testing')}
          >
            {t('settings.telegram.test')}
          </Button>
          {(settings.has_telegram_token || settings.telegram_chat_id !== null) && (
            <Button variant="ghost" className="text-status-down sm:ml-auto" onClick={() => setIsConfirmingRemoval(true)}>
              {t('settings.telegram.remove')}
            </Button>
          )}
        </div>
      </form>

      <ConfirmDialog
        isOpen={isConfirmingRemoval}
        title={t('settings.telegram.removeTitle')}
        description={t('settings.telegram.removeDescription')}
        confirmLabel={t('settings.telegram.removeConfirm')}
        pendingLabel={t('settings.telegram.removing')}
        isPending={removeMutation.isPending}
        onConfirm={() => removeMutation.mutate()}
        onClose={() => setIsConfirmingRemoval(false)}
      />
    </Card>
  )
}
