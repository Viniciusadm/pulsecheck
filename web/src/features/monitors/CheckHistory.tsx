import { useInfiniteQuery } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { fieldErrors } from '../../api/errors.ts'
import { listChecks } from '../../api/monitors.ts'
import { Alert } from '../../components/ui/Alert.tsx'
import { queryKeys } from '../../api/queryKeys.ts'
import type { CheckResult } from '../../api/types.ts'
import { Badge } from '../../components/ui/Badge.tsx'
import { Button } from '../../components/ui/Button.tsx'
import { ErrorState } from '../../components/ui/ErrorState.tsx'
import { Skeleton } from '../../components/ui/Skeleton.tsx'
import { t } from '../../i18n/index.ts'
import { formatDateTime, formatLatency, getDisplayTimeZone } from '../../lib/format.ts'
import { browserTimeZone, zonedInputToIso } from '../../lib/timeZones.ts'

function toIso(localValue: string): string {
  return localValue === '' ? '' : zonedInputToIso(localValue, getDisplayTimeZone() ?? browserTimeZone())
}

export function CheckHistory({ monitorId }: { monitorId: number }) {
  const [draft, setDraft] = useState({ from: '', to: '' })
  const [period, setPeriod] = useState({ from: '', to: '' })

  const checks = useInfiniteQuery({
    queryKey: queryKeys.checks(monitorId, period.from, period.to),
    queryFn: ({ pageParam }) => listChecks(monitorId, { from: period.from, to: period.to, cursor: pageParam }),
    initialPageParam: null as string | null,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor,
  })

  const results = checks.data?.pages.flatMap((page) => page.data) ?? []
  const periodErrors = Object.values(fieldErrors(checks.error))

  const applyPeriod = (event: FormEvent) => {
    event.preventDefault()
    setPeriod({ from: toIso(draft.from), to: toIso(draft.to) })
  }

  const resetPeriod = () => {
    setDraft({ from: '', to: '' })
    setPeriod({ from: '', to: '' })
  }

  return (
    <section className="flex flex-col gap-3" aria-labelledby="check-history">
      <h2 id="check-history" className="text-lg font-semibold text-neutral-900">
        {t('monitors.checks.heading')}
      </h2>

      <form onSubmit={applyPeriod} className="flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1 text-sm font-medium text-neutral-700">
          {t('monitors.checks.from')}
          <input
            type="datetime-local"
            value={draft.from}
            onChange={(event) => setDraft({ ...draft, from: event.target.value })}
            className="min-h-10 rounded-md border border-neutral-300 bg-surface px-3 font-normal"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm font-medium text-neutral-700">
          {t('monitors.checks.to')}
          <input
            type="datetime-local"
            value={draft.to}
            onChange={(event) => setDraft({ ...draft, to: event.target.value })}
            className="min-h-10 rounded-md border border-neutral-300 bg-surface px-3 font-normal"
          />
        </label>
        <Button type="submit" variant="secondary">
          {t('monitors.checks.apply')}
        </Button>
        {(period.from || period.to) && (
          <Button variant="ghost" onClick={resetPeriod}>
            {t('monitors.checks.reset')}
          </Button>
        )}
      </form>

      {checks.isPending && <Skeleton className="h-48" />}
      {checks.isError && periodErrors.length > 0 && <Alert tone="error">{periodErrors.join(' ')}</Alert>}
      {checks.isError && periodErrors.length === 0 && <ErrorState onRetry={() => void checks.refetch()} />}
      {checks.isSuccess && results.length === 0 && <p className="text-neutral-600">{t('monitors.checks.empty')}</p>}
      {results.length > 0 && (
        <div className="overflow-x-auto rounded-lg border border-border bg-surface">
          <table className="w-full min-w-[36rem] text-left">
            <thead className="border-b border-border bg-neutral-50 text-xs font-semibold text-neutral-600">
              <tr>
                <th scope="col" className="px-4 py-2.5">{t('monitors.checks.time')}</th>
                <th scope="col" className="px-4 py-2.5">{t('monitors.checks.result')}</th>
                <th scope="col" className="px-4 py-2.5 text-right">{t('monitors.columns.httpCode')}</th>
                <th scope="col" className="px-4 py-2.5 text-right">{t('monitors.columns.latency')}</th>
                <th scope="col" className="px-4 py-2.5">{t('monitors.incidents.cause')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {results.map((check) => (
                <tr key={check.id}>
                  <td className="whitespace-nowrap px-4 py-2.5 text-neutral-700">
                    <time dateTime={check.started_at}>{formatDateTime(check.started_at)}</time>
                  </td>
                  <td className="px-4 py-2.5">
                    {check.is_success && check.is_degraded && (
                      <Badge className="bg-status-degraded-soft text-status-degraded">{t('monitors.checks.degraded')}</Badge>
                    )}
                    {check.is_success && !check.is_degraded && (
                      <Badge className="bg-status-up-soft text-status-up">{t('monitors.checks.success')}</Badge>
                    )}
                    {!check.is_success && (
                      <Badge className="bg-status-down-soft text-status-down">{t('monitors.checks.failure')}</Badge>
                    )}
                    {check.in_maintenance && (
                      <Badge className="ml-1.5 bg-status-maintenance-soft text-status-maintenance">{t('monitors.checks.maintenance')}</Badge>
                    )}
                  </td>
                  <td className="px-4 py-2.5 text-right tabular-nums text-neutral-700">{check.status_code ?? '—'}</td>
                  <td className="px-4 py-2.5 text-right tabular-nums text-neutral-700">{formatLatency(check.latency_ms)}</td>
                  <td className="max-w-xs truncate px-4 py-2.5 text-neutral-600" title={check.error_message ?? check.warning_message ?? undefined}>
                    {checkCause(check)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {checks.hasNextPage && (
        <Button variant="secondary" className="self-start" onClick={() => void checks.fetchNextPage()} isLoading={checks.isFetchingNextPage}>
          {t('monitors.checks.loadMore')}
        </Button>
      )}
    </section>
  )
}

function checkCause(check: CheckResult): string {
  if (check.error_type) {
    return `${t(`monitors.errorTypes.${check.error_type}`)}: ${check.error_message ?? ''}`
  }

  if (check.warning_type) {
    return `${t(`monitors.warningTypes.${check.warning_type}`)}: ${check.warning_message ?? ''}`
  }

  return '—'
}
