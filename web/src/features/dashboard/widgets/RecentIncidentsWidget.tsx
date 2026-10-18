import { useQuery } from '@tanstack/react-query'
import { listRecentIncidents } from '../../../api/dashboard.ts'
import { queryKeys } from '../../../api/queryKeys.ts'
import { t } from '../../../i18n/index.ts'
import { IncidentList } from '../../monitors/IncidentList.tsx'
import { QueryContent } from './QueryContent.tsx'
import { useRefreshInterval } from './refresh.ts'

export function RecentIncidentsWidget() {
  const refetchInterval = useRefreshInterval()
  const incidents = useQuery({ queryKey: queryKeys.recentIncidents, queryFn: () => listRecentIncidents(5), refetchInterval })

  return (
    <QueryContent query={incidents}>
      {(data) => (data.length === 0 ? <p className="text-neutral-600">{t('dashboard.noIncidents')}</p> : <IncidentList incidents={data} showMonitor />)}
    </QueryContent>
  )
}
