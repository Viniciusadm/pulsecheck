import { useId } from 'react'
import type { Monitor } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { HeaderRowsEditor } from '../../components/ui/HeaderRowsEditor.tsx'
import { inputClassName } from '../../components/ui/inputClassName.ts'
import { PasswordField } from '../../components/ui/PasswordField.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { t } from '../../i18n/index.ts'
import { AUTH_TYPES, hasCustomRequest, HTTP_METHODS, METHODS_WITHOUT_BODY, type HttpRequestState } from './httpRequest.ts'

type HttpRequestFieldsProps = {
  state: HttpRequestState
  onChange: (state: HttpRequestState) => void
  monitor?: Monitor
  errors: Record<string, string>
}

export function HttpRequestFields({ state, onChange, monitor, errors }: HttpRequestFieldsProps) {
  const bodyId = useId()
  const set = (changes: Partial<HttpRequestState>) => onChange({ ...state, ...changes })
  const errorFor = (prefix: string) => Object.entries(errors).find(([field]) => field === prefix || field.startsWith(`${prefix}.`))?.[1]
  const isAuthSaved = monitor !== undefined && monitor.auth_type !== 'none' && monitor.auth_type === state.authType
  const isBodySaved = (monitor?.secrets_set.includes('body') ?? false) && !state.isClearingBody
  const acceptsBody = !METHODS_WITHOUT_BODY.includes(state.method)
  const bodyError = errors['secrets.body']

  return (
    <details open={hasCustomRequest(monitor) || Object.keys(errors).some((field) => field.startsWith('options') || field.startsWith('secrets'))} className="rounded-md border border-border px-4 py-3">
      <summary className="cursor-pointer text-sm font-medium text-neutral-800">{t('monitors.form.request')}</summary>
      <p className="mt-1 text-xs text-neutral-500">{t('monitors.form.requestHint')}</p>
      <div className="mt-4 flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <SelectField
            label={t('monitors.form.method')}
            name="method"
            value={state.method}
            onChange={(event) => set({ method: HTTP_METHODS.find((method) => method === event.target.value) ?? state.method })}
            error={errors['options.method']}
          >
            {HTTP_METHODS.map((method) => (
              <option key={method} value={method}>
                {method}
              </option>
            ))}
          </SelectField>
          <TextField
            label={t('monitors.form.expectedStatus')}
            name="expected_status_codes"
            hint={t('monitors.form.expectedStatusHint')}
            value={state.expectedStatus}
            onChange={(event) => set({ expectedStatus: event.target.value })}
            error={errorFor('options.expected_status_codes')}
          />
        </div>
        <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
          <input
            type="checkbox"
            checked={state.followRedirects}
            onChange={(event) => set({ followRedirects: event.target.checked })}
            className="size-4 accent-primary-600"
          />
          {t('monitors.form.followRedirects')}
        </label>

        <HeaderRowsEditor
          rows={state.headers}
          onChange={(headers) => set({ headers })}
          hint={t('monitors.form.headersHint')}
          isReplacing={state.isReplacingHeaders}
          onReplace={() => set({ isReplacingHeaders: true })}
          error={errorFor('secrets.headers')}
        />

        {acceptsBody && (
          <>
            <TextField
              label={t('monitors.form.contentType')}
              name="body_content_type"
              placeholder="application/json"
              value={state.contentType}
              onChange={(event) => set({ contentType: event.target.value })}
              error={errors['options.body_content_type']}
            />
            <div className="flex flex-col gap-1.5">
              <label htmlFor={bodyId} className="text-sm font-medium text-neutral-800">
                {t('monitors.form.body')}
              </label>
              <textarea
                id={bodyId}
                name="body"
                rows={4}
                spellCheck={false}
                placeholder={isBodySaved ? t('monitors.form.bodySaved') : '{"ping": true}'}
                value={state.body}
                onChange={(event) => set({ body: event.target.value })}
                aria-invalid={bodyError ? true : undefined}
                aria-describedby={bodyError ? `${bodyId}-error` : undefined}
                className={`${inputClassName(bodyError)} py-2 font-mono`}
              />
              {bodyError && (
                <p id={`${bodyId}-error`} className="text-xs font-medium text-status-down">
                  {bodyError}
                </p>
              )}
              {isBodySaved && state.body === '' && (
                <Button variant="ghost" className="self-start" onClick={() => set({ isClearingBody: true })}>
                  {t('monitors.form.removeBody')}
                </Button>
              )}
              {state.isClearingBody && state.body === '' && (
                <p className="text-xs text-neutral-500">
                  {t('monitors.form.bodyWillBeRemoved')}{' '}
                  <button type="button" className="font-semibold text-primary-700 underline" onClick={() => set({ isClearingBody: false })}>
                    {t('monitors.form.keepBody')}
                  </button>
                </p>
              )}
            </div>
          </>
        )}

        <SelectField
          label={t('monitors.form.auth')}
          name="auth_type"
          value={state.authType}
          hint={isAuthSaved ? t('monitors.form.authSaved') : undefined}
          onChange={(event) => set({ authType: AUTH_TYPES.find((type) => type === event.target.value) ?? state.authType })}
          error={errors['secrets.auth.type']}
        >
          {AUTH_TYPES.map((type) => (
            <option key={type} value={type}>
              {t(`monitors.form.authTypes.${type}`)}
            </option>
          ))}
        </SelectField>
        {state.authType === 'basic' && (
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              label={t('monitors.form.username')}
              name="auth_username"
              autoComplete="off"
              value={state.username}
              onChange={(event) => set({ username: event.target.value })}
              error={errors['secrets.auth.username']}
            />
            <PasswordField
              label={t('monitors.form.password')}
              name="auth_password"
              autoComplete="new-password"
              value={state.password}
              onChange={(event) => set({ password: event.target.value })}
              error={errors['secrets.auth.password']}
            />
          </div>
        )}
        {state.authType === 'bearer' && (
          <PasswordField
            label={t('monitors.form.token')}
            name="auth_token"
            autoComplete="off"
            value={state.token}
            onChange={(event) => set({ token: event.target.value })}
            error={errors['secrets.auth.token']}
          />
        )}
      </div>
    </details>
  )
}
