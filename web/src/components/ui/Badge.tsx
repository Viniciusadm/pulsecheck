import type { ReactNode } from 'react'
import { t } from '../../i18n/index.ts'
import { DegradedIcon, DownIcon, MaintenanceIcon, PausedIcon, PendingIcon, UpIcon } from './icons.tsx'

export type DisplayStatus = 'up' | 'degraded' | 'down' | 'paused' | 'pending' | 'maintenance'

const statusStyles: Record<DisplayStatus, { className: string; icon: ReactNode }> = {
  up: { className: 'bg-status-up-soft text-status-up', icon: <UpIcon /> },
  degraded: { className: 'bg-status-degraded-soft text-status-degraded', icon: <DegradedIcon /> },
  down: { className: 'bg-status-down-soft text-status-down', icon: <DownIcon /> },
  paused: { className: 'bg-status-paused-soft text-status-paused', icon: <PausedIcon /> },
  maintenance: { className: 'bg-status-maintenance-soft text-status-maintenance', icon: <MaintenanceIcon /> },
  pending: { className: 'bg-status-pending-soft text-status-pending', icon: <PendingIcon /> },
}

export function Badge({ className = '', children }: { className?: string; children: ReactNode }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ${className}`}>
      {children}
    </span>
  )
}

export function StatusBadge({ status }: { status: DisplayStatus }) {
  const { className, icon } = statusStyles[status]

  return (
    <Badge className={className}>
      {icon}
      {t(`status.${status}`)}
    </Badge>
  )
}
