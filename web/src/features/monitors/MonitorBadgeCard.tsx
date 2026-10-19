import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { disableMonitorBadge, enableMonitorBadge } from '../../api/monitors.ts'
import { queryKeys } from '../../api/queryKeys.ts'
import type { Monitor } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'

export function MonitorBadgeCard({ monitor }: { monitor: Monitor }) {
  const queryClient = useQueryClient()
  const { showToast } = useToast()
  const [isConfirmingRotation, setIsConfirmingRotation] = useState(false)
  const onSuccess = (saved: Monitor) => queryClient.setQueryData(queryKeys.monitor(saved.id), saved)
  const enableMutation = useMutation({ mutationFn: () => enableMonitorBadge(monitor.id), onSuccess })
  const disableMutation = useMutation({ mutationFn: () => disableMonitorBadge(monitor.id), onSuccess })
  const badgeUrl = monitor.badge_url ?? null

  return (
    <section className="flex flex-col gap-3" aria-labelledby="monitor-badge">
      <h2 id="monitor-badge" className="text-lg font-semibold text-neutral-900">
        {t('monitors.detail.badge.title')}
      </h2>
      <Card className="flex flex-col gap-3 px-4 py-4">
        <p className="text-sm text-neutral-600">{t('monitors.detail.badge.hint')}</p>
        {badgeUrl === null ? (
          <div>
            <Button
              variant="secondary"
              isLoading={enableMutation.isPending}
              onClick={() => enableMutation.mutate(undefined, { onSuccess: () => showToast(t('monitors.detail.badge.enabled')) })}
            >
              {t('monitors.detail.badge.enable')}
            </Button>
          </div>
        ) : (
          <>
            <div className="flex flex-wrap items-center gap-4">
              <img src={`${badgeUrl}/status.svg`} alt={t('monitors.detail.badge.status')} height={20} />
              <img src={`${badgeUrl}/uptime.svg`} alt={t('monitors.detail.badge.uptime')} height={20} />
            </div>
            <p className="text-xs text-neutral-500">{t('monitors.detail.badge.markdown')}</p>
            <code className="break-all rounded-md bg-neutral-50 px-3 py-2 font-mono text-xs text-neutral-700">{`![${monitor.name}](${badgeUrl}/status.svg) ![uptime](${badgeUrl}/uptime.svg)`}</code>
            <p className="text-xs text-neutral-500">{t('monitors.detail.badge.html')}</p>
            <code className="break-all rounded-md bg-neutral-50 px-3 py-2 font-mono text-xs text-neutral-700">{`<img src="${badgeUrl}/status.svg" alt="${monitor.name}">`}</code>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={() => setIsConfirmingRotation(true)}>
                {t('monitors.detail.badge.rotate')}
              </Button>
              <Button
                variant="ghost"
                isLoading={disableMutation.isPending}
                onClick={() => disableMutation.mutate(undefined, { onSuccess: () => showToast(t('monitors.detail.badge.disabled')) })}
              >
                {t('monitors.detail.badge.disable')}
              </Button>
            </div>
          </>
        )}
      </Card>
      <ConfirmDialog
        isOpen={isConfirmingRotation}
        title={t('monitors.detail.badge.rotateTitle')}
        description={t('monitors.detail.badge.rotateDescription')}
        confirmLabel={t('monitors.detail.badge.rotate')}
        pendingLabel={t('monitors.detail.badge.rotating')}
        isPending={enableMutation.isPending}
        onConfirm={() => enableMutation.mutate(undefined, { onSuccess: () => setIsConfirmingRotation(false) })}
        onClose={() => setIsConfirmingRotation(false)}
      />
    </section>
  )
}
