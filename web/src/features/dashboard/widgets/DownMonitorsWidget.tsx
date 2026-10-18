import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { listMonitors } from '../../../api/monitors.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import type { Monitor } from '../../../api/types.ts'
import { StatusBadge } from '../../../components/ui/Badge.tsx'
import { UpIcon } from '../../../components/ui/icons.tsx'
import { RelativeTime } from '../../../components/ui/RelativeTime.tsx'
import { t } from '../../../i18n/index.ts'
import { hostOf } from '../../../lib/monitor.ts'
import { QueryContent } from './QueryContent.tsx'
import { useRefreshInterval } from './refresh.ts'

export function DownMonitorsWidget() {
  const refetchInterval = useRefreshInterval()
  const downMonitors = useQuery({
    queryKey: queryKeys.monitorList(1, 'down'),
    queryFn: () => listMonitors({ status: 'down' }),
    refetchInterval,
  })

  return (
    <QueryContent query={downMonitors}>
      {(data) => (
        <div className="flex flex-col gap-3">
          <AttentionList monitors={data.data} />
          {data.meta.total > data.data.length && (
            <Link to="/monitors?status=down" className="font-medium text-primary-700 hover:underline">
              {t('dashboard.viewAllDown')}
            </Link>
          )}
        </div>
      )}
    </QueryContent>
  )
}

function AttentionList({ monitors }: { monitors: Monitor[] }) {
  if (monitors.length === 0) {
    return (
      <p className="flex items-center gap-2 font-medium text-status-up">
        <UpIcon />
        {t('dashboard.allHealthy')}
      </p>
    )
  }

  return (
    <ul className="divide-y divide-border">
      {monitors.map((monitor) => (
        <li key={monitor.id} className="flex flex-col gap-1 py-2.5 first:pt-0 last:pb-0 @md:flex-row @md:items-center @md:gap-4">
          <div className="min-w-0 flex-1">
            <Link to={`/monitors/${monitor.id}`} className="font-semibold text-neutral-900 hover:text-primary-700 hover:underline">
              {monitor.name}
            </Link>
            <p className="truncate text-xs text-neutral-500">{hostOf(monitor.url)}</p>
          </div>
          <div className="flex items-center gap-3 text-sm text-neutral-600">
            {monitor.last_status_code !== null && <span className="tabular-nums">HTTP {monitor.last_status_code}</span>}
            {monitor.last_checked_at !== null && <RelativeTime value={monitor.last_checked_at} />}
            <StatusBadge status="down" />
          </div>
        </li>
      ))}
    </ul>
  )
}
