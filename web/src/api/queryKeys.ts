import type { StatsPeriod } from './types.ts'

export const queryKeys = {
  config: ['config'] as const,
  summary: ['dashboard', 'summary'] as const,
  recentIncidents: ['dashboard', 'incidents'] as const,
  monitors: ['monitors'] as const,
  monitorList: (page: number, status?: string) => ['monitors', 'list', page, status ?? 'all'] as const,
  monitor: (id: number) => ['monitors', id] as const,
  stats: (id: number, period: StatsPeriod) => ['monitors', id, 'stats', period] as const,
  latency: (id: number, period: StatsPeriod) => ['monitors', id, 'latency', period] as const,
  uptimeHistory: (id: number) => ['monitors', id, 'uptime-history'] as const,
  checks: (id: number, from: string, to: string) => ['monitors', id, 'checks', from, to] as const,
  monitorIncidents: (id: number, page: number) => ['monitors', id, 'incidents', page] as const,
  notificationSettings: ['settings', 'notifications'] as const,
}
