import type { HttpAuthType, HttpMethod, Monitor, MonitorOptions, MonitorSecretsInput } from '../../api/types.ts'
import { headerRowsToRecord, type HeaderRow } from '../../components/ui/headerRows.ts'

export const HTTP_METHODS: HttpMethod[] = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE']

export const METHODS_WITHOUT_BODY: HttpMethod[] = ['GET', 'HEAD']

export const AUTH_TYPES: HttpAuthType[] = ['none', 'basic', 'bearer']

const DEFAULT_OPTIONS: Required<MonitorOptions> = {
  method: 'GET',
  follow_redirects: true,
  expected_status_codes: ['200-399'],
  body_content_type: null,
}

export type HttpRequestState = {
  method: HttpMethod
  expectedStatus: string
  followRedirects: boolean
  contentType: string
  body: string
  authType: HttpAuthType
  username: string
  password: string
  token: string
  headers: HeaderRow[]
  isReplacingHeaders: boolean
}

export function initialHttpRequestState(monitor?: Monitor): HttpRequestState {
  const options = { ...DEFAULT_OPTIONS, ...monitor?.options }

  return {
    method: options.method,
    expectedStatus: options.expected_status_codes.join(', '),
    followRedirects: options.follow_redirects,
    contentType: options.body_content_type ?? '',
    body: '',
    authType: monitor?.auth_type ?? 'none',
    username: '',
    password: '',
    token: '',
    headers: [],
    isReplacingHeaders: !(monitor?.secrets_set.includes('headers') ?? false),
  }
}

export function hasCustomRequest(monitor?: Monitor): boolean {
  if (!monitor) {
    return false
  }

  return monitor.secrets_set.length > 0 || JSON.stringify(requestOptions(initialHttpRequestState(monitor))) !== JSON.stringify(requestOptions(initialHttpRequestState()))
}

export function requestOptions(state: HttpRequestState): Required<MonitorOptions> {
  return {
    method: state.method,
    follow_redirects: state.followRedirects,
    expected_status_codes: state.expectedStatus.split(',').map((code) => code.trim()).filter((code) => code !== ''),
    body_content_type: state.contentType.trim() === '' ? null : state.contentType.trim(),
  }
}

export function changedRequestOptions(state: HttpRequestState, monitor?: Monitor): MonitorOptions | undefined {
  const options = requestOptions(state)
  const current = requestOptions(initialHttpRequestState(monitor))

  return JSON.stringify(options) === JSON.stringify(current) ? undefined : options
}

export function requestSecrets(state: HttpRequestState, monitor?: Monitor): MonitorSecretsInput | undefined {
  const secrets: MonitorSecretsInput = {}

  if (state.isReplacingHeaders && (monitor?.secrets_set.includes('headers') || state.headers.length > 0)) {
    secrets.headers = headerRowsToRecord(state.headers)
  }

  if (!METHODS_WITHOUT_BODY.includes(state.method) && state.body !== '') {
    secrets.body = state.body
  }

  const auth = requestAuth(state, monitor)

  if (auth !== undefined) {
    secrets.auth = auth
  }

  return Object.keys(secrets).length === 0 ? undefined : secrets
}

function requestAuth(state: HttpRequestState, monitor?: Monitor): MonitorSecretsInput['auth'] | undefined {
  const savedType = monitor?.auth_type ?? 'none'

  if (state.authType === 'none') {
    return savedType === 'none' ? undefined : { type: 'none' }
  }

  if (state.authType === 'basic' && (state.authType !== savedType || state.username !== '' || state.password !== '')) {
    return { type: 'basic', username: state.username, password: state.password }
  }

  if (state.authType === 'bearer' && (state.authType !== savedType || state.token !== '')) {
    return { type: 'bearer', token: state.token }
  }

  return undefined
}
