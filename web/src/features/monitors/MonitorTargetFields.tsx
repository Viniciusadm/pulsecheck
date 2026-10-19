import type { MonitorType } from '../../api/types.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { SelectField } from '../../components/ui/SelectField.tsx'
import { TextField } from '../../components/ui/TextField.tsx'
import { t } from '../../i18n/index.ts'
import { KEYWORD_MODES, type TargetState } from './monitorTypes.ts'

type MonitorTargetFieldsProps = {
  type: MonitorType
  state: TargetState
  onChange: (state: TargetState) => void
  errors: Record<string, string>
  keywordMaxBytes: number
  isEditing: boolean
}

function formatSize(bytes: number): string {
  return bytes >= 1024 * 1024 ? `${Math.round(bytes / 1024 / 1024)} MB` : `${Math.round(bytes / 1024)} KB`
}

export function MonitorTargetFields({ type, state, onChange, errors, keywordMaxBytes, isEditing }: MonitorTargetFieldsProps) {
  const set = (changes: Partial<TargetState>) => onChange({ ...state, ...changes })

  switch (type) {
    case 'keyword':
      return (
        <div className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              label={t('monitors.form.keyword')}
              name="keyword"
              required
              maxLength={255}
              hint={t('monitors.form.keywordHint', { size: formatSize(keywordMaxBytes) })}
              value={state.keyword}
              onChange={(event) => set({ keyword: event.target.value })}
              error={errors['options.keyword'] ?? errors.options}
            />
            <SelectField
              label={t('monitors.form.keywordMode')}
              name="keyword_mode"
              value={state.keywordMode}
              onChange={(event) => set({ keywordMode: KEYWORD_MODES.find((mode) => mode === event.target.value) ?? state.keywordMode })}
              error={errors['options.keyword_mode']}
            >
              {KEYWORD_MODES.map((mode) => (
                <option key={mode} value={mode}>
                  {t(`monitors.form.keywordModes.${mode}`)}
                </option>
              ))}
            </SelectField>
          </div>
          <label className="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
            <input
              type="checkbox"
              checked={state.caseSensitive}
              onChange={(event) => set({ caseSensitive: event.target.checked })}
              className="size-4 accent-primary-600"
            />
            {t('monitors.form.caseSensitive')}
          </label>
        </div>
      )
    case 'tcp':
      return (
        <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
          <TextField
            label={t('monitors.form.host')}
            name="host"
            required
            autoComplete="off"
            placeholder="db.example.com"
            hint={t('monitors.form.hostHint')}
            value={state.host}
            onChange={(event) => set({ host: event.target.value })}
            error={errors['options.host'] ?? errors.options}
          />
          <TextField
            label={t('monitors.form.port')}
            name="port"
            type="number"
            required
            min={1}
            max={65535}
            value={state.port}
            onChange={(event) => set({ port: event.target.value })}
            error={errors['options.port']}
          />
        </div>
      )
    case 'ssl':
      return (
        <TextField
          label={t('monitors.form.warningDays')}
          name="warning_days"
          type="number"
          min={1}
          max={365}
          hint={t('monitors.form.warningDaysHint')}
          value={state.warningDays}
          onChange={(event) => set({ warningDays: event.target.value })}
          error={errors['options.warning_days']}
        />
      )
    case 'heartbeat':
      return (
        <div className="flex flex-col gap-4">
          <TextField
            label={t('monitors.form.graceSeconds')}
            name="grace_seconds"
            type="number"
            min={0}
            max={3600}
            hint={t('monitors.form.graceSecondsHint')}
            value={state.graceSeconds}
            onChange={(event) => set({ graceSeconds: event.target.value })}
            error={errors['options.grace_seconds']}
          />
          {!isEditing && <Alert tone="info">{t('monitors.form.heartbeatAfterSave')}</Alert>}
        </div>
      )
    default:
      return null
  }
}
