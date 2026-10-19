import type { KeywordMode, Monitor, MonitorOptions, MonitorType } from '../../api/types.ts'
import { initialHttpRequestState, requestOptions, type HttpRequestState } from './httpRequest.ts'

export type MonitorTypeDefinition = {
  usesUrl: boolean
  usesRequest: boolean
  usesTimeout: boolean
  usesLatencyThreshold: boolean
  isPassive: boolean
}

export const monitorTypes: Record<MonitorType, MonitorTypeDefinition> = {
  http: { usesUrl: true, usesRequest: true, usesTimeout: true, usesLatencyThreshold: true, isPassive: false },
  keyword: { usesUrl: true, usesRequest: true, usesTimeout: true, usesLatencyThreshold: true, isPassive: false },
  tcp: { usesUrl: false, usesRequest: false, usesTimeout: true, usesLatencyThreshold: true, isPassive: false },
  ssl: { usesUrl: true, usesRequest: false, usesTimeout: true, usesLatencyThreshold: false, isPassive: false },
  heartbeat: { usesUrl: false, usesRequest: false, usesTimeout: false, usesLatencyThreshold: false, isPassive: true },
}

export const monitorTypeOrder: MonitorType[] = ['http', 'keyword', 'tcp', 'ssl', 'heartbeat']

export const KEYWORD_MODES: KeywordMode[] = ['contains', 'not_contains']

export type TargetState = {
  keyword: string
  keywordMode: KeywordMode
  caseSensitive: boolean
  warningDays: string
  host: string
  port: string
  graceSeconds: string
}

export function initialTargetState(monitor?: Monitor): TargetState {
  const options = monitor?.options

  return {
    keyword: options?.keyword ?? '',
    keywordMode: options?.keyword_mode ?? 'contains',
    caseSensitive: options?.case_sensitive ?? false,
    warningDays: String(options?.warning_days ?? 14),
    host: options?.host ?? '',
    port: options?.port == null ? '' : String(options.port),
    graceSeconds: String(options?.grace_seconds ?? 60),
  }
}

function toNumber(value: string): number | null {
  return value.trim() === '' ? null : Number(value)
}

export function typeOptions(type: MonitorType, request: HttpRequestState, target: TargetState): MonitorOptions {
  switch (type) {
    case 'http':
      return requestOptions(request)
    case 'keyword':
      return { ...requestOptions(request), keyword: target.keyword, keyword_mode: target.keywordMode, case_sensitive: target.caseSensitive }
    case 'ssl':
      return { warning_days: toNumber(target.warningDays) ?? undefined }
    case 'tcp':
      return { host: target.host.trim(), port: toNumber(target.port) }
    case 'heartbeat':
      return { grace_seconds: toNumber(target.graceSeconds) ?? undefined }
  }
}

export function changedTypeOptions(type: MonitorType, request: HttpRequestState, target: TargetState, monitor?: Monitor): MonitorOptions | undefined {
  const options = typeOptions(type, request, target)

  if (!monitor && type !== 'http') {
    return options
  }

  const current = typeOptions(type, initialHttpRequestState(monitor), initialTargetState(monitor))

  return JSON.stringify(options) === JSON.stringify(current) ? undefined : options
}
