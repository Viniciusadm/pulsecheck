import { useState } from 'react'
import type { Monitor } from '../../api/types.ts'
import { Button } from '../../components/ui/Button.tsx'
import { Card } from '../../components/ui/Card.tsx'
import { ConfirmDialog } from '../../components/ui/ConfirmDialog.tsx'
import { RelativeTime } from '../../components/ui/RelativeTime.tsx'
import { useToast } from '../../components/ui/toast.ts'
import { t } from '../../i18n/index.ts'
import { useRotateHeartbeatToken } from './useMonitorMutations.ts'

export function HeartbeatPanel({ monitor, heartbeatUrl }: { monitor: Monitor; heartbeatUrl: string }) {
  const { showToast } = useToast()
  const rotateMutation = useRotateHeartbeatToken(monitor.id)
  const [isConfirmingRotation, setIsConfirmingRotation] = useState(false)

  const copy = () => {
    void navigator.clipboard?.writeText(heartbeatUrl).then(() => showToast(t('monitors.detail.heartbeat.copied')))
  }

  const rotate = () =>
    rotateMutation.mutate(undefined, {
      onSuccess: () => {
        setIsConfirmingRotation(false)
        showToast(t('monitors.detail.heartbeat.regenerated'))
      },
    })

  return (
    <section className="flex flex-col gap-3" aria-labelledby="heartbeat-url">
      <h2 id="heartbeat-url" className="text-lg font-semibold text-neutral-900">
        {t('monitors.detail.heartbeat.title')}
      </h2>
      <Card className="flex flex-col gap-3 px-4 py-4">
        <p className="text-sm text-neutral-600">{t('monitors.detail.heartbeat.hint')}</p>
        <code className="break-all rounded-md bg-neutral-50 px-3 py-2 font-mono text-sm text-neutral-900">{heartbeatUrl}</code>
        <p className="text-xs text-neutral-500">{t('monitors.detail.heartbeat.example')}</p>
        <code className="break-all rounded-md bg-neutral-50 px-3 py-2 font-mono text-xs text-neutral-700">{`curl -fsS -m 10 --retry 3 ${heartbeatUrl}`}</code>
        <p className="text-sm text-neutral-600">
          {monitor.last_heartbeat_at ? (
            <RelativeTime value={monitor.last_heartbeat_at} format={(time) => t('monitors.detail.heartbeat.lastPing', { time })} />
          ) : (
            t('monitors.detail.heartbeat.noPing')
          )}
        </p>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={copy}>
            {t('monitors.detail.heartbeat.copy')}
          </Button>
          <Button variant="ghost" onClick={() => setIsConfirmingRotation(true)}>
            {t('monitors.detail.heartbeat.regenerate')}
          </Button>
        </div>
      </Card>
      <ConfirmDialog
        isOpen={isConfirmingRotation}
        title={t('monitors.detail.heartbeat.regenerateTitle')}
        description={t('monitors.detail.heartbeat.regenerateDescription')}
        confirmLabel={t('monitors.detail.heartbeat.regenerate')}
        pendingLabel={t('monitors.detail.heartbeat.regenerating')}
        isPending={rotateMutation.isPending}
        onConfirm={rotate}
        onClose={() => setIsConfirmingRotation(false)}
      />
    </section>
  )
}
