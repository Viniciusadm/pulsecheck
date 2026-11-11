import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { createApiToken, listApiTokens, revokeApiToken } from '../../api/account.ts'
import { fieldErrors, generalErrorMessage } from '../../api/errors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { ApiToken, TokenAbility } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { Dialog } from '../../components/ui/Dialog.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { formatDateTime, formatRelativeTime } from '../../lib/format.ts'
import { AccountSection } from './AccountSection.tsx'

const EXPIRATION_OPTIONS = ['30', '90', '365', 'never'] as const

type Access = 'read' | 'write'

export function ApiTokensSection() {
  const tokens = useQuery({ queryKey: queryKeys.apiTokens, queryFn: listApiTokens })
  const [isCreating, setIsCreating] = useState(false)
  const [createdToken, setCreatedToken] = useState<string | null>(null)

  return (
    <AccountSection
      id="api-tokens-heading"
      title={t('account.tokens.heading')}
      description={t('account.tokens.description')}
      action={<Button variant="secondary" onClick={() => setIsCreating(true)}>{t('account.tokens.create')}</Button>}
    >
      {createdToken && <CreatedToken token={createdToken} onDismiss={() => setCreatedToken(null)} />}
      {tokens.isPending && <Skeleton className="h-16" />}
      {tokens.isError && <ErrorState onRetry={() => void tokens.refetch()} />}
      {tokens.data && tokens.data.length === 0 && <p className="text-neutral-600">{t('account.tokens.empty')}</p>}
      {tokens.data && tokens.data.length > 0 && (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {tokens.data.map((token) => (
            <TokenRow key={token.id} token={token} />
          ))}
        </ul>
      )}
      {isCreating && (
        <CreateTokenDialog
          onClose={() => setIsCreating(false)}
          onCreated={(plainTextToken) => {
            setIsCreating(false)
            setCreatedToken(plainTextToken)
          }}
        />
      )}
    </AccountSection>
  )
}

function CreatedToken({ token, onDismiss }: { token: string; onDismiss: () => void }) {
  const { showToast } = useToast()

  return (
    <div className="flex flex-col gap-3 rounded-lg border border-status-up/30 bg-status-up-soft p-4">
      <p className="font-medium text-status-up-ink">{t('account.tokens.createdNotice')}</p>
      <code className="break-all rounded-md bg-surface px-3 py-2 font-mono text-sm text-neutral-900">{token}</code>
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onClick={() => void navigator.clipboard?.writeText(token).then(() => showToast(t('account.tokens.copied')))}>
          {t('account.tokens.copy')}
        </Button>
        <Button variant="ghost" onClick={onDismiss}>{t('common.dismiss')}</Button>
      </div>
    </div>
  )
}

function TokenRow({ token }: { token: ApiToken }) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [isConfirming, setIsConfirming] = useState(false)
  const [renderedAt] = useState(() => Date.now())
  const isExpired = token.expires_at !== null && Date.parse(token.expires_at) < renderedAt

  const mutation = useMutation({
    mutationFn: () => revokeApiToken(token.id),
    onSuccess: () => {
      setIsConfirming(false)
      void queryClient.invalidateQueries({ queryKey: queryKeys.apiTokens })
      showToast(t('account.tokens.revoked'))
    },
    onError: (error) => {
      setIsConfirming(false)
      showToast(generalErrorMessage(error) ?? t('common.genericError'), 'error')
    },
  })

  return (
    <li className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex min-w-0 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="truncate font-semibold text-neutral-900">{token.name}</span>
          <Badge className={token.abilities.includes('write') ? 'bg-status-degraded-soft text-status-degraded' : 'bg-neutral-100 text-neutral-700'}>
            {token.abilities.includes('write') ? t('account.tokens.readWrite') : t('account.tokens.readOnly')}
          </Badge>
          {isExpired && <Badge className="bg-status-down-soft text-status-down">{t('account.tokens.expired')}</Badge>}
        </div>
        <p className="text-xs text-neutral-500">
          {token.last_used_at ? t('account.tokens.lastUsed', { time: formatRelativeTime(token.last_used_at) }) : t('account.tokens.neverUsed')}
          {' · '}
          {token.expires_at ? t('account.tokens.expires', { date: formatDateTime(token.expires_at) }) : t('account.tokens.noExpiry')}
        </p>
      </div>
      <Button variant="ghost" className="self-start text-status-down sm:self-auto" onClick={() => setIsConfirming(true)} aria-label={`${t('account.tokens.revoke')}: ${token.name}`}>
        {t('account.tokens.revoke')}
      </Button>
      <ConfirmDialog
        isOpen={isConfirming}
        title={t('account.tokens.revokeTitle', { name: token.name })}
        description={t('account.tokens.revokeDescription')}
        confirmLabel={t('account.tokens.revoke')}
        pendingLabel={t('account.tokens.revoking')}
        isPending={mutation.isPending}
        onConfirm={() => mutation.mutate()}
        onClose={() => setIsConfirming(false)}
      />
    </li>
  )
}

function CreateTokenDialog({ onClose, onCreated }: { onClose: () => void; onCreated: (plainTextToken: string) => void }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const [access, setAccess] = useState<Access>('read')
  const [expiration, setExpiration] = useState<(typeof EXPIRATION_OPTIONS)[number]>('90')

  const mutation = useMutation({
    mutationFn: createApiToken,
    onSuccess: ({ plainTextToken }) => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.apiTokens })
      onCreated(plainTextToken)
    },
  })

  const errors = fieldErrors(mutation.error)
  const generalError = mutation.isError ? generalErrorMessage(mutation.error) : null

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault()

    if (!mutation.isPending) {
      const abilities: TokenAbility[] = access === 'write' ? ['read', 'write'] : ['read']
      mutation.mutate({ name: name.trim(), abilities, expires_in_days: expiration === 'never' ? null : Number(expiration) })
    }
  }

  return (
    <Dialog isOpen title={t('account.tokens.create')} onClose={onClose} isBusy={mutation.isPending}>
      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        {generalError && <Alert tone="error">{generalError}</Alert>}
        <TextField
          label={t('account.tokens.name')}
          name="name"
          data-autofocus
          required
          maxLength={100}
          hint={t('account.tokens.nameHint')}
          value={name}
          onChange={(event) => setName(event.target.value)}
          error={errors.name}
        />
        <SelectField label={t('account.tokens.access')} value={access} onChange={(event) => setAccess(event.target.value as Access)} hint={t('account.tokens.accessHint')} error={errors.abilities}>
          <option value="read">{t('account.tokens.readOnly')}</option>
          <option value="write">{t('account.tokens.readWrite')}</option>
        </SelectField>
        <SelectField
          label={t('account.tokens.expiration')}
          value={expiration}
          onChange={(event) => setExpiration(event.target.value as (typeof EXPIRATION_OPTIONS)[number])}
          error={errors.expires_in_days}
        >
          {EXPIRATION_OPTIONS.map((option) => (
            <option key={option} value={option}>
              {option === 'never' ? t('account.tokens.never') : t('account.tokens.days', { count: option })}
            </option>
          ))}
        </SelectField>
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={mutation.isPending}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" isLoading={mutation.isPending} loadingLabel={t('account.tokens.creating')}>
            {t('account.tokens.create')}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
