import { isAxiosError } from 'axios'
import { t } from '../i18n/index.ts'
import type { ValidationErrorResponse } from './types.ts'

export function fieldErrors(error: unknown): Record<string, string> {
  if (!isAxiosError<ValidationErrorResponse>(error) || error.response?.status !== 422) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(error.response.data.errors ?? {}).map(([field, messages]) => [field, messages[0] ?? '']),
  )
}

export function generalErrorMessage(error: unknown): string | null {
  if (!isAxiosError(error)) {
    return t('common.genericError')
  }

  if (!error.response) {
    return t('common.networkError')
  }

  if (error.response.status === 422) {
    return null
  }

  if (error.response.status === 429) {
    return t('common.tooManyRequests')
  }

  return t('common.genericError')
}
