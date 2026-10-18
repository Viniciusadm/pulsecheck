import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { updateInstance } from '../../api/instance.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { AppConfig } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { useAppConfig } from '../config/ConfigContext.ts'
import { LocaleSelect, TimeZoneSelect } from '../instance/InstanceFields.tsx'

export function InstanceSection() {
  const config = useAppConfig()
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [form, setForm] = useState(config.instance)

  const mutation = useMutation({
    mutationFn: updateInstance,
    onSuccess: (instance) => {
      showToast(t('settings.instance.saved'))
      queryClient.setQueryData<AppConfig>(queryKeys.config, (current) => current && { ...current, instance })
    },
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate({ ...form, name: form.name.trim() })
    }
  }

  return (
    <section aria-labelledby="instance-heading">
      <Card className="flex max-w-3xl flex-col gap-6 px-5 py-6 sm:px-6">
        <div className="flex max-w-prose flex-col gap-1">
          <h2 id="instance-heading" className="text-lg font-semibold text-neutral-900">{t('settings.instance.heading')}</h2>
          <p className="text-neutral-600">{t('settings.instance.description')}</p>
        </div>
        <form onSubmit={handleSubmit} noValidate className="flex max-w-md flex-col gap-4">
          {generalError && <Alert tone="error">{generalError}</Alert>}
          <TextField
            label={t('settings.instance.name')}
            name="name"
            required
            maxLength={100}
            value={form.name}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
            error={errors.name}
          />
          <TimeZoneSelect
            label={t('settings.instance.timezone')}
            value={form.timezone}
            onChange={(timezone) => setForm({ ...form, timezone })}
            error={errors.timezone}
          />
          <LocaleSelect
            label={t('settings.instance.language')}
            locales={config.available_locales}
            value={form.locale}
            onChange={(locale) => setForm({ ...form, locale })}
            error={errors.locale}
          />
          <div>
            <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('settings.instance.saving')}>
              {t('settings.instance.save')}
            </Button>
          </div>
        </form>
      </Card>
    </section>
  )
}
