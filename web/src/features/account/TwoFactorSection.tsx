import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { confirmTwoFactor, disableTwoFactor, getTwoFactorStatus, regenerateRecoveryCodes, startTwoFactorSetup } from '../../api/account.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { TwoFactorSetup, User } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { formatDateTime } from '../../lib/format.ts'
import { currentUserQueryKey } from '../auth/AuthContext.ts'
import { AccountSection } from './AccountSection.tsx'
import { PasswordPromptDialog } from './PasswordPromptDialog.tsx'
import { RecoveryCodes } from './RecoveryCodes.tsx'

type Prompt = 'none' | 'enable' | 'disable' | 'regenerate'

export function TwoFactorSection() {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const status = useQuery({ queryKey: queryKeys.twoFactor, queryFn: getTwoFactorStatus })
  const [prompt, setPrompt] = useState<Prompt>('none')
  const [setup, setSetup] = useState<TwoFactorSetup | null>(null)
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null)

  const refresh = (isEnabled: boolean) => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.twoFactor })
    queryClient.setQueryData<User | null>(currentUserQueryKey, (user) => user && { ...user, two_factor_enabled: isEnabled })
  }

  const enableMutation = useMutation({
    mutationFn: startTwoFactorSetup,
    onSuccess: (result) => {
      setPrompt('none')
      setSetup(result)
    },
  })

  const disableMutation = useMutation({
    mutationFn: disableTwoFactor,
    onSuccess: () => {
      setPrompt('none')
      setRecoveryCodes(null)
      refresh(false)
      showToast(t('account.twoFactor.disabled'))
    },
  })

  const regenerateMutation = useMutation({
    mutationFn: regenerateRecoveryCodes,
    onSuccess: (codes) => {
      setPrompt('none')
      setRecoveryCodes(codes)
      void queryClient.invalidateQueries({ queryKey: queryKeys.twoFactor })
    },
  })

  const isEnabled = status.data?.enabled ?? false

  return (
    <AccountSection
      id="two-factor-heading"
      title={t('account.twoFactor.heading')}
      description={t('account.twoFactor.description')}
      action={status.data && <Badge className={isEnabled ? 'bg-status-up-soft text-status-up' : 'bg-neutral-100 text-neutral-700'}>{isEnabled ? t('account.twoFactor.on') : t('account.twoFactor.off')}</Badge>}
    >
      {status.isPending && <Skeleton className="h-16" />}
      {status.isError && <ErrorState onRetry={() => void status.refetch()} />}

      {recoveryCodes && <RecoveryCodes codes={recoveryCodes} onDone={() => setRecoveryCodes(null)} />}

      {!recoveryCodes && setup && (
        <TwoFactorSetupStep
          setup={setup}
          onCancel={() => setSetup(null)}
          onConfirmed={(codes) => {
            setSetup(null)
            setRecoveryCodes(codes)
            refresh(true)
            showToast(t('account.twoFactor.enabled'))
          }}
        />
      )}

      {!recoveryCodes && !setup && status.data && !isEnabled && (
        <div>
          <Button onClick={() => setPrompt('enable')}>{t('account.twoFactor.enable')}</Button>
        </div>
      )}

      {!recoveryCodes && !setup && status.data && isEnabled && (
        <div className="flex flex-col gap-4">
          <p className="text-neutral-700">
            {status.data.confirmed_at && t('account.twoFactor.enabledSince', { date: formatDateTime(status.data.confirmed_at) })}{' '}
            {t('account.twoFactor.codesRemaining', { count: status.data.recovery_codes_remaining })}
          </p>
          {status.data.recovery_codes_remaining <= 2 && <Alert tone="error">{t('account.twoFactor.fewCodesLeft')}</Alert>}
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" onClick={() => setPrompt('regenerate')}>{t('account.twoFactor.regenerate')}</Button>
            <Button variant="ghost" className="text-status-down" onClick={() => setPrompt('disable')}>{t('account.twoFactor.disable')}</Button>
          </div>
        </div>
      )}

      {prompt === 'enable' && (
        <PasswordPromptDialog
          title={t('account.twoFactor.enable')}
          description={t('account.twoFactor.confirmPassword')}
          confirmLabel={t('account.continue')}
          pendingLabel={t('account.checking')}
          isPending={enableMutation.isPending}
          error={fieldErrors(enableMutation.error).current_password ?? (enableMutation.isError ? (generalErrorMessage(enableMutation.error) ?? undefined) : undefined)}
          onConfirm={(password) => enableMutation.mutate(password)}
          onClose={() => {
            setPrompt('none')
            enableMutation.reset()
          }}
        />
      )}
      {prompt === 'disable' && (
        <PasswordPromptDialog
          title={t('account.twoFactor.disableTitle')}
          description={t('account.twoFactor.disableDescription')}
          confirmLabel={t('account.twoFactor.disable')}
          pendingLabel={t('account.checking')}
          isDanger
          isPending={disableMutation.isPending}
          error={fieldErrors(disableMutation.error).current_password ?? (disableMutation.isError ? (generalErrorMessage(disableMutation.error) ?? undefined) : undefined)}
          onConfirm={(password) => disableMutation.mutate(password)}
          onClose={() => {
            setPrompt('none')
            disableMutation.reset()
          }}
        />
      )}
      {prompt === 'regenerate' && (
        <PasswordPromptDialog
          title={t('account.twoFactor.regenerate')}
          description={t('account.twoFactor.regenerateDescription')}
          confirmLabel={t('account.twoFactor.regenerate')}
          pendingLabel={t('account.checking')}
          isPending={regenerateMutation.isPending}
          error={fieldErrors(regenerateMutation.error).current_password ?? (regenerateMutation.isError ? (generalErrorMessage(regenerateMutation.error) ?? undefined) : undefined)}
          onConfirm={(password) => regenerateMutation.mutate(password)}
          onClose={() => {
            setPrompt('none')
            regenerateMutation.reset()
          }}
        />
      )}
    </AccountSection>
  )
}

function TwoFactorSetupStep({ setup, onCancel, onConfirmed }: { setup: TwoFactorSetup; onCancel: () => void; onConfirmed: (codes: string[]) => void }) {
  const [code, setCode] = useState('')
  const mutation = useMutation({ mutationFn: confirmTwoFactor, onSuccess: onConfirmed })
  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      mutation.mutate(code.replace(/\s+/g, ''))
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <ol className="flex list-decimal flex-col gap-1 pl-5 text-neutral-700">
        <li>{t('account.twoFactor.stepScan')}</li>
        <li>{t('account.twoFactor.stepCode')}</li>
      </ol>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
        <img
          src={`data:image/svg+xml;utf8,${encodeURIComponent(setup.qr_code_svg)}`}
          alt={t('account.twoFactor.qrAlt')}
          width={176}
          height={176}
          className="size-44 shrink-0 rounded-lg border border-border bg-white p-2"
        />
        <div className="flex min-w-0 flex-col gap-1">
          <p className="text-sm text-neutral-600">{t('account.twoFactor.manualEntry')}</p>
          <code className="break-all rounded-md bg-neutral-100 px-2 py-1 font-mono text-sm text-neutral-900">{setup.secret}</code>
        </div>
      </div>
      <form onSubmit={handleSubmit} noValidate className="flex max-w-md flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField
          label={t('auth.twoFactor.code')}
          name="code"
          inputMode="numeric"
          autoComplete="one-time-code"
          required
          maxLength={7}
          value={code}
          onChange={(event) => setCode(event.target.value)}
          error={errors.code}
        />
        <div className="flex flex-wrap gap-2">
          <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('auth.twoFactor.verifying')}>
            {t('account.twoFactor.activate')}
          </Button>
          <Button variant="secondary" onClick={onCancel} disabled={mutation.isPending}>
            {t('common.cancel')}
          </Button>
        </div>
      </form>
    </div>
  )
}
